<?php

namespace Tests\Feature\Management;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function draftQuotation(?User $pm): array
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);

        if ($pm) {
            $this->actingAs($management)->put("/management/opportunities/{$lead->id}/delegate", ['project_manager_id' => $pm->id]);
        }

        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();

        return [$quotation, $sales, $management];
    }

    public function test_sales_cannot_send_quotation_without_review(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$quotation, $sales] = $this->draftQuotation($pm);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/send")->assertForbidden();
    }

    public function test_manager_cannot_review_before_pm(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$quotation, , $management] = $this->draftQuotation($pm);

        $this->actingAs($management)->post("/management/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertForbidden();
    }

    public function test_full_two_stage_approval_unlocks_send(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$quotation, $sales, $management] = $this->draftQuotation($pm);

        $this->actingAs($pm)->post("/project-manager/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertRedirect();
        $quotation->refresh();
        $this->assertSame('approved', $quotation->pm_review_status);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/send")->assertForbidden();

        $this->actingAs($management)->post("/management/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertRedirect();
        $quotation->refresh();
        $this->assertSame('approved', $quotation->manager_review_status);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/send")->assertRedirect();
        $this->assertSame('sent', $quotation->fresh()->status);
    }

    public function test_pm_rejection_returns_to_sales_and_editing_resets_both_reviews(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$quotation, $sales] = $this->draftQuotation($pm);

        $this->actingAs($pm)->post("/project-manager/quotations/{$quotation->id}/review", [
            'approved' => false,
            'notes' => 'Harga kurang kompetitif',
        ])->assertRedirect();
        $quotation->refresh();
        $this->assertSame('rejected', $quotation->pm_review_status);

        $line = $quotation->lines()->first();
        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}", [
            'lines' => [['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1250000]],
        ])->assertRedirect();

        $quotation->refresh();
        $this->assertNull($quotation->pm_review_status);
        $this->assertNull($quotation->manager_review_status);
    }

    public function test_revision_notifies_pm_and_requires_review_again(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$quotation, $sales, $management] = $this->draftQuotation($pm);

        $this->actingAs($pm)->post("/project-manager/quotations/{$quotation->id}/review", ['approved' => true]);
        $this->actingAs($management)->post("/management/quotations/{$quotation->id}/review", ['approved' => true]);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/send")->assertRedirect();

        $quotation->update(['status' => 'rejected']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/revisions")->assertRedirect();

        $revision = \App\Models\Quotation::where('parent_quotation_id', $quotation->id)->firstOrFail();
        $this->assertNull($revision->pm_review_status);

        $notification = \App\Models\Notification::where('user_id', $pm->id)
            ->where('type', 'quotation.pending_pm_review')
            ->where('related_id', $revision->id)
            ->first();
        $this->assertNotNull($notification, 'PM harus mendapat notifikasi untuk revisi quotation baru.');

        $this->actingAs($sales)->post("/sales/quotations/{$revision->id}/send")->assertForbidden();
    }

    public function test_pm_cannot_review_opportunity_not_delegated_to_them(): void
    {
        $pmA = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $pmB = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$quotation] = $this->draftQuotation($pmA);

        $this->actingAs($pmB)->post("/project-manager/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertForbidden();
    }

    /**
     * Harga beli & margin itu domain Manager (bisnis/pricing), bukan Project Manager
     * (kelayakan teknis/pengiriman) — jadi cost_price tidak boleh ikut terkirim sama
     * sekali ke payload halaman PM, bukan cuma disembunyikan di tampilan.
     */
    public function test_project_manager_does_not_receive_cost_price_or_margin_in_payload(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$quotation] = $this->draftQuotation($pm);

        $this->actingAs($pm)->get("/project-manager/quotations/{$quotation->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotations/Review/Show')
                ->where('quotation.lines.0.cost_price', null)
                ->where('quotation.totals.margin_amount', null)
                ->where('quotation.totals.margin_percent', null));
    }

    public function test_manager_does_receive_cost_price_and_margin_in_payload(): void
    {
        [$quotation, , $management] = $this->draftQuotation(null);
        $quotation->update(['pm_review_status' => 'approved']);

        $this->actingAs($management)->get("/management/quotations/{$quotation->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotations/Review/Show')
                ->where('quotation.lines.0.cost_price', '1000000.00')
                ->where('quotation.totals.margin_amount', 600000)
                ->where('quotation.totals.margin_percent', 30));
    }
}
