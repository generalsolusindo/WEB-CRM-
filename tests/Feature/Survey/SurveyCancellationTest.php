<?php

namespace Tests\Feature\Survey;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyCancellationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Lead} */
    private function opportunity(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);

        return [$sales, $lead];
    }

    private function survey(Lead $lead, User $sales, string $status, array $extra = []): Survey
    {
        return $lead->surveys()->create(array_merge([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'internal', 'billable' => false, 'cost' => 0, 'status' => $status,
        ], $extra));
    }

    public function test_sales_cancels_early_survey_and_procurement_notified(): void
    {
        [$sales, $lead] = $this->opportunity();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $survey = $this->survey($lead, $sales, 'requested');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/cancel", [
            'reason' => 'Customer mundur',
        ])->assertRedirect("/sales/leads/{$lead->id}");

        $survey->refresh();
        $this->assertSame('cancelled', $survey->status);
        $this->assertSame('Customer mundur', $survey->cancel_reason);
        $this->assertSame($sales->id, $survey->cancelled_by);
        $this->assertSame(1, Notification::where('type', 'survey.cancelled')->where('user_id', $procurement->id)->count());
    }

    public function test_cancelling_clears_stale_pending_notifications(): void
    {
        [$sales, $lead] = $this->opportunity();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $survey = $this->survey($lead, $sales, 'finance_review', ['billable' => true, 'cost' => 200000]);

        // notifikasi "menunggu Finance" seperti yang dibuat SourceSurvey
        Notification::create([
            'user_id' => $finance->id, 'type' => 'survey.finance_review', 'message' => 'x',
            'related_type' => $survey->getMorphClass(), 'related_id' => $survey->id, 'is_sent' => true,
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/cancel", []);

        $this->assertSame(0, Notification::where('type', 'survey.finance_review')->where('related_id', $survey->id)->count());
        $this->assertTrue(Notification::where('type', 'survey.cancelled')->where('related_id', $survey->id)->exists());
    }

    public function test_cancelling_survey_voids_unpaid_srv_invoice(): void
    {
        [$sales, $lead] = $this->opportunity();
        $survey = $this->survey($lead, $sales, 'awaiting_payment', ['billable' => true, 'cost' => 400000]);
        $invoice = Invoice::create([
            'number' => 'SRV-2026-0001', 'invoice_type' => 'survey', 'sales_order_id' => null,
            'survey_id' => $survey->id, 'invoice_phase' => null, 'status' => 'sent',
            'amount' => 400000, 'tax_amount' => 0,
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/cancel", [])
            ->assertRedirect();

        $this->assertSame('cancelled', $survey->fresh()->status);
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_cannot_cancel_when_srv_invoice_has_payment(): void
    {
        [$sales, $lead] = $this->opportunity();
        $survey = $this->survey($lead, $sales, 'awaiting_payment', ['billable' => true, 'cost' => 400000]);
        $invoice = Invoice::create([
            'number' => 'SRV-2026-0001', 'invoice_type' => 'survey', 'sales_order_id' => null,
            'survey_id' => $survey->id, 'invoice_phase' => null, 'status' => 'partially_paid',
            'amount' => 400000, 'tax_amount' => 0,
        ]);
        Payment::create(['invoice_id' => $invoice->id, 'amount_paid' => 100000, 'paid_at' => now()]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/cancel", [])
            ->assertSessionHasErrors('survey');

        $this->assertSame('awaiting_payment', $survey->fresh()->status);
    }

    public function test_sales_cannot_cancel_once_in_progress_but_operational_can(): void
    {
        [$sales, $lead] = $this->opportunity();
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $survey = $this->survey($lead, $sales, 'in_progress');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys/{$survey->id}/cancel", [])
            ->assertForbidden();

        $this->actingAs($ops)->post("/operational/surveys/{$survey->id}/cancel", ['reason' => 'Surveyor mangkir'])
            ->assertRedirect('/operational/surveys');

        $this->assertSame('cancelled', $survey->fresh()->status);
    }

    public function test_cannot_cancel_verified_or_closed_survey(): void
    {
        [$sales, $lead] = $this->opportunity();
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $verified = $this->survey($lead, $sales, 'verified');

        $this->actingAs($ops)->post("/operational/surveys/{$verified->id}/cancel", [])->assertForbidden();
    }

    public function test_cancelled_survey_does_not_block_new_request(): void
    {
        [$sales, $lead] = $this->opportunity();
        $this->survey($lead, $sales, 'cancelled');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys", [
            'site_address' => 'baru', 'site_region' => 'y', 'delivery_mode' => 'internal', 'billable' => false,
        ])->assertRedirect();

        $this->assertSame(2, $lead->surveys()->count());
    }
}
