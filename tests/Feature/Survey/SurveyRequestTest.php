<?php

namespace Tests\Feature\Survey;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Survey;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyRequestTest extends TestCase
{
    use RefreshDatabase;

    private function opportunity(User $sales): Lead
    {
        $contact = Contact::create(['name' => 'Customer Survey', 'created_by' => $sales->id]);

        return Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'opportunity',
            'stage' => 'qualified',
        ]);
    }

    public function test_sales_requests_survey_and_procurement_is_notified(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $lead = $this->opportunity($sales);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys", [
            'site_address' => 'Jl. Proyek No. 1',
            'site_region' => 'Balikpapan',
            'delivery_mode' => 'vendor',
            'billable' => true,
            'notes' => 'Lokasi jauh',
        ])->assertRedirect("/sales/leads/{$lead->id}");

        $survey = Survey::firstOrFail();
        $this->assertSame('requested', $survey->status);
        $this->assertTrue($survey->billable);
        $this->assertMatchesRegularExpression('/^SVY-\d{6}$/', $survey->code);
        $this->assertSame('SVY-'.str_pad((string) $survey->id, 6, '0', STR_PAD_LEFT), $survey->code);
        $this->assertSame(1, Notification::where('type', 'survey.requested')->where('user_id', $procurement->id)->count());
    }

    public function test_only_opportunity_owner_can_request_survey(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        $lead = $this->opportunity($sales);

        $this->actingAs($other)->post("/sales/leads/{$lead->id}/surveys", [
            'site_address' => 'x', 'site_region' => 'y', 'delivery_mode' => 'internal', 'billable' => false,
        ])->assertForbidden();
    }

    public function test_cannot_request_second_survey_while_one_is_open(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $lead = $this->opportunity($sales);
        $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'b',
            'delivery_mode' => 'internal', 'billable' => false, 'status' => 'in_progress',
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys", [
            'site_address' => 'x', 'site_region' => 'y', 'delivery_mode' => 'internal', 'billable' => false,
        ])->assertSessionHasErrors('survey');
    }

    public function test_procurement_sources_internal_non_billable_survey_to_operational(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $operational = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $surveyor = User::factory()->create(['role' => 'technician', 'is_active' => true, 'vendor_id' => null]);
        $lead = $this->opportunity($sales);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'Surabaya',
            'delivery_mode' => 'internal', 'billable' => false, 'status' => 'requested',
        ]);

        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'cost' => 0,
        ])->assertRedirect();

        $survey->refresh();
        $this->assertSame('awaiting_briefing', $survey->status);
        $this->assertSame($procurement->id, $survey->sourced_by);
        $this->assertSame(1, Notification::where('type', 'survey.awaiting_briefing')->where('user_id', $operational->id)->count());
    }

    public function test_billable_survey_routes_to_finance(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $surveyor = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $lead = $this->opportunity($sales);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'Bekasi',
            'delivery_mode' => 'internal', 'billable' => true, 'status' => 'requested',
        ]);

        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'cost' => 500000,
        ])->assertRedirect();

        $this->assertSame('finance_review', $survey->fresh()->status);
        $this->assertSame(1, Notification::where('type', 'survey.finance_review')->where('user_id', $finance->id)->count());
    }

    public function test_vendor_survey_requires_vendor_selection(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_survey' => true]);
        $lead = $this->opportunity($sales);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'Papua',
            'delivery_mode' => 'vendor', 'billable' => true, 'status' => 'requested',
        ]);

        // tanpa vendor -> ditolak
        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'cost' => 1000000,
        ])->assertSessionHasErrors('vendor_id');

        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'vendor_id' => $vendor->id,
            'cost' => 1000000,
        ])->assertRedirect();

        $this->assertSame($vendor->id, $survey->fresh()->vendor_id);
    }

    public function test_procurement_can_adjust_vendor_and_cost_before_scheduling(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $lead = $this->opportunity($sales);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'Solo',
            'delivery_mode' => 'internal', 'billable' => false, 'status' => 'requested',
        ]);

        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'cost' => 0,
        ])->assertRedirect();
        $this->assertSame('awaiting_briefing', $survey->fresh()->status);

        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'cost' => 50000,
        ])->assertRedirect();

        $survey->refresh();
        $this->assertSame('awaiting_briefing', $survey->status);
        $this->assertSame('50000.00', $survey->cost);
    }

    public function test_cannot_reassign_after_operational_briefed(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $lead = $this->opportunity($sales);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'internal', 'billable' => false,
            'status' => 'in_progress',
        ]);
        $survey->surveyors()->attach($tech->id, ['is_leader' => true]);

        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'cost' => 0,
        ])->assertForbidden();
    }

    public function test_cannot_reassign_after_finance_issued_invoice(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $tech = User::factory()->create(['role' => 'technician', 'is_active' => true]);
        $lead = $this->opportunity($sales);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'internal', 'billable' => true, 'cost' => 300000,
            'status' => 'awaiting_payment',
        ]);
        $survey->surveyors()->attach($tech->id, ['is_leader' => true]);
        \App\Models\Invoice::create([
            'number' => 'SRV-2026-0001', 'invoice_type' => 'survey', 'sales_order_id' => null,
            'survey_id' => $survey->id, 'invoice_phase' => null, 'status' => 'sent',
            'amount' => 300000, 'tax_amount' => 0,
        ]);

        $this->actingAs($procurement)->post("/procurement/surveys/{$survey->id}/source", [
            'cost' => 300000,
        ])->assertForbidden();
    }

    public function test_non_procurement_cannot_source_or_view_inbox(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $lead = $this->opportunity($sales);
        $survey = $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'internal', 'billable' => false, 'status' => 'requested',
        ]);

        $this->actingAs($sales)->get('/procurement/surveys')->assertForbidden();
        $this->actingAs($sales)->get("/procurement/surveys/{$survey->id}")->assertForbidden();
    }
}
