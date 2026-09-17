<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequirementHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirement_can_only_be_added_to_owned_opportunity(): void
    {
        [$sales, $lead] = $this->makeLead('lead');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", $this->requirementData())
            ->assertSessionHas('error');

        $lead->update(['type' => 'opportunity', 'stage' => 'qualified']);
        $other = User::factory()->create(['role' => 'sales']);

        $this->actingAs($other)->post("/sales/leads/{$lead->id}/requirements", $this->requirementData())
            ->assertForbidden();

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            ...$this->requirementData(),
            'created_by' => $other->id,
        ])->assertSessionHas('success');

        $requirement = Requirement::firstOrFail();
        $this->assertSame($sales->id, $requirement->created_by);
    }

    public function test_requirement_requires_positive_qty_and_valid_fields(): void
    {
        [$sales, $lead] = $this->makeLead('opportunity');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            'item_name' => '',
            'qty' => 0,
            'unit' => '',
        ])->assertSessionHasErrors(['item_name', 'qty', 'unit']);
    }

    public function test_owner_can_update_and_delete_requirement_before_handoff(): void
    {
        [$sales, $lead] = $this->makeLead('opportunity');
        $requirement = $lead->requirements()->create([
            ...$this->requirementData(),
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->put(
            "/sales/leads/{$lead->id}/requirements/{$requirement->id}",
            [...$this->requirementData(), 'item_name' => 'Updated Item'],
        )->assertSessionHas('success');

        $this->assertDatabaseHas('requirements', ['id' => $requirement->id, 'item_name' => 'Updated Item']);

        $this->actingAs($sales)->delete(
            "/sales/leads/{$lead->id}/requirements/{$requirement->id}",
        )->assertSessionHas('success');

        $this->assertDatabaseMissing('requirements', ['id' => $requirement->id]);
    }

    public function test_handoff_requires_at_least_one_requirement(): void
    {
        [$sales, $lead] = $this->makeLead('opportunity');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement")
            ->assertSessionHasErrors('requirements');

        $this->assertDatabaseCount('procurement_requests', 0);
    }

    public function test_handoff_copies_all_requirements_and_updates_lead_atomically(): void
    {
        [$sales, $lead] = $this->makeLead('opportunity');
        $first = $lead->requirements()->create([
            ...$this->requirementData(),
            'created_by' => $sales->id,
        ]);
        $second = $lead->requirements()->create([
            ...$this->requirementData(),
            'item_name' => 'Jasa Instalasi',
            'qty' => 2.5,
            'unit' => 'hari',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement")
            ->assertRedirectToRoute('sales.leads.show', $lead);

        $request = ProcurementRequest::with('lines')->firstOrFail();
        $this->assertSame('submitted', $request->status);
        $this->assertSame($sales->id, $request->requested_by);
        $this->assertCount(2, $request->lines);
        $this->assertDatabaseHas('procurement_request_lines', [
            'procurement_request_id' => $request->id,
            'requirement_id' => $first->id,
            'item_name' => $first->item_name,
            'qty' => $first->qty,
            'unit' => $first->unit,
            'cost_price' => 0,
            'availability_status' => 'searching',
        ]);
        $this->assertDatabaseHas('procurement_request_lines', [
            'requirement_id' => $second->id,
            'item_name' => 'Jasa Instalasi',
        ]);
        $this->assertSame('procurement', $lead->fresh()->stage);
    }

    public function test_handoff_is_not_duplicated_and_locks_requirements_and_lead(): void
    {
        [$sales, $lead] = $this->makeLead('opportunity');
        $requirement = $lead->requirements()->create([
            ...$this->requirementData(),
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement")
            ->assertSessionHasErrors('lead');

        $this->assertDatabaseCount('procurement_requests', 1);
        $this->assertDatabaseCount('procurement_request_lines', 1);

        $this->actingAs($sales)->put(
            "/sales/leads/{$lead->id}/requirements/{$requirement->id}",
            [...$this->requirementData(), 'item_name' => 'Tampered'],
        )->assertForbidden();
        $this->actingAs($sales)->get("/sales/leads/{$lead->id}/edit")->assertForbidden();
    }

    public function test_cost_price_is_hidden_until_procurement_is_ready(): void
    {
        [$sales, $lead] = $this->makeLead('opportunity');
        $requirement = $lead->requirements()->create([
            ...$this->requirementData(),
            'created_by' => $sales->id,
        ]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $procurement = ProcurementRequest::firstOrFail();
        $line = $procurement->lines()->firstOrFail();
        $line->update(['cost_price' => 1250000, 'availability_status' => 'available']);

        $this->actingAs($sales)->get("/sales/leads/{$lead->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('procurementRequest.lines.0.cost_price', null));

        $procurement->update(['status' => 'ready']);

        $this->actingAs($sales)->get("/sales/leads/{$lead->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('procurementRequest.lines.0.cost_price', '1250000.00'));
    }

    public function test_sales_can_add_requirement_after_procurement_rejects_the_request(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        [$sales, $lead] = $this->makeLead('opportunity');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", $this->requirementData())
            ->assertSessionHas('success');
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::firstOrFail();
        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/reject", [
            'rejection_reason' => 'Ada yang kurang, tolong lengkapi merk & jasanya.',
        ])->assertRedirect();

        $this->assertTrue($lead->fresh()->requirementsLocked() === false);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            ...$this->requirementData(),
            'item_name' => 'Kabel UTP tambahan',
        ])->assertSessionHas('success');

        $this->assertSame(2, Requirement::where('lead_id', $lead->id)->count());
    }

    /** @return array{User, Lead} */
    private function makeLead(string $type): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => $type,
            'stage' => $type === 'opportunity' ? 'qualified' : 'new',
        ]);

        return [$sales, $lead];
    }

    /** @return array<string, mixed> */
    public function test_requirement_unit_must_be_one_of_the_fixed_options(): void
    {
        [$sales, $lead] = $this->makeLead('opportunity');

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            ...$this->requirementData(),
            'unit' => 'kardus', // bebas teks lama — sudah tidak diterima
        ])->assertSessionHasErrors('unit');

        foreach (Requirement::UNITS as $unit) {
            $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
                ...$this->requirementData(),
                'unit' => $unit,
            ])->assertSessionDoesntHaveErrors('unit');
        }
    }

    private function requirementData(): array
    {
        return [
            'item_name' => 'Router Enterprise',
            'description' => 'Dual WAN',
            'qty' => 2,
            'unit' => 'set',
            'notes' => 'Urgent',
        ];
    }
}
