<?php

namespace Tests\Feature\Procurement;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementRequestFinalizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_is_rejected_when_a_line_is_not_available(): void
    {
        [$procurement, $pr, $sales] = $this->submittedRequest();
        $pr->lines()->update(['cost_price' => 1000, 'availability_status' => 'searching']);

        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/ready")
            ->assertSessionHasErrors('lines');
        $this->assertSame('submitted', $pr->fresh()->status);
    }

    public function test_ready_is_rejected_when_a_line_has_zero_cost(): void
    {
        [$procurement, $pr] = $this->submittedRequest();
        $pr->lines()->update(['cost_price' => 0, 'availability_status' => 'available']);

        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/ready")
            ->assertSessionHasErrors('lines');
    }

    public function test_ready_succeeds_and_notifies_sales_once(): void
    {
        [$procurement, $pr, $sales] = $this->submittedRequest();
        $pr->lines()->update(['cost_price' => 1200000, 'availability_status' => 'available']);

        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/ready")
            ->assertSessionHas('success');

        $this->assertSame('ready', $pr->fresh()->status);
        $this->assertSame(1, Notification::where('user_id', $sales->id)
            ->where('type', 'procurement_request.ready')->count());

        // Sudah ready -> tidak bisa difinalisasi lagi.
        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/ready")
            ->assertForbidden();
    }

    public function test_reject_returns_stage_unlocks_requirement_and_allows_resubmit(): void
    {
        [$procurement, $pr, $sales, $lead] = $this->submittedRequest();
        $this->assertSame('procurement', $lead->fresh()->stage);

        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/reject", [
            'rejection_reason' => 'Spesifikasi router tidak jelas.',
        ])->assertRedirect('/procurement/procurement-requests');

        $pr->refresh();
        $this->assertSame('rejected', $pr->status);
        $this->assertSame('Spesifikasi router tidak jelas.', $pr->rejection_reason);
        $this->assertSame('requirement', $lead->fresh()->stage);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $sales->id,
            'type' => 'procurement_request.rejected',
        ]);

        // Sales bisa edit requirement lagi.
        $requirement = $lead->requirements()->firstOrFail();
        $this->actingAs($sales)->put("/sales/leads/{$lead->id}/requirements/{$requirement->id}", [
            'item_name' => 'Router Dual-WAN (revisi)', 'qty' => 2, 'unit' => 'set',
        ])->assertSessionHas('success');

        // Submit ulang -> PR BARU, PR lama tetap ada.
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement")
            ->assertSessionHas('success');
        $this->assertSame(2, ProcurementRequest::where('lead_id', $lead->id)->count());
        $this->assertSame('procurement', $lead->fresh()->stage);
        $this->assertSame('submitted', $lead->latestProcurementRequest->status);
    }

    public function test_reject_requires_a_reason(): void
    {
        [$procurement, $pr] = $this->submittedRequest();

        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/reject", [
            'rejection_reason' => '',
        ])->assertSessionHasErrors('rejection_reason');
    }

    public function test_dashboard_lists_procurement_needs_action(): void
    {
        [$procurement, $pr] = $this->submittedRequest();
        [, $pr2] = $this->submittedRequest();
        $pr2->update(['status' => 'searching']);

        $this->actingAs($procurement)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('procurementActions.submitted.0.href', "/procurement/procurement-requests/{$pr->id}")
            ->where('procurementActions.searching.0.href', "/procurement/procurement-requests/{$pr2->id}"));
    }

    /** @return array{User, ProcurementRequest, User, Lead} */
    private function submittedRequest(): array
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create([
            'item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id,
        ]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        return [$procurement, ProcurementRequest::with('lines')->where('lead_id', $lead->id)->firstOrFail(), $sales, $lead];
    }
}
