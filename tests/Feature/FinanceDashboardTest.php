<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedSalesOrder(): SalesOrder
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
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));

        return SalesOrder::firstOrFail();
    }

    public function test_confirmed_so_shows_in_finance_dashboard_needs_upfront_invoice(): void
    {
        $so = $this->confirmedSalesOrder();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('financeActions.needs_upfront_invoice', fn ($items) => collect($items)->contains(
                    fn ($i) => str_contains($i['label'], $so->number),
                )));
    }

    public function test_finance_dashboard_survives_unpaid_survey_invoice(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'C', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'internal', 'billable' => true, 'cost' => 300000, 'status' => 'awaiting_payment',
        ]);
        Invoice::create([
            'number' => 'SRV-2026-0001', 'invoice_type' => 'survey', 'sales_order_id' => null,
            'survey_id' => $survey->id, 'invoice_phase' => null, 'status' => 'sent',
            'amount' => 300000, 'tax_amount' => 0, 'due_date' => now()->subDay(),
        ]);

        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('financeActions.unpaid_sent', [])
                ->where('financeActions.overdue', []));
    }
}
