<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_create_contact_and_creator_is_taken_from_session(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);

        $response = $this->actingAs($sales)->post('/sales/contacts', [
            'name' => 'Budi Santoso',
            'company_name' => 'PT Contoh',
            'email' => 'budi@example.com',
            'created_by' => $other->id,
        ]);

        $contact = Contact::firstOrFail();
        $response->assertRedirectToRoute('sales.contacts.show', $contact);
        $this->assertSame($sales->id, $contact->created_by);
    }

    public function test_sales_only_sees_owned_contacts(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        Contact::create(['name' => 'Owned', 'created_by' => $sales->id]);
        Contact::create(['name' => 'Hidden', 'created_by' => $other->id]);

        $this->actingAs($sales)->get('/sales/contacts')
            ->assertOk()
            ->assertSee('Owned')
            ->assertDontSee('Hidden');
    }

    public function test_sales_cannot_view_or_update_another_sales_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Private', 'created_by' => $other->id]);

        $this->actingAs($sales)->get("/sales/contacts/{$contact->id}")->assertForbidden();
        $this->actingAs($sales)->put("/sales/contacts/{$contact->id}", ['name' => 'Changed'])->assertForbidden();
    }

    public function test_contact_used_by_lead_cannot_be_deleted(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        $this->actingAs($sales)->delete("/sales/contacts/{$contact->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }
}
