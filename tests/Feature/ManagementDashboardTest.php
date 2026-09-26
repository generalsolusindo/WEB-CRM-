<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

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
        $this->assertSame(2600000.0, $revenue['revenue']);
        $this->assertSame(600000.0, $revenue['profit']);
        $this->assertSame(1, $revenue['won_count']);
        $this->assertSame(30.0, $revenue['margin_percent']);
    }

    /**
     * Periode boleh dipilih bebas lewat query string, dan hasilnya tidak boleh
     * ikut menghitung project Won di luar rentang tanggal yang diminta.
     */
    public function test_revenue_can_be_filtered_to_a_custom_date_range(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $project = $this->materialProject();
        $project->salesOrder->update(['status' => 'won']);
        $project->forceFill(['created_at' => '2026-01-15'])->save();

        $res = $this->actingAs($management)->get('/dashboard?from=2026-01-01&to=2026-01-31');
        $overview = $res->viewData('page')['props']['managementOverview'];

        $this->assertSame('2026-01-01', $overview['period']['from']);
        $this->assertSame('2026-01-31', $overview['period']['to']);
        $this->assertSame(2600000.0, $overview['revenue']['revenue']);

        // Di luar rentang tanggal itu, project ini tidak boleh ikut terhitung.
        $res2 = $this->actingAs($management)->get('/dashboard?from=2026-02-01&to=2026-02-28');
        $this->assertSame(0.0, $res2->viewData('page')['props']['managementOverview']['revenue']['revenue']);
    }

    /**
     * Backlog/status (mis. jumlah lead per stage) adalah kondisi SEKARANG dan harus tetap
     * utuh berapa pun periode yang dipilih — cuma angka "aktivitas" yang boleh berubah.
     */
    public function test_backlog_counts_are_not_affected_by_the_period_filter(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified'])->forceFill(['created_at' => '2020-01-01'])->save();

        $unfiltered = $this->actingAs($management)->get('/dashboard')
            ->viewData('page')['props']['managementOverview'];
        $filtered = $this->actingAs($management)->get('/dashboard?from=2026-01-01&to=2026-01-02')
            ->viewData('page')['props']['managementOverview'];

        $stageBefore = collect($unfiltered['sales']['leads_by_stage'])->firstWhere('label', 'Terkualifikasi')['count'];
        $stageAfter = collect($filtered['sales']['leads_by_stage'])->firstWhere('label', 'Terkualifikasi')['count'];
        $this->assertSame($stageBefore, $stageAfter);
        $this->assertGreaterThan(0, $stageAfter);
    }

    /**
     * "Aktivitas Periode Ini" (lead baru, quotation dibuat, dst) dihitung dari created_at
     * dalam rentang tanggal, dan dibandingkan dengan periode sebelumnya yang sama panjang.
     */
    public function test_activity_counts_leads_created_in_period_and_compares_to_previous_period(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);

        Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'new'])->forceFill(['created_at' => '2026-01-15'])->save();
        Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'new'])->forceFill(['created_at' => '2026-01-16'])->save();
        // Periode sebelumnya (Desember, panjang sama 31 hari) cuma 1 lead -> delta naik 100%.
        Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'new'])->forceFill(['created_at' => '2025-12-20'])->save();

        $overview = $this->actingAs($management)->get('/dashboard?from=2026-01-01&to=2026-01-31')
            ->viewData('page')['props']['managementOverview'];

        $this->assertSame(2, $overview['activity']['leads_created']);
        $this->assertSame(100.0, $overview['activity']['leads_created_delta_percent']);
    }

    public function test_non_management_cannot_be_shown_overview_even_if_role_changes(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->actingAs($procurement)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('managementOverview', null)
            ->has('procurementActions'));
    }
}
