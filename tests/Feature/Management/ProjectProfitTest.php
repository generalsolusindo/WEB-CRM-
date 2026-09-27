<?php

namespace Tests\Feature\Management;

use App\Models\ActualProcurement;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectProfitTest extends TestCase
{
    use RefreshDatabase;

    private function management(): User
    {
        return User::factory()->create(['role' => 'management', 'is_active' => true]);
    }

    public function test_management_can_view_project_profit_report_with_correct_totals(): void
    {
        $project = $this->plannedProject();

        $response = $this->actingAs($this->management())->get('/management/project-profit');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Management/ProjectProfit/Index')
            ->where('projects.total', 1)
            // 2 unit Router @ cost 1.000.000 (estimasi awal, belum ada sourcing) = HPP 2.000.000
            // harga jual (DPP SO) = 2 x 1.300.000 = 2.600.000
            // profit = 600.000
            ->where('projects.data.0.hpp', 2000000)
            ->where('projects.data.0.harga_jual', 2600000)
            ->where('projects.data.0.profit', 600000)
            ->where('summary.total_hpp', 2000000)
            ->where('summary.total_harga_jual', 2600000)
            ->where('summary.total_profit', 600000)
            ->where('summary.count', 1));
    }

    public function test_hpp_reflects_actual_procurement_cost_once_sourced(): void
    {
        $project = $this->plannedProject();

        // Procurement menemukan harga beli sungguhan lebih murah dari estimasi.
        ActualProcurement::where('project_id', $project->id)->update(['cost_price' => 800000]);

        $response = $this->actingAs($this->management())->get('/management/project-profit');

        $response->assertInertia(fn ($page) => $page
            // HPP sekarang 2 x 800.000 = 1.600.000 (bukan lagi estimasi 2.000.000)
            ->where('projects.data.0.hpp', 1600000)
            ->where('projects.data.0.profit', 1000000));
    }

    public function test_vendor_deal_fee_replaces_service_cost_estimate_in_hpp(): void
    {
        $project = $this->plannedProject();
        $vendor = Vendor::create(['name' => 'Vendor Jasa', 'provides_technical' => true]);
        $project->vendorServicePayment()->create([
            'number' => '1/GS-VP/09/2026', 'vendor_id' => $vendor->id, 'total_fee' => 500000, 'terms' => 'pay_at_end',
            'bank_name' => 'BCA', 'account_number' => '1', 'account_holder' => 'x', 'status' => 'in_progress', 'released_at' => now(),
        ]);

        // Material 2 x 1.000.000 = 2.000.000, ditambah fee vendor 500.000 = HPP 2.500.000.
        $this->actingAs($this->management())->get('/management/project-profit')
            ->assertInertia(fn ($page) => $page
                ->where('projects.data.0.hpp', 2500000)
                ->where('projects.data.0.uses_vendor_fee', true)
                ->where('projects.data.0.profit', 100000)
                ->where('summary.total_profit', 100000));
    }

    public function test_project_without_vendor_deal_keeps_estimated_service_cost(): void
    {
        $this->plannedProject();

        $this->actingAs($this->management())->get('/management/project-profit')
            ->assertInertia(fn ($page) => $page->where('projects.data.0.uses_vendor_fee', false)->where('projects.data.0.hpp', 2000000));
    }

    /**
     * Regresi untuk pertanyaan "diskon dihitung dua kali atau tidak": Harga Jual di laporan
     * ini SUDAH bersih dari diskon (diambil dari `subtotal`, bukan `selling_price x qty`
     * mentah), jadi rumus Profit = Harga Jual - HPP TIDAK boleh mengurangi diskon lagi
     * secara terpisah. HPP sendiri tetap dari cost_price mentah (belum ada sourcing aktual),
     * tidak ikut terpotong diskon — diskon murni urusan sisi harga jual ke customer.
     */
    public function test_discount_is_reflected_once_in_harga_jual_not_subtracted_again_from_profit(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust Diskon', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->latest('id')->firstOrFail();
        // Cost dari Procurement (dasar HPP): 1.000.000/unit x 2 = HPP 2.000.000.
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        // Sales pasang harga jual 1.300.000/unit, TAPI kasih diskon 20% saat quotation.
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $pr->lines()->first()->id,
                'selling_price' => 1300000,
                'discount_percent' => 20,
            ]],
        ]);
        $quotation = Quotation::latest('id')->firstOrFail();
        // Harga jual per unit setelah diskon 20% = 1.040.000 -> subtotal 2 unit = 2.080.000.
        $this->assertSame('2080000.00', $quotation->lines()->first()->subtotal);
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
        $project->salesOrder->update(['status' => 'won']);

        $response = $this->actingAs($this->management())->get('/management/project-profit');
        $response->assertInertia(fn ($page) => $page
            // Harga Jual = subtotal yang SUDAH bersih diskon (2.080.000), bukan 2.600.000 (harga sebelum diskon).
            ->where('projects.data.0.harga_jual', 2080000)
            // HPP tetap dari cost_price mentah, tidak ikut didiskon.
            ->where('projects.data.0.hpp', 2000000)
            // Profit = 2.080.000 - 2.000.000 = 80.000 — BUKAN 2.080.000 - 2.000.000 - diskon lagi.
            ->where('projects.data.0.profit', 80000));

        // Dashboard (kartu Pendapatan & Profit) harus persis sama, karena satu rumus yang sama.
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $dash = $this->actingAs($this->management())->get("/dashboard?from={$monthStart}&to={$monthEnd}")
            ->viewData('page')['props']['managementOverview']['revenue'];
        $this->assertSame(2080000.0, $dash['revenue']);
        $this->assertSame(80000.0, $dash['profit']);
    }

    public function test_can_filter_by_won_scope(): void
    {
        $project = $this->plannedProject();
        $project->salesOrder->update(['status' => 'won']);

        $otherProject = $this->plannedProject();

        $management = $this->management();

        $this->actingAs($management)->get('/management/project-profit?scope=won')
            ->assertInertia(fn ($page) => $page
                ->where('projects.total', 1)
                ->where('projects.data.0.id', $project->id));

        $this->actingAs($management)->get('/management/project-profit?scope=running')
            ->assertInertia(fn ($page) => $page
                ->where('projects.total', 1)
                ->where('projects.data.0.id', $otherProject->id));

        $this->actingAs($management)->get('/management/project-profit')
            ->assertInertia(fn ($page) => $page->where('projects.total', 2));
    }

    public function test_can_filter_by_date_range(): void
    {
        $project = $this->plannedProject();
        $project->forceFill(['created_at' => now()->subDays(10)])->save();

        $management = $this->management();

        $this->actingAs($management)->get('/management/project-profit?from='.now()->subDay()->toDateString())
            ->assertInertia(fn ($page) => $page->where('projects.total', 0));

        $this->actingAs($management)
            ->get('/management/project-profit?from='.now()->subDays(15)->toDateString().'&to='.now()->subDays(5)->toDateString())
            ->assertInertia(fn ($page) => $page->where('projects.total', 1));
    }

    public function test_cancelled_sales_order_project_is_excluded(): void
    {
        $project = $this->plannedProject();
        $project->salesOrder->update(['status' => 'cancelled']);

        $this->actingAs($this->management())->get('/management/project-profit')
            ->assertInertia(fn ($page) => $page->where('projects.total', 0));
    }

    public function test_non_management_role_cannot_view_project_profit_report(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)->get('/management/project-profit')->assertForbidden();
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
