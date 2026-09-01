<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectResourcesTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $procurement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $this->procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
    }

    public function test_procurement_fulfills_item_status_and_cost_operational_cannot(): void
    {
        $project = $this->planningProject();
        $item = $project->actualProcurements()->firstOrFail();
        $product = VendorProduct::create([
            'vendor_id' => Vendor::create(['name' => 'V'])->id,
            'item_name' => 'Kabel', 'category' => 'material', 'price' => 500000, 'unit' => 'roll', 'is_active' => true,
        ]);

        // Operational tidak boleh ubah status/vendor
        $this->actingAs($this->ops)->put("/procurement/project-procurements/{$item->id}", [
            'cost_price' => 400000, 'status' => 'purchased',
        ])->assertForbidden();

        // Procurement boleh
        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$item->id}", [
            'vendor_product_id' => $product->id,
            'cost_price' => 450000,
            'status' => 'purchased',
        ])->assertSessionHas('success');

        $item->refresh();
        $this->assertSame('purchased', $item->status);
        $this->assertSame('450000.00', $item->cost_price);
        $this->assertSame($product->vendor_id, $item->vendor_id);
        $this->assertSame($this->procurement->id, $item->handled_by);
        $this->assertNotNull($item->purchased_at);
    }

    public function test_project_moves_to_waiting_resource_when_purchasing_starts(): void
    {
        $project = $this->planningProject();
        $item = $project->actualProcurements()->firstOrFail();

        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$item->id}", [
            'cost_price' => 100000, 'status' => 'purchased',
        ]);

        $this->assertSame('waiting_resource', $project->fresh()->status);
    }

    public function test_all_received_notifies_operational(): void
    {
        $project = $this->planningProject();
        $item = $project->actualProcurements()->firstOrFail();

        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$item->id}", [
            'cost_price' => 100000, 'status' => 'received',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->ops->id,
            'type' => 'project_procurement.ready',
        ]);
    }

    public function test_operational_adds_and_deletes_extra_item_only_while_pending(): void
    {
        $project = $this->planningProject();

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/actual-procurements", [
            'item_name' => 'Bracket tambahan', 'qty' => 4, 'unit' => 'pcs', 'cost_price' => 25000,
        ])->assertSessionHas('success');

        $extra = $project->actualProcurements()->where('item_name', 'Bracket tambahan')->firstOrFail();
        $this->assertSame($this->ops->id, $extra->requested_by);

        $this->actingAs($this->ops)->delete("/operational/projects/{$project->id}/actual-procurements/{$extra->id}")
            ->assertSessionHas('success');

        // item yang sudah diproses tak bisa dihapus
        $seed = $project->actualProcurements()->firstOrFail();
        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$seed->id}", [
            'cost_price' => 1, 'status' => 'purchased',
        ]);
        $this->actingAs($this->ops)->delete("/operational/projects/{$project->id}/actual-procurements/{$seed->id}")
            ->assertSessionHas('error');
    }

    public function test_assign_technicians_requires_exactly_one_leader(): void
    {
        $project = $this->planningProject();
        $t1 = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $t2 = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$t1->id], 'leader_id' => $t2->id,
        ])->assertSessionHasErrors('leader_id');

        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$t1->id, $t2->id], 'leader_id' => $t1->id,
        ])->assertSessionHas('success');
        $this->assertSame(1, $project->technicians()->where('is_leader', true)->count());
    }

    public function test_mark_ready_needs_all_items_received_plus_task_and_leader(): void
    {
        $project = $this->planningProject();
        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $item = $project->actualProcurements()->firstOrFail();

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);
        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ]);

        // item belum received
        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$item->id}", [
            'cost_price' => 1, 'status' => 'purchased',
        ]);
        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/ready")
            ->assertSessionHasErrors('project');

        $this->actingAs($this->procurement)->put("/procurement/project-procurements/{$item->id}", [
            'cost_price' => 1, 'status' => 'received',
        ]);
        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/ready")
            ->assertSessionHas('success');
        $this->assertSame('ready', $project->fresh()->status);
    }

    public function test_pure_service_project_can_be_ready_without_procurement(): void
    {
        $project = $this->planningProject();
        $project->actualProcurements()->delete(); // murni jasa
        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/tasks", ['title' => 'Instalasi']);
        $this->actingAs($this->ops)->put("/operational/projects/{$project->id}/technicians", [
            'technician_ids' => [$tech->id], 'leader_id' => $tech->id,
        ]);

        $this->actingAs($this->ops)->post("/operational/projects/{$project->id}/ready")
            ->assertSessionHas('success');
        $this->assertSame('ready', $project->fresh()->status);
    }

    private function planningProject(): Project
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("mixed"));
        $so = SalesOrder::firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = Invoice::firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::firstOrFail();
        $project->update(['status' => 'planning']);

        return $project->fresh();
    }
}
