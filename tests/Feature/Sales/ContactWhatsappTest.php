<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactWhatsappTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_normalized_whatsapp_number_per_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        Contact::create(['name' => 'Budi', 'phone' => '0812-3456-7890', 'created_by' => $sales->id]);
        Contact::create(['name' => 'Tanpa Nomor', 'created_by' => $sales->id]);

        $response = $this->actingAs($sales)->get('/sales/contacts')->assertOk();
        $contacts = collect($response->viewData('page')['props']['contacts']['data']);

        $this->assertSame('6281234567890', $contacts->firstWhere('name', 'Budi')['whatsapp_number']);
        $this->assertNull($contacts->firstWhere('name', 'Tanpa Nomor')['whatsapp_number']);
    }

    public function test_show_exposes_normalized_whatsapp_number(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Budi', 'phone' => '0812-3456-7890', 'created_by' => $sales->id]);

        $this->actingAs($sales)->get("/sales/contacts/{$contact->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contact.whatsapp_number', '6281234567890'));
    }
}
