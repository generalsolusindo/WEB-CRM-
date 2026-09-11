<?php

namespace Tests\Feature\Procurement;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function procurement(): User
    {
        return User::factory()->create(['role' => 'procurement', 'is_active' => true]);
    }

    public function test_procurement_can_create_vendor_and_product(): void
    {
        $user = $this->procurement();

        $this->actingAs($user)->post('/procurement/vendors', [
            'name' => 'PT Sumber Jaya',
            'contact_person' => 'Andi',
            'email' => 'andi@sumberjaya.test',
        ])->assertRedirect();

        $vendor = Vendor::firstOrFail();

        $this->actingAs($user)->post('/procurement/vendor-products', [
            'vendor_id' => $vendor->id,
            'item_name' => 'Switch 24 Port',
            'category' => 'material',
            'price' => 1500000,
            'unit' => 'unit',
            'is_active' => true,
        ])->assertRedirect("/procurement/vendors/{$vendor->id}");

        $this->assertDatabaseHas('vendor_products', [
            'vendor_id' => $vendor->id,
            'item_name' => 'Switch 24 Port',
            'category' => 'material',
        ]);
    }

    public function test_vendor_bank_account_note_can_be_set_and_updated(): void
    {
        $user = $this->procurement();

        $this->actingAs($user)->post('/procurement/vendors', [
            'name' => 'PT Rekening Jaya',
            'bank_account_note' => 'BCA 1234567890 a.n. PT Rekening Jaya',
        ])->assertRedirect();

        $vendor = Vendor::where('name', 'PT Rekening Jaya')->firstOrFail();
        $this->assertSame('BCA 1234567890 a.n. PT Rekening Jaya', $vendor->bank_account_note);

        $this->actingAs($user)->get("/procurement/vendors/{$vendor->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('vendor.bank_account_note', 'BCA 1234567890 a.n. PT Rekening Jaya'));

        $this->actingAs($user)->put("/procurement/vendors/{$vendor->id}", [
            'name' => $vendor->name,
            'bank_account_note' => 'Mandiri 999888777 a.n. PT Rekening Jaya',
        ])->assertRedirect();

        $this->assertSame('Mandiri 999888777 a.n. PT Rekening Jaya', $vendor->fresh()->bank_account_note);
    }

    public function test_product_validation_rejects_bad_input(): void
    {
        $user = $this->procurement();
        $vendor = Vendor::create(['name' => 'V']);

        $this->actingAs($user)->post('/procurement/vendor-products', [
            'vendor_id' => $vendor->id,
            'item_name' => '',
            'category' => 'weird',
            'price' => -10,
            'unit' => '',
        ])->assertSessionHasErrors(['item_name', 'category', 'price', 'unit']);
    }

    public function test_non_procurement_cannot_access_catalog(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)->get('/procurement/vendors')->assertForbidden();
        $this->actingAs($sales)->post('/procurement/vendors', ['name' => 'X'])->assertForbidden();
    }

    public function test_vendor_with_products_cannot_be_deleted(): void
    {
        $user = $this->procurement();
        $vendor = Vendor::create(['name' => 'V']);
        $vendor->products()->create([
            'item_name' => 'A', 'category' => 'material', 'price' => 1000, 'unit' => 'pcs', 'is_active' => true,
        ]);

        $this->actingAs($user)->delete("/procurement/vendors/{$vendor->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    }

    public function test_vendor_with_surveyor_account_cannot_be_deleted(): void
    {
        $user = $this->procurement();
        $vendor = Vendor::create(['name' => 'V', 'provides_survey' => true]);
        \App\Models\User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id]);

        $this->actingAs($user)->delete("/procurement/vendors/{$vendor->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    }

    public function test_vendor_with_running_survey_cannot_be_deleted(): void
    {
        $user = $this->procurement();
        $sales = \App\Models\User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'C', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $vendor = Vendor::create(['name' => 'V', 'provides_survey' => true]);
        $lead->surveys()->create([
            'requested_by' => $sales->id, 'site_address' => 'a', 'site_region' => 'x',
            'delivery_mode' => 'vendor', 'billable' => false, 'vendor_id' => $vendor->id, 'status' => 'in_progress',
        ]);

        $this->actingAs($user)->delete("/procurement/vendors/{$vendor->id}")->assertSessionHas('error');
    }

    public function test_vendor_and_product_delete_when_unused(): void
    {
        $user = $this->procurement();
        $vendor = Vendor::create(['name' => 'V']);
        $product = $vendor->products()->create([
            'item_name' => 'A', 'category' => 'material', 'price' => 1000, 'unit' => 'pcs', 'is_active' => true,
        ]);

        $this->actingAs($user)->delete("/procurement/vendor-products/{$product->id}")->assertSessionHas('success');
        $this->actingAs($user)->delete("/procurement/vendors/{$vendor->id}")->assertSessionHas('success');
        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_product_referenced_by_procurement_request_cannot_be_deleted(): void
    {
        $user = $this->procurement();
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'C', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'procurement',
        ]);
        $pr = ProcurementRequest::create(['lead_id' => $lead->id, 'status' => 'submitted', 'requested_by' => $sales->id]);
        $vendor = Vendor::create(['name' => 'V']);
        $product = $vendor->products()->create([
            'item_name' => 'A', 'category' => 'material', 'price' => 1000, 'unit' => 'pcs', 'is_active' => true,
        ]);
        $pr->lines()->create([
            'vendor_product_id' => $product->id,
            'item_name' => 'A', 'qty' => 1, 'unit' => 'pcs', 'cost_price' => 1000, 'availability_status' => 'available',
        ]);

        $this->actingAs($user)->delete("/procurement/vendor-products/{$product->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('vendor_products', ['id' => $product->id]);
    }
}
