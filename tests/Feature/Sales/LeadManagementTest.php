<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_create_lead_for_owned_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);

        $response = $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'new',
            'source' => 'Referral',
        ]);

        $lead = Lead::firstOrFail();
        $response->assertRedirectToRoute('sales.leads.show', $lead);
        $this->assertSame($sales->id, $lead->sales_id);
        $this->assertSame('lead', $lead->type);
    }

    public function test_sales_cannot_create_lead_from_another_sales_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Other Customer', 'created_by' => $other->id]);

        $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'new',
        ])->assertSessionHasErrors('contact_id');

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_sales_cannot_access_another_sales_lead(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $other->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $other->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        $this->actingAs($sales)->get("/sales/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")->assertForbidden();
    }

    public function test_owner_can_convert_qualified_lead_with_reachable_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'created_by' => $sales->id,
        ]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'lead',
            'stage' => 'qualified',
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")
            ->assertRedirectToRoute('sales.leads.show', $lead);

        $lead->refresh();
        $this->assertSame('opportunity', $lead->type);
        $this->assertSame('qualified', $lead->stage);
    }

    public function test_convert_is_blocked_until_qualified_and_contact_reachable(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        // Stage masih 'new'.
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")
            ->assertSessionHas('error');
        $this->assertSame('lead', $lead->fresh()->type);

        // Qualified tapi kontak tanpa telepon/email.
        $lead->update(['stage' => 'qualified']);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")
            ->assertSessionHas('error');
        $this->assertSame('lead', $lead->fresh()->type);

        // Lengkapi kontak -> berhasil.
        $contact->update(['phone' => '08123456789']);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")
            ->assertSessionHas('success');
        $this->assertSame('opportunity', $lead->fresh()->type);
    }

    public function test_invalid_stage_is_rejected(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);

        $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'anything',
        ])->assertSessionHasErrors('stage');
    }
}
