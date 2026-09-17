<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use RefreshDatabase;
    use BuildsProcurementProject;

    public function test_management_sees_overview_other_roles_do_not(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($management)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('managementOverview')
                ->where('salesActions', null));

        $this->actingAs($sales)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('managementOverview', null));
    }

    public function test_overview_reflects_open_quotation_pipeline_and_lead_stage(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
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

        $res = $this->actingAs($management)->get('/dashboard');
        $overview = $res->viewData('page')['props']['managementOverview'];

        $this->assertSame(1, $overview['sales']['open_quotations']);
        $this->assertSame(2600000.0, $overview['sales']['pipeline_value']);
        $stage = collect($overview['sales']['leads_by_stage'])->firstWhere('label', 'Quotation');
        $this->assertSame(1, $stage['count']);
    }

    /**
     * Rumus di ringkasan Dashboard harus identik dengan laporan "Profit Project"
     * (scope=won) — dipakai sama-sama dari ProjectProfitCalculator.
     */
    public function test_overview_shows_revenue_and_profit_for_projects_won_this_month(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $project = $this->materialProject();
        $project->salesOrder->update(['status' => 'won']);

        // Project lain yang masih berjalan (belum Won) tidak boleh ikut terhitung.
        $this->materialProject();

        $res = $this->actingAs($management)->get('/dashboard');
        $revenue = $res->viewData('page')['props']['managementOverview']['revenue'];

        // Router qty 2 x cost 1.000.000 (belum disourcing, masih estimasi) = HPP 2.000.000.
        // Selling price = cost x 1.3 = 1.300.000 x 2 = harga jual 2.600.000. Profit 600.000.
        $this->assertSame(2600000.0, $revenue['revenue_this_month']);
        $this->assertSame(600000.0, $revenue['profit_this_month']);
        $this->assertSame(1, $revenue['won_count_this_month']);
        $this->assertSame(30.0, $revenue['margin_percent']);
    }

    public function test_non_management_cannot_be_shown_overview_even_if_role_changes(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->actingAs($procurement)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('managementOverview', null)
            ->has('procurementActions'));
    }
}
