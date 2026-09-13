<?php

namespace Tests\Feature\Management;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityDelegationTest extends TestCase
{
    use RefreshDatabase;

    private function opportunity(): Lead
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);

        return Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
    }

    public function test_management_can_view_and_delegate_opportunity(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $lead = $this->opportunity();

        $this->actingAs($management)->get('/management/opportunities')->assertOk();
        $this->actingAs($management)->get("/management/opportunities/{$lead->id}")->assertOk();

        $this->actingAs($management)->put("/management/opportunities/{$lead->id}/delegate", [
            'project_manager_id' => $pm->id,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame($pm->id, $lead->delegated_to);
        $this->assertSame($management->id, $lead->delegated_by);
        $this->assertNotNull($lead->delegated_at);
    }

    public function test_management_filters_opportunities_by_stage(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $qualified = $this->opportunity();
        $won = $this->opportunity();
        $won->update(['stage' => 'won']);

        $res = $this->actingAs($management)->get('/management/opportunities?stage=won');
        $res->assertOk();
        $ids = collect($res->viewData('page')['props']['opportunities']['data'])->pluck('id')->all();
        $this->assertSame([$won->id], $ids);

        $unfiltered = $this->actingAs($management)->get('/management/opportunities');
        $allIds = collect($unfiltered->viewData('page')['props']['opportunities']['data'])->pluck('id')->all();
        $this->assertContains($qualified->id, $allIds);
        $this->assertContains($won->id, $allIds);
    }

    public function test_opportunity_stage_is_labeled_correctly(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $lead = $this->opportunity();
        $lead->update(['stage' => 'won']);

        $res = $this->actingAs($management)->get("/management/opportunities/{$lead->id}");
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->where('opportunity.stage', 'won')
            ->where('opportunity.stage_label', 'Won'));
    }

    public function test_pm_only_sees_opportunities_delegated_to_them(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $pmA = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $pmB = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $leadA = $this->opportunity();
        $leadB = $this->opportunity();

        $this->actingAs($management)->put("/management/opportunities/{$leadA->id}/delegate", ['project_manager_id' => $pmA->id]);
        $this->actingAs($management)->put("/management/opportunities/{$leadB->id}/delegate", ['project_manager_id' => $pmB->id]);

        $res = $this->actingAs($pmA)->get('/project-manager/opportunities');
        $res->assertOk();
        $ids = collect($res->viewData('page')['props']['opportunities']['data'])->pluck('id')->all();
        $this->assertSame([$leadA->id], $ids);

        $this->actingAs($pmA)->get("/project-manager/opportunities/{$leadB->id}")->assertForbidden();
    }

    public function test_pm_and_sales_cannot_delegate(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $lead = $this->opportunity();

        $this->actingAs($pm)->put("/management/opportunities/{$lead->id}/delegate", [
            'project_manager_id' => $pm->id,
        ])->assertForbidden();

        $this->actingAs($lead->sales)->put("/management/opportunities/{$lead->id}/delegate", [
            'project_manager_id' => $pm->id,
        ])->assertForbidden();
    }

    public function test_project_inherits_delegation_from_opportunity_automatically(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $this->actingAs($management)->put("/management/opportunities/{$lead->id}/delegate", ['project_manager_id' => $pm->id]);

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
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('material_only'));
        $so = SalesOrder::firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $this->assertSame($pm->id, $project->delegated_to);
        $this->assertSame($management->id, $project->delegated_by);
        $this->assertNotNull($project->delegated_at);

        // PM otomatis lihat project ini juga tanpa didelegasikan ulang manual
        $this->actingAs($pm)->get("/project-manager/projects/{$project->id}")->assertOk();
    }
}
