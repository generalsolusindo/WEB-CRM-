<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
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

class MaterialDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithTwoMaterialLines(): Project
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'address' => 'Jl. X', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'category' => 'material', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $lead->requirements()->create(['item_name' => 'Switch', 'category' => 'material', 'qty' => 4, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 100000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $lines = $pr->lines->sortBy('id')->values();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $lines[0]->id, 'selling_price' => 150000],
                ['procurement_request_line_id' => $lines[1]->id, 'selling_price' => 150000],
            ],
        ]);
        $q = Quotation::firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('material_only'));
        $so = SalesOrder::with('lines')->firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        return Project::where('sales_order_id', $so->id)->firstOrFail();
    }

    public function test_operational_project_page_shows_partial_material_status(): void
    {
        Storage::fake('local');
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->projectWithTwoMaterialLines();
        $so = $project->salesOrder;
        $router = $so->lines->firstWhere('item_name', 'Router');

        // kirim Router lengkap (2/2), Switch belum sama sekali
        $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
            'delivery_method' => 'sendiri',
            'delivery_address' => 'Site A',
            'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
            'lines' => [['sales_order_line_id' => $router->id, 'qty_delivered' => 2]],
        ]);

        $res = $this->actingAs($ops)->get("/operational/projects/{$project->id}");
        $res->assertOk();
        $status = $res->viewData('page')['props']['materialStatus'];
        $this->assertSame(2, $status['total']);
        $this->assertSame(1, $status['complete']);
        $this->assertFalse($status['is_complete']);
    }

    public function test_management_overview_shows_complete_material_status(): void
    {
        Storage::fake('local');
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->projectWithTwoMaterialLines();
        $so = $project->salesOrder;

        foreach ($so->lines as $line) {
            $this->actingAs($ops)->post("/operational/sales-orders/{$so->id}/delivery-notes", [
                'delivery_method' => 'sendiri',
                'delivery_address' => 'Site A',
                'dispatch_proof' => UploadedFile::fake()->image('bukti.jpg'),
                'lines' => [['sales_order_line_id' => $line->id, 'qty_delivered' => $line->qty]],
            ]);
        }

        $res = $this->actingAs($management)->get("/management/projects/{$project->id}");
        $res->assertOk();
        $status = $res->viewData('page')['props']['project']['material_status'];
        $this->assertSame(2, $status['total']);
        $this->assertSame(2, $status['complete']);
        $this->assertTrue($status['is_complete']);

        // muncul juga di daftar
        $listRes = $this->actingAs($management)->get('/management/projects');
        $row = collect($listRes->viewData('page')['props']['projects']['data'])->firstWhere('id', $project->id);
        $this->assertTrue($row['material_status']['is_complete']);
    }
}
