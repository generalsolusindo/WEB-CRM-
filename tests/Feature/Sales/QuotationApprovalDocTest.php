<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationApprovalDocTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Quotation} */
    private function sentQuotation(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);

        return [$sales, $quotation];
    }

    public function test_confirm_rejected_without_signed_quotation(): void
    {
        [$sales, $quotation] = $this->sentQuotation();

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", [
            'order_type' => 'material_only',
        ])->assertSessionHasErrors('signed_quotation');

        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_confirm_stores_signed_quotation_and_optional_po(): void
    {
        [$sales, $quotation] = $this->sentQuotation();

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", [
            'order_type' => 'material_only',
            'signed_quotation' => UploadedFile::fake()->create('quo-ttd.pdf', 80, 'application/pdf'),
            'purchase_order' => UploadedFile::fake()->image('po.jpg'),
            'po_number' => 'PO/2026/777',
        ])->assertRedirect();

        $so = SalesOrder::with('attachments')->firstOrFail();
        $this->assertSame('PO/2026/777', $so->po_number);
        $this->assertEqualsCanonicalizing(
            ['quotation_signed', 'purchase_order'],
            $so->attachments->pluck('category')->all(),
        );
        foreach ($so->attachments as $a) {
            Storage::disk('local')->assertExists($a->file_path);
        }
    }

    public function test_confirm_works_with_only_signed_quotation(): void
    {
        [$sales, $quotation] = $this->sentQuotation();

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", [
            'order_type' => 'mixed',
            'signed_quotation' => UploadedFile::fake()->image('quo.png'),
        ])->assertRedirect();

        $so = SalesOrder::with('attachments')->firstOrFail();
        $this->assertNull($so->po_number);
        $this->assertSame(['quotation_signed'], $so->attachments->pluck('category')->all());
    }

    public function test_oversized_file_rejected(): void
    {
        [$sales, $quotation] = $this->sentQuotation();

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", [
            'order_type' => 'material_only',
            'signed_quotation' => UploadedFile::fake()->create('big.pdf', 1500, 'application/pdf'),
        ])->assertSessionHasErrors('signed_quotation');
    }
}
