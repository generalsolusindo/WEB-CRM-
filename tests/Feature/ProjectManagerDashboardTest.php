<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_sees_project_overview_other_roles_do_not(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($pm)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('projectManagerOverview')
                ->where('salesActions', null));

        $this->actingAs($sales)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('projectManagerOverview', null));
    }

    public function test_overview_only_counts_projects_delegated_to_this_pm(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $otherPm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);

        $so1 = $this->confirmedSalesOrder();
        Project::create(['sales_order_id' => $so1->id, 'status' => 'planning', 'delegated_to' => $pm->id]);
        $so2 = $this->confirmedSalesOrder();
        Project::create(['sales_order_id' => $so2->id, 'status' => 'in_progress', 'delegated_to' => $pm->id]);
        $so3 = $this->confirmedSalesOrder();
        Project::create(['sales_order_id' => $so3->id, 'status' => 'completed', 'delegated_to' => $pm->id]);
        $so4 = $this->confirmedSalesOrder();
        Project::create(['sales_order_id' => $so4->id, 'status' => 'planning', 'delegated_to' => $otherPm->id]);

        $res = $this->actingAs($pm)->get('/dashboard');
        $overview = $res->viewData('page')['props']['projectManagerOverview'];

        $this->assertSame(3, $overview['total']);
        $this->assertSame(1, $overview['completed']);
        $this->assertCount(2, $overview['active_projects']);
        $this->assertTrue(collect($overview['active_projects'])->every(fn ($p) => $p['status'] !== 'completed'));
    }

    /** @return SalesOrder */
    private function confirmedSalesOrder()
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::where('lead_id', $lead->id)->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $q = $pr->quotations()->latest('id')->firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('material_only'));

        return SalesOrder::where('quotation_id', $q->id)->latest('id')->firstOrFail();
    }
}
