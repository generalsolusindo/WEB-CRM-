<?php

namespace Tests\Feature\Hr;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SowReviewTest extends TestCase
{
    use RefreshDatabase;

    private function pendingSow(): array
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'ODP', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = $lead->procurementRequests()->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = $pr->quotations()->latest('id')->firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));
        $so = $quotation->salesOrder()->firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true]);
        $project->update(['vendor_id' => $vendor->id]);
        $technician = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id, 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa Terminasi FO', 'technician_id' => $technician->id,
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit");

        $sow = Sow::where('project_id', $project->id)->firstOrFail();

        return [$sow, $ops, $technician];
    }

    public function test_hr_sees_technician_nik_and_ktp_link_only_when_uploaded(): void
    {
        Storage::fake('local');
        [$sow, $ops, $technician] = $this->pendingSow();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);
        $technician->update(['nik' => '3201234567890001']);

        $this->actingAs($hr)->get("/hr/sows/{$sow->id}")
            ->assertInertia(fn ($page) => $page->where('technicianKtp.nik', '3201234567890001')->where('technicianKtp.url', null));

        $technician->attachments()->create([
            'category' => 'ktp_document',
            'file_path' => UploadedFile::fake()->image('ktp.jpg')->store('ktp-documents'),
            'uploaded_by' => $ops->id,
        ]);

        $this->actingAs($hr)->get("/hr/sows/{$sow->id}")
            ->assertInertia(fn ($page) => $page->where('technicianKtp.nik', '3201234567890001')->whereType('technicianKtp.url', 'string'));
    }

    public function test_other_roles_do_not_receive_technician_ktp_data(): void
    {
        [$sow, , $technician] = $this->pendingSow();
        $technician->update(['nik' => '3201234567890001']);
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);

        $this->actingAs($management)->get("/management/sows/{$sow->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('technicianKtp'));
        $this->assertStringNotContainsString('3201234567890001', $this->actingAs($management)->get("/management/sows/{$sow->id}")->getContent());
    }

    public function test_hr_can_approve_sow_content_and_technician_is_notified(): void
    {
        [$sow, , $technician] = $this->pendingSow();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($hr)->get('/hr/sows')->assertOk();
        $this->actingAs($hr)->get("/hr/sows/{$sow->id}")->assertOk();

        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", ['approved' => true])->assertRedirect();

        $sow->refresh();
        $this->assertSame('pending_technician_signature', $sow->status);
        $this->assertSame($hr->id, $sow->hr_content_reviewed_by);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $technician->id,
            'type' => 'sow.pending_technician_signature',
            'related_id' => $sow->id,
        ]);
    }

    public function test_hr_can_reject_sow_content_and_operational_is_notified(): void
    {
        [$sow, $ops] = $this->pendingSow();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", [
            'approved' => false, 'notes' => 'Nomor SOW salah format',
        ])->assertRedirect();

        $sow->refresh();
        $this->assertSame('rejected_by_hr', $sow->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ops->id,
            'type' => 'sow.rejected_by_hr',
            'related_id' => $sow->id,
        ]);

        // Operational bisa edit lagi setelah ditolak.
        $this->actingAs($ops)->put("/operational/projects/{$sow->project_id}/sow", [
            'number' => 'SOW-001-REV', 'project_name' => 'Jasa Terminasi FO',
        ])->assertRedirect();
    }

    public function test_reject_requires_notes(): void
    {
        [$sow] = $this->pendingSow();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", ['approved' => false])
            ->assertSessionHasErrors('notes');
    }

    public function test_hr_cannot_review_sow_not_pending_review(): void
    {
        [$sow] = $this->pendingSow();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);
        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", ['approved' => true]);

        // Sudah pending_technician_signature — review kedua harus ditolak.
        $this->actingAs($hr)->post("/hr/sows/{$sow->id}/review", ['approved' => true])->assertForbidden();
    }

    public function test_non_hr_cannot_review(): void
    {
        [$sow, $ops] = $this->pendingSow();

        $this->actingAs($ops)->get('/hr/sows')->assertForbidden();
        $this->actingAs($ops)->post("/hr/sows/{$sow->id}/review", ['approved' => true])->assertForbidden();
    }
}
