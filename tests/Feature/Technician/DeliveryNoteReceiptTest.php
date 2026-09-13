<?php

namespace Tests\Feature\Technician;

use App\Models\Contact;
use App\Models\DeliveryNote;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeliveryNoteReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_leader_can_confirm_receipt_member_cannot(): void
    {
        [$project, $leader, $member] = $this->projectWithDeliveryNote();
        $dn = DeliveryNote::firstOrFail();

        // member bisa lihat, tapi tidak bisa konfirmasi
        $this->actingAs($member)->get("/technician/delivery-notes/{$dn->id}")->assertOk();
        $this->actingAs($member)->post("/technician/delivery-notes/{$dn->id}/receive")->assertForbidden();

        // leader bisa konfirmasi
        $this->actingAs($leader)->post("/technician/delivery-notes/{$dn->id}/receive")->assertRedirect();

        $dn->refresh();
        $this->assertSame('received', $dn->status);
        $this->assertSame($leader->id, $dn->received_by);
        $this->assertNotNull($dn->received_at);
    }

    public function test_outsider_technician_cannot_view_delivery_note(): void
    {
        [$project] = $this->projectWithDeliveryNote();
        $dn = DeliveryNote::firstOrFail();
        $outsider = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($outsider)->get("/technician/delivery-notes/{$dn->id}")->assertForbidden();
        $this->actingAs($outsider)->get("/technician/projects/{$project->id}/delivery-notes")->assertForbidden();
    }

    public function test_cannot_receive_twice(): void
    {
        [$project, $leader] = $this->projectWithDeliveryNote();
        $dn = DeliveryNote::firstOrFail();

        $this->actingAs($leader)->post("/technician/delivery-notes/{$dn->id}/receive")->assertRedirect();
        $this->actingAs($leader)->post("/technician/delivery-notes/{$dn->id}/receive")->assertForbidden();
    }

    /** @return array{Project, User, User} [project, leader, member] */
    private function projectWithDeliveryNote(): array
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'address' => 'Jl. Contoh', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'category' => 'material', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::latest('id')->firstOrFail();
        $quotation->update(['status' => 'sent']);
        // Mixed — project Material Only sekarang tidak pakai tim teknisi sama sekali
        // (Operational langsung Delivery Note + selesai, tanpa konfirmasi teknisi).
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));
        $so = SalesOrder::with('lines')->latest('id')->firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $leader = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $member = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$leader->id, $member->id], 'leader_id' => $leader->id,
        ]);

        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
            'lines' => [['sales_order_line_id' => $so->lines->first()->id, 'qty_delivered' => 2]],
        ]);

        return [$project->fresh(), $leader, $member];
    }
}
