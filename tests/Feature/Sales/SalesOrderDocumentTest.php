<?php

namespace Tests\Feature\Sales;

use App\Models\Attachment;
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

class SalesOrderDocumentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, SalesOrder} */
    private function confirmedOrder(): array
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
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('material_only'));

        return [$sales, SalesOrder::firstOrFail()];
    }

    public function test_sales_replaces_signed_quotation(): void
    {
        [$sales, $so] = $this->confirmedOrder();
        $old = $so->attachments()->where('category', 'quotation_signed')->firstOrFail();

        $this->actingAs($sales)->post("/sales/sales-orders/{$so->id}/documents", [
            'signed_quotation' => UploadedFile::fake()->create('quo-rev.pdf', 90, 'application/pdf'),
            'po_number' => $so->po_number,
        ])->assertRedirect();

        $this->assertDatabaseMissing('attachments', ['id' => $old->id]);
        Storage::disk('local')->assertMissing($old->file_path);
        $this->assertSame(1, $so->attachments()->where('category', 'quotation_signed')->count());
    }

    public function test_sales_adds_po_later_without_touching_quotation_doc(): void
    {
        [$sales, $so] = $this->confirmedOrder();
        $quoDoc = $so->attachments()->where('category', 'quotation_signed')->firstOrFail();

        $this->actingAs($sales)->post("/sales/sales-orders/{$so->id}/documents", [
            'purchase_order' => UploadedFile::fake()->image('po.jpg'),
            'po_number' => 'PO/2026/555',
        ])->assertRedirect();

        $this->assertSame('PO/2026/555', $so->fresh()->po_number);
        $this->assertDatabaseHas('attachments', ['id' => $quoDoc->id]);
        $this->assertSame(1, $so->attachments()->where('category', 'purchase_order')->count());
    }

    public function test_update_po_number_only(): void
    {
        [$sales, $so] = $this->confirmedOrder();

        $this->actingAs($sales)->post("/sales/sales-orders/{$so->id}/documents", [
            'po_number' => 'PO-XYZ',
        ])->assertRedirect();

        $this->assertSame('PO-XYZ', $so->fresh()->po_number);
        // dokumen tidak diubah: hanya quotation_signed dari konfirmasi
        $this->assertSame(1, Attachment::whereIn('category', ['quotation_signed', 'purchase_order'])->count());
    }

    public function test_non_owner_sales_cannot_manage_documents(): void
    {
        [, $so] = $this->confirmedOrder();
        $other = User::factory()->create(['role' => 'sales']);

        $this->actingAs($other)->post("/sales/sales-orders/{$so->id}/documents", [
            'po_number' => 'X',
        ])->assertForbidden();
    }

    public function test_other_role_cannot_manage_documents(): void
    {
        [, $so] = $this->confirmedOrder();
        $finance = User::factory()->create(['role' => 'finance']);

        $this->actingAs($finance)->post("/sales/sales-orders/{$so->id}/documents", [
            'po_number' => 'X',
        ])->assertForbidden();
    }
}
