<?php

namespace Tests\Feature\Survey;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Survey;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function financeReviewSurvey(bool $billable, string $mode = 'internal', ?int $vendorId = null): Survey
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);

        return $lead->surveys()->create([
            'requested_by' => $sales->id,
            'site_address' => 'Jl. A',
            'site_region' => 'Bandung',
            'delivery_mode' => $mode,
            'vendor_id' => $vendorId,
            'billable' => $billable,
            'cost' => 750000,
            'status' => 'finance_review',
        ]);
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    public function test_finance_issues_srv_invoice_without_tax(): void
    {
        $survey = $this->financeReviewSurvey(billable: true);

        $this->actingAs($this->finance())->post("/finance/surveys/{$survey->id}/invoice", [])
            ->assertRedirect("/finance/surveys/{$survey->id}");

        $invoice = Invoice::firstOrFail();
        $this->assertSame('survey', $invoice->invoice_type);
        $this->assertNull($invoice->sales_order_id);
        $this->assertSame($survey->id, $invoice->survey_id);
        $this->assertMatchesRegularExpression('/^SRV-\d{4}-0001$/', $invoice->number);
        $this->assertSame('750000.00', $invoice->amount);
        $this->assertSame('0.00', $invoice->tax_amount);

        // PDF invoice survey (tanpa grup Materials/Services) tetap render
        $this->actingAs($this->finance())->get("/finance/invoices/{$invoice->id}/pdf")->assertOk();
        $this->assertSame('awaiting_payment', $survey->fresh()->status);
        $this->assertSame(1, $invoice->lines()->count());
    }

    public function test_finance_can_apply_optional_ppn_to_survey_invoice(): void
    {
        $survey = $this->financeReviewSurvey(billable: true); // cost 750000
        $ppn = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);

        $this->actingAs($this->finance())->post("/finance/surveys/{$survey->id}/invoice", [
            'tax_id' => $ppn->id,
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('82500.00', $invoice->tax_amount);        // 750.000 * 11%
        $this->assertSame('750000.00', $invoice->amount);           // DPP tetap
        $this->assertEqualsWithDelta(832500.0, $invoice->grandTotal(), 0.01);
        $line = $invoice->lines->first();
        $this->assertSame($ppn->id, $line->tax_id);
        $this->assertSame('11.00', $line->tax_rate);
    }

    public function test_ppn_survey_needs_full_grand_total_before_advancing(): void
    {
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $survey = $this->financeReviewSurvey(billable: true);
        $ppn = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);
        $finance = $this->finance();
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", ['tax_id' => $ppn->id]);
        $invoice = Invoice::firstOrFail();

        // bayar DPP saja -> masih kurang PPN
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 750000, 'paid_at' => now()->toDateTimeString(),
        ]);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame('awaiting_payment', $survey->fresh()->status);

        // lunasi sisa PPN
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 82500, 'paid_at' => now()->toDateTimeString(),
        ]);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('awaiting_briefing', $survey->fresh()->status);
    }

    public function test_cancelling_survey_invoice_returns_survey_to_finance_review(): void
    {
        $survey = $this->financeReviewSurvey(billable: true);
        $finance = $this->finance();
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", []);
        $invoice = Invoice::firstOrFail();

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/cancel")->assertRedirect();

        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame('finance_review', $survey->fresh()->status);

        // bisa terbitkan ulang
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", [])->assertRedirect();
        $this->assertSame(2, Invoice::count());
        $this->assertSame('awaiting_payment', $survey->fresh()->status);
    }

    public function test_survey_invoice_paid_advances_to_operational(): void
    {
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $survey = $this->financeReviewSurvey(billable: true);
        $finance = $this->finance();

        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", []);
        $invoice = Invoice::firstOrFail();

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 750000,
            'paid_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('awaiting_briefing', $survey->fresh()->status);
        $this->assertSame(1, Notification::where('type', 'survey.awaiting_briefing')->where('user_id', $operational->id)->count());
    }

    public function test_partial_payment_keeps_survey_awaiting_payment(): void
    {
        $survey = $this->financeReviewSurvey(billable: true);
        $finance = $this->finance();
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", []);
        $invoice = Invoice::firstOrFail();

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 300000,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame('awaiting_payment', $survey->fresh()->status);
    }

    public function test_non_billable_vendor_survey_cleared_without_invoice(): void
    {
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'V', 'provides_survey' => true]);
        $survey = $this->financeReviewSurvey(billable: false, mode: 'vendor', vendorId: $vendor->id);

        $this->actingAs($this->finance())->post("/finance/surveys/{$survey->id}/clear", [
            'finance_note' => 'Dibayar cash ke vendor',
        ])->assertRedirect('/finance/surveys');

        $survey->refresh();
        $this->assertSame('awaiting_briefing', $survey->status);
        $this->assertSame('Dibayar cash ke vendor', $survey->finance_note);
        $this->assertSame(0, Invoice::count());
        $this->assertSame(1, Notification::where('type', 'survey.awaiting_briefing')->where('user_id', $operational->id)->count());
    }

    public function test_finance_can_void_partially_paid_survey_invoice_to_unstick_it(): void
    {
        $survey = $this->financeReviewSurvey(billable: true); // cost 750000
        $finance = $this->finance();
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", []);
        $invoice = Invoice::firstOrFail();

        // customer bayar sebagian lalu berhenti
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 300000, 'paid_at' => now()->toDateTimeString(),
        ]);
        $this->assertSame('partially_paid', $invoice->fresh()->status);

        // Finance void invoice meski ada pembayaran
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/cancel")->assertRedirect();

        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame('finance_review', $survey->fresh()->status);
        // pembayaran tetap jadi riwayat
        $this->assertSame(1, $invoice->payments()->count());

        // survey sekarang bisa dibatalkan Sales
        $sales = $survey->lead->sales_id
            ? \App\Models\User::find($survey->lead->sales_id)
            : \App\Models\User::where('role', 'sales')->first();
        $this->actingAs($sales)->post("/sales/leads/{$survey->lead_id}/surveys/{$survey->id}/cancel", [
            'reason' => 'Customer batal',
        ])->assertRedirect();
        $this->assertSame('cancelled', $survey->fresh()->status);
    }

    public function test_cannot_void_fully_paid_survey_invoice(): void
    {
        $survey = $this->financeReviewSurvey(billable: true);
        $finance = $this->finance();
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", []);
        $invoice = Invoice::firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 750000, 'paid_at' => now()->toDateTimeString(),
        ]);

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/cancel")->assertForbidden();
    }

    public function test_cannot_issue_invoice_for_non_billable_survey(): void
    {
        $survey = $this->financeReviewSurvey(billable: false, mode: 'vendor');

        $this->actingAs($this->finance())->post("/finance/surveys/{$survey->id}/invoice", [])
            ->assertSessionHasErrors('survey');
        $this->assertSame(0, Invoice::count());
    }

    public function test_survey_invoices_excluded_from_sales_invoice_lists(): void
    {
        $survey = $this->financeReviewSurvey(billable: true);
        $finance = $this->finance();
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", []);

        $this->actingAs($finance)->get('/finance/invoices?')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('invoices.data', []));
    }

    public function test_non_finance_cannot_access_finance_survey_inbox(): void
    {
        $survey = $this->financeReviewSurvey(billable: true);
        $proc = User::factory()->create(['role' => 'procurement']);

        $this->actingAs($proc)->get('/finance/surveys')->assertForbidden();
        $this->actingAs($proc)->post("/finance/surveys/{$survey->id}/invoice", [])->assertForbidden();
    }
}
