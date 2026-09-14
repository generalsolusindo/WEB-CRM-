<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function management(): User
    {
        return User::factory()->create(['role' => 'management', 'is_active' => true]);
    }

    public function test_management_can_view_procurement_requests_list(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $this->actingAs($this->management())->get('/management/procurement-requests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Management/ProcurementRequests/Index')
                ->where('requests.total', 1));
    }

    public function test_procurement_role_still_cannot_view_management_only_pages(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        // Halaman procurement asli tetap aman (role-gated), tapi rute management
        // tidak bisa diakses procurement karena middleware role:management.
        $this->actingAs($procurement)->get('/management/procurement-requests')->assertForbidden();
    }

    public function test_management_can_view_invoices_list(): void
    {
        $so = $this->confirmedSalesOrder();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);

        $this->actingAs($this->management())->get('/management/invoices')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Management/Invoices/Index')
                ->where('invoices.total', 1));
    }

    public function test_finance_role_cannot_reach_management_invoice_route(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->get('/management/invoices')->assertForbidden();
    }

    public function test_management_can_view_invoice_pdf(): void
    {
        $so = $this->confirmedSalesOrder();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);
        $invoice = \App\Models\Invoice::latest('id')->firstOrFail();

        $this->actingAs($this->management())->get("/finance/invoices/{$invoice->id}/pdf")->assertOk();
    }

    public function test_management_can_view_all_quotations_tracking_and_pdf(): void
    {
        $so = $this->confirmedSalesOrder();
        $quotation = $so->quotation;

        $this->actingAs($this->management())->get('/management/quotations-overview')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Management/Quotations/Index')
                ->where('quotations.total', 1));

        $this->actingAs($this->management())->get("/sales/quotations/{$quotation->id}/print")->assertOk();
    }

    public function test_sales_role_cannot_reach_management_quotation_overview_route(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)->get('/management/quotations-overview')->assertForbidden();
    }

    public function test_management_can_view_surveys_list(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys", [
            'site_address' => 'Jl. Uji Coba No. 1',
            'site_region' => 'Sidoarjo',
            'delivery_mode' => 'vendor',
            'billable' => true,
        ]);

        $this->actingAs($this->management())->get('/management/surveys')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Management/Surveys/Index')
                ->where('surveys.total', 1));
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
