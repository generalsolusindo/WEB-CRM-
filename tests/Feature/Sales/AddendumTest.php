<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Requirement;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddendumTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Lead, 2: SalesOrder} */
    private function confirmedLeadWithSalesOrder(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
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

        $so = SalesOrder::where('quotation_id', $quotation->id)->firstOrFail();

        return [$sales, $lead->fresh(), $so];
    }

    public function test_cannot_submit_addendum_without_active_sales_order(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-addendum")
            ->assertSessionHasErrors('lead');
    }

    public function test_sales_submits_addendum_without_creating_new_lead(): void
    {
        [$sales, $lead, $so] = $this->confirmedLeadWithSalesOrder();
        $originalRequirementId = $lead->requirements()->firstOrFail()->id;

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            'item_name' => 'Switch Tambahan', 'category' => 'material', 'qty' => 2, 'unit' => 'set',
        ])->assertSessionHas('success');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-addendum")
            ->assertRedirect(route('sales.leads.show', $lead));

        // Tidak ada Lead baru — masih Lead yang sama, cuma dapat PR kedua.
        $this->assertSame(1, Lead::count());

        $addendumPr = ProcurementRequest::where('is_addendum', true)->firstOrFail();
        $this->assertSame($lead->id, $addendumPr->lead_id);
        $this->assertSame($so->id, $addendumPr->addendum_of_sales_order_id);

        // Cuma requirement baru yang ikut, requirement lama tidak disertakan ulang.
        $this->assertCount(1, $addendumPr->lines);
        $this->assertSame('Switch Tambahan', $addendumPr->lines->first()->item_name);

        // PR original tidak berubah/kesenggol.
        $originalPr = ProcurementRequest::where('is_addendum', false)->firstOrFail();
        $this->assertCount(1, $originalPr->lines);

        // Requirement lama tetap 'submitted_at' terisi (tidak berubah), requirement baru sekarang ikut terisi.
        $this->assertNotNull(Requirement::find($originalRequirementId)->submitted_at);
        $this->assertNotNull($addendumPr->lines->first()->requirement->submitted_at);
    }

    public function test_quotation_created_from_addendum_request_is_flagged_and_reviewed_normally(): void
    {
        [$sales, $lead, $so] = $this->confirmedLeadWithSalesOrder();
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $lead->update(['delegated_to' => $pm->id, 'delegated_by' => $manager->id, 'delegated_at' => now()]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            'item_name' => 'Switch Tambahan', 'category' => 'material', 'qty' => 1, 'unit' => 'set',
        ]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-addendum");

        $addendumPr = ProcurementRequest::where('is_addendum', true)->firstOrFail();
        $addendumPr->lines()->update(['cost_price' => 200000, 'availability_status' => 'available']);
        $addendumPr->update(['status' => 'ready']);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$addendumPr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $addendumPr->lines()->first()->id, 'selling_price' => 300000]],
        ])->assertRedirect();

        $quotation = Quotation::where('procurement_request_id', $addendumPr->id)->firstOrFail();
        $this->assertTrue((bool) $quotation->is_addendum);
        $this->assertSame($lead->id, $quotation->lead_id);

        // Tetap lewat alur review PM & Manager yang sama seperti quotation biasa.
        $this->actingAs($pm)->post("/project-manager/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertRedirect();
        $this->assertSame('approved', $quotation->fresh()->pm_review_status);

        $this->actingAs($manager)->post("/management/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertRedirect();
        $this->assertSame('approved', $quotation->fresh()->manager_review_status);
    }

    public function test_cannot_submit_addendum_with_no_new_requirements(): void
    {
        [$sales, $lead] = $this->confirmedLeadWithSalesOrder();

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-addendum")
            ->assertSessionHasErrors('requirements');
    }

    public function test_other_sales_cannot_submit_addendum_for_someone_elses_lead(): void
    {
        [, $lead] = $this->confirmedLeadWithSalesOrder();
        $otherSales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($otherSales)->post("/sales/leads/{$lead->id}/submit-addendum")
            ->assertForbidden();
    }

    public function test_new_requirement_can_be_edited_and_deleted_before_addendum_submission(): void
    {
        [$sales, $lead] = $this->confirmedLeadWithSalesOrder();

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            'item_name' => 'Item Baru', 'category' => 'material', 'qty' => 1, 'unit' => 'set',
        ]);
        $newRequirement = $lead->requirements()->where('item_name', 'Item Baru')->firstOrFail();

        $this->actingAs($sales)->put("/sales/leads/{$lead->id}/requirements/{$newRequirement->id}", [
            'item_name' => 'Item Baru (revisi)', 'category' => 'material', 'qty' => 2, 'unit' => 'set',
        ])->assertSessionHas('success');

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}/requirements/{$newRequirement->id}")
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('requirements', ['id' => $newRequirement->id]);
    }

    public function test_already_submitted_requirement_cannot_be_edited_or_deleted(): void
    {
        [$sales, $lead] = $this->confirmedLeadWithSalesOrder();
        $oldRequirement = $lead->requirements()->firstOrFail();

        $this->actingAs($sales)->put("/sales/leads/{$lead->id}/requirements/{$oldRequirement->id}", [
            'item_name' => 'Coba ubah', 'category' => 'material', 'qty' => 1, 'unit' => 'set',
        ])->assertForbidden();

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}/requirements/{$oldRequirement->id}")
            ->assertForbidden();
    }
}
