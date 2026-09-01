<?php

namespace Tests\Feature\Procurement;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementRequestFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_fills_cost_tax_and_availability_per_line(): void
    {
        [$procurement, $pr] = $this->submittedRequest();
        $line = $pr->lines()->firstOrFail();
        $product = $this->product(1500000);
        $tax = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);

        $this->actingAs($procurement)->put("/procurement/procurement-requests/{$pr->id}/lines", [
            'lines' => [[
                'id' => $line->id,
                'vendor_product_id' => $product->id,
                'cost_price' => 1500000,
                'tax_id' => $tax->id,
                'availability_status' => 'available',
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('procurement_request_lines', [
            'id' => $line->id,
            'vendor_product_id' => $product->id,
            'cost_price' => 1500000,
            'tax_id' => $tax->id,
            'availability_status' => 'available',
        ]);
    }

    public function test_cost_price_can_be_overridden_from_catalog_price(): void
    {
        [$procurement, $pr] = $this->submittedRequest();
        $line = $pr->lines()->firstOrFail();
        $product = $this->product(1500000);

        $this->actingAs($procurement)->put("/procurement/procurement-requests/{$pr->id}/lines", [
            'lines' => [[
                'id' => $line->id,
                'vendor_product_id' => $product->id,
                'cost_price' => 1375000, // harga negosiasi, beda dari katalog
                'tax_id' => null,
                'availability_status' => 'available',
            ]],
        ])->assertSessionHas('success');

        $this->assertSame('1375000.00', $line->fresh()->cost_price);
    }

    public function test_start_moves_submitted_to_searching(): void
    {
        [$procurement, $pr] = $this->submittedRequest();

        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$pr->id}/start")
            ->assertSessionHas('success');

        $this->assertSame('searching', $pr->fresh()->status);
    }

    public function test_ready_request_cannot_be_edited(): void
    {
        [$procurement, $pr] = $this->submittedRequest();
        $pr->update(['status' => 'ready']);
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($procurement)->put("/procurement/procurement-requests/{$pr->id}/lines", [
            'lines' => [[
                'id' => $line->id,
                'vendor_product_id' => null,
                'cost_price' => 1000,
                'tax_id' => null,
                'availability_status' => 'available',
            ]],
        ])->assertForbidden();
    }

    public function test_inactive_vendor_product_or_tax_is_rejected(): void
    {
        [$procurement, $pr] = $this->submittedRequest();
        $line = $pr->lines()->firstOrFail();
        $product = $this->product(1000, active: false);
        $tax = Tax::create(['name' => 'lama', 'rate' => 10, 'is_active' => false]);

        $this->actingAs($procurement)->put("/procurement/procurement-requests/{$pr->id}/lines", [
            'lines' => [[
                'id' => $line->id,
                'vendor_product_id' => $product->id,
                'cost_price' => 1000,
                'tax_id' => $tax->id,
                'availability_status' => 'available',
            ]],
        ])->assertSessionHasErrors(['lines.0.vendor_product_id', 'lines.0.tax_id']);
    }

    public function test_non_procurement_cannot_access_requests(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        [, $pr] = $this->submittedRequest();

        $this->actingAs($sales)->get('/procurement/procurement-requests')->assertForbidden();
        $this->actingAs($sales)->get("/procurement/procurement-requests/{$pr->id}")->assertForbidden();
    }

    /** @return array{User, ProcurementRequest} */
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

        return [$procurement, ProcurementRequest::with('lines')->firstOrFail()];
    }

    private function product(float $price, bool $active = true): VendorProduct
    {
        return Vendor::create(['name' => 'V'])->products()->create([
            'item_name' => 'Router X', 'category' => 'material', 'price' => $price, 'unit' => 'unit', 'is_active' => $active,
        ]);
    }
}
