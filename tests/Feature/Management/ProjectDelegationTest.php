<?php

namespace Tests\Feature\Management;

use App\Models\Bast;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_can_view_all_projects_read_only(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $project = $this->plannedProject();

        $this->actingAs($management)->get('/management/projects')->assertOk();
        $this->actingAs($management)->get("/management/projects/{$project->id}")->assertOk();
    }

    public function test_project_overview_shows_stage_options_and_won_flag(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $project = $this->plannedProject();

        $res = $this->actingAs($management)->get("/management/projects/{$project->id}");
        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->where('project.is_won', false)
            ->has('project.stage_options', 7));

        $project->salesOrder->update(['status' => 'won']);

        $res2 = $this->actingAs($management)->get("/management/projects/{$project->id}");
        $res2->assertInertia(fn ($page) => $page->where('project.is_won', true));

        $listRes = $this->actingAs($management)->get('/management/projects');
        $row = collect($listRes->viewData('page')['props']['projects']['data'])->firstWhere('id', $project->id);
        $this->assertTrue($row['is_won']);
    }

    public function test_management_delegates_and_revokes_project(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $project = $this->plannedProject();

        $this->actingAs($management)->put("/management/projects/{$project->id}/delegate", [
            'project_manager_id' => $pm->id,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame($pm->id, $project->delegated_to);
        $this->assertSame($management->id, $project->delegated_by);
        $this->assertNotNull($project->delegated_at);

        // PM sekarang bisa lihat project ini
        $this->actingAs($pm)->get("/management/projects")->assertForbidden();
        $this->actingAs($pm)->get("/project-manager/projects/{$project->id}")->assertOk();
        $this->assertCount(1, Project::where('delegated_to', $pm->id)->get());

        // tarik kembali
        $this->actingAs($management)->put("/management/projects/{$project->id}/delegate", [
            'project_manager_id' => '',
        ])->assertRedirect();
        $project->refresh();
        $this->assertNull($project->delegated_to);
        $this->assertNull($project->delegated_by);

        // PM tidak lagi bisa lihat setelah ditarik
        $this->actingAs($pm)->get("/project-manager/projects/{$project->id}")->assertForbidden();
    }

    public function test_project_manager_only_sees_projects_delegated_to_them(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $pmA = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $pmB = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $projectA = $this->plannedProject();
        $projectB = $this->plannedProject();

        $this->actingAs($management)->put("/management/projects/{$projectA->id}/delegate", ['project_manager_id' => $pmA->id]);
        $this->actingAs($management)->put("/management/projects/{$projectB->id}/delegate", ['project_manager_id' => $pmB->id]);

        $res = $this->actingAs($pmA)->get('/project-manager/projects');
        $res->assertOk();
        $ids = collect($res->viewData('page')['props']['projects']['data'])->pluck('id')->all();
        $this->assertSame([$projectA->id], $ids);

        // PM A tidak boleh buka detail project B
        $this->actingAs($pmA)->get("/project-manager/projects/{$projectB->id}")->assertForbidden();
    }

    public function test_project_manager_cannot_delegate(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $pmOther = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $project = $this->plannedProject();
        $project->update(['delegated_to' => $pm->id]);

        $this->actingAs($pm)->put("/management/projects/{$project->id}/delegate", [
            'project_manager_id' => $pmOther->id,
        ])->assertForbidden();
    }

    public function test_operational_role_unaffected_by_new_policy_rules(): void
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $project = $this->plannedProject();

        $this->actingAs($ops)->get("/operational/projects/{$project->id}")->assertOk();
    }

    private function plannedProject(): Project
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust '.uniqid(), 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::latest('id')->firstOrFail();
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

        return Project::where('sales_order_id', $so->id)->firstOrFail();
    }
}
