<?php

namespace Tests\Feature\Survey;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyToSalesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Lead, Survey} */
    private function verifiedSurvey(bool $withItems = true): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'Jl. Q', 'site_region' => 'Kudus',
            'delivery_mode' => 'internal', 'billable' => false, 'cost' => 0, 'status' => 'verified',
        ]);
        $report = $survey->report()->create(['status' => 'verified', 'revision' => 1, 'summary' => 'ok']);
        if ($withItems) {
            $report->items()->create(['item_name' => 'Access Point', 'qty' => 4, 'unit' => 'unit', 'notes' => 'plafon']);
            $report->items()->create(['item_name' => 'Kabel', 'qty' => 200, 'unit' => 'meter']);
        }

        return [$sales, $lead, $survey];
    }

    public function test_sales_copies_report_items_to_requirements_and_closes(): void
    {
        [$sales, $lead, $survey] = $this->verifiedSurvey();

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/finalize", [
            'copy_items' => true,
        ])->assertRedirect("/sales/leads/{$lead->id}");

        $this->assertSame('closed', $survey->fresh()->status);
        $this->assertSame(2, $lead->requirements()->count());
        $this->assertDatabaseHas('requirements', [
            'lead_id' => $lead->id, 'item_name' => 'Access Point', 'qty' => '4.00', 'unit' => 'unit',
        ]);
    }

    public function test_close_without_copy_leaves_requirements_empty(): void
    {
        [$sales, $lead, $survey] = $this->verifiedSurvey();

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/finalize", [
            'copy_items' => false,
        ])->assertRedirect();

        $this->assertSame('closed', $survey->fresh()->status);
        $this->assertSame(0, $lead->requirements()->count());
    }

    public function test_copy_blocked_when_requirements_locked(): void
    {
        [$sales, $lead, $survey] = $this->verifiedSurvey();
        $lead->procurementRequests()->create(['status' => 'submitted', 'requested_by' => $sales->id]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/finalize", [
            'copy_items' => true,
        ])->assertSessionHasErrors('survey');

        $this->assertSame('verified', $survey->fresh()->status);
    }

    public function test_only_verified_survey_can_be_finalized(): void
    {
        [$sales, $lead, $survey] = $this->verifiedSurvey();
        $survey->update(['status' => 'in_progress']);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/finalize", [
            'copy_items' => false,
        ])->assertForbidden();
    }

    public function test_paid_survey_fee_is_credited_on_quotation_and_dp_invoice(): void
    {
        // Sales chain: opportunity -> requirement -> PR ready
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);

        // A paid survey invoice worth 500.000 for this lead
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'internal', 'billable' => true, 'cost' => 500000, 'status' => 'awaiting_payment',
        ]);
        $srv = Invoice::create([
            'number' => 'SRV-2026-0001', 'invoice_type' => 'survey', 'sales_order_id' => null,
            'survey_id' => $survey->id, 'invoice_phase' => null, 'status' => 'paid',
            'amount' => 500000, 'tax_amount' => 0,
        ]);

        // Quotation
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();
        $this->assertSame('500000.00', $quotation->survey_credit);

        // Confirm -> SO carries credit
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("mixed"));
        $so = \App\Models\SalesOrder::firstOrFail();
        $this->assertSame('500000.00', $so->survey_credit);

        // DP invoice: half of 2.6jt line = 1.3jt, minus half credit 250rb = 1.05jt
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = Invoice::where('invoice_phase', 'dp')->with('lines')->firstOrFail();
        $this->assertSame('1050000.00', $invoice->amount);
        $creditLine = $invoice->lines->firstWhere('item_name', 'Kredit Biaya Survey');
        $this->assertNotNull($creditLine);
        $this->assertSame('-250000.00', $creditLine->subtotal);
    }
}
