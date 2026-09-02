<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineCategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, ProcurementRequest} */
    private function readyPr(?string $vendorProductCategory = null): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Instalasi Jaringan', 'qty' => 1, 'unit' => 'paket', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $vpId = null;
        if ($vendorProductCategory) {
            $vendor = Vendor::create(['name' => 'V']);
            $vpId = VendorProduct::create([
                'vendor_id' => $vendor->id, 'item_name' => 'x', 'category' => $vendorProductCategory,
                'price' => 1, 'unit' => 'x', 'is_active' => true,
            ])->id;
        }
        $pr->lines()->update([
            'cost_price' => 1000000, 'availability_status' => 'available', 'vendor_product_id' => $vpId,
        ]);
        $pr->update(['status' => 'ready']);

        return [$sales, $pr->fresh('lines')];
    }

    public function test_category_defaults_from_vendor_product(): void
    {
        [$sales, $pr] = $this->readyPr('service');

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines->first()->id, 'selling_price' => 1300000]],
        ])->assertRedirect();

        $this->assertSame('service', Quotation::firstOrFail()->lines()->firstOrFail()->category);
    }

    public function test_category_defaults_material_without_vendor_product(): void
    {
        [$sales, $pr] = $this->readyPr(null);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines->first()->id, 'selling_price' => 1300000]],
        ]);

        $this->assertSame('material', Quotation::firstOrFail()->lines()->firstOrFail()->category);
    }

    public function test_sales_can_override_category_and_it_flows_to_invoice(): void
    {
        [$sales, $pr] = $this->readyPr('material');

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $pr->lines->first()->id,
                'selling_price' => 1300000,
                'category' => 'service',
            ]],
        ]);
        $quotation = Quotation::firstOrFail();
        $this->assertSame('service', $quotation->lines()->firstOrFail()->category);

        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('service_only'));
        $so = SalesOrder::firstOrFail();
        $this->assertSame('service', $so->lines()->firstOrFail()->category);

        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $this->assertSame('service', Invoice::firstOrFail()->lines()->firstOrFail()->category);
    }

    public function test_requirement_category_flows_to_pr_line_and_quotation_default(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/requirements", [
            'item_name' => 'Instalasi CCTV', 'category' => 'service', 'qty' => 1, 'unit' => 'paket',
        ])->assertRedirect();

        $this->assertSame('service', $lead->requirements()->firstOrFail()->category);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $this->assertSame('service', $pr->lines->first()->category);

        // quotation line default ikut kategori PR line (belum ada input eksplisit)
        $pr->lines()->update(['cost_price' => 500000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines->first()->id, 'selling_price' => 900000]],
        ]);

        $this->assertSame('service', Quotation::firstOrFail()->lines()->firstOrFail()->category);
    }

    public function test_invalid_category_rejected(): void
    {
        [$sales, $pr] = $this->readyPr('material');

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $pr->lines->first()->id,
                'selling_price' => 1300000,
                'category' => 'jasa-salah',
            ]],
        ])->assertSessionHasErrors('lines.0.category');
    }
}
