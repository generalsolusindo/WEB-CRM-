<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_sales_can_upload_npwp_document_when_creating_contact(): void
    {
        Storage::fake('local');
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)->post('/sales/contacts', [
            'name' => 'Budi Santoso',
            'npwp' => '01.234.567.8-901.000',
            'npwp_document' => UploadedFile::fake()->image('npwp.jpg'),
        ])->assertRedirect();

        $contact = Contact::firstOrFail();
        $this->assertNotNull($contact->npwpDocument());
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Contact::class,
            'attachable_id' => $contact->id,
            'category' => 'npwp_document',
        ]);
    }

    public function test_sales_can_replace_npwp_document_when_updating_contact(): void
    {
        Storage::fake('local');
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'npwp' => '01.234.567.8-901.000', 'created_by' => $sales->id]);

        $this->actingAs($sales)->put("/sales/contacts/{$contact->id}", [
            'name' => 'Customer',
            'npwp' => '01.234.567.8-901.000',
            'npwp_document' => UploadedFile::fake()->image('npwp-updated.jpg'),
        ])->assertRedirect();

        $this->assertNotNull($contact->npwpDocument());
    }

    public function test_procurement_sees_npwp_indicator_when_customer_has_npwp(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $withNpwp = Contact::create(['name' => 'Ada NPWP', 'npwp' => '01.234.567.8-901.000', 'created_by' => $sales->id]);
        $withoutNpwp = Contact::create(['name' => 'Tanpa NPWP', 'created_by' => $sales->id]);

        $leadWith = Lead::create(['contact_id' => $withNpwp->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'requirement']);
        $leadWith->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$leadWith->id}/submit-procurement");
        $prWith = $leadWith->procurementRequests()->latest('id')->firstOrFail();

        $leadWithout = Lead::create(['contact_id' => $withoutNpwp->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'requirement']);
        $leadWithout->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$leadWithout->id}/submit-procurement");
        $prWithout = $leadWithout->procurementRequests()->latest('id')->firstOrFail();

        $this->actingAs($procurement)->get("/procurement/procurement-requests/{$prWith->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('hasNpwp', true));

        $this->actingAs($procurement)->get("/procurement/procurement-requests/{$prWithout->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('hasNpwp', false));
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

    public function test_merging_moves_leads_quotations_and_sales_orders_then_deletes_duplicate(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $keep = Contact::create(['name' => 'Dian Herbayu', 'phone' => '0896', 'created_by' => $sales->id]);
        $duplicate = Contact::create(['name' => 'Dian H.', 'email' => 'dian@dup.test', 'created_by' => $sales->id]);

        $lead = Lead::create([
            'contact_id' => $duplicate->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'negotiation',
        ]);
        $quotation = \App\Models\Quotation::create([
            'number' => 'QUO-TEST-0001', 'procurement_request_id' => \App\Models\ProcurementRequest::create([
                'lead_id' => $lead->id, 'status' => 'ready',
            ])->id,
            'lead_id' => $lead->id, 'contact_id' => $duplicate->id, 'sales_id' => $sales->id,
            'status' => 'draft', 'revision_number' => 1,
        ]);

        $this->actingAs($sales)->post("/sales/contacts/{$keep->id}/merge", [
            'duplicate_contact_id' => $duplicate->id,
        ])->assertRedirect(route('sales.contacts.show', $keep));

        $this->assertSame($keep->id, $lead->fresh()->contact_id);
        $this->assertSame($keep->id, $quotation->fresh()->contact_id);
        $this->assertDatabaseMissing('contacts', ['id' => $duplicate->id]);
        // Field yang kosong di $keep (email) ikut terisi dari duplikatnya.
        $this->assertSame('dian@dup.test', $keep->fresh()->email);
        // Field yang sudah terisi di $keep (phone) tidak ditimpa.
        $this->assertSame('0896', $keep->fresh()->phone);
    }

    public function test_cannot_merge_another_sales_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        $keep = Contact::create(['name' => 'Milik Saya', 'created_by' => $sales->id]);
        $notMine = Contact::create(['name' => 'Milik Orang Lain', 'created_by' => $other->id]);

        $this->actingAs($sales)->post("/sales/contacts/{$keep->id}/merge", [
            'duplicate_contact_id' => $notMine->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('contacts', ['id' => $notMine->id]);
    }

    public function test_cannot_merge_contact_with_itself(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Solo', 'created_by' => $sales->id]);

        // Ditolak lewat authorize() policy (403) sebelum sempat divalidasi — keduanya
        // sama-sama efektif mencegah merge, tapi authorize() jalan lebih dulu.
        $this->actingAs($sales)->post("/sales/contacts/{$contact->id}/merge", [
            'duplicate_contact_id' => $contact->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }
}
