<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mekanisme "sambung nomor dari sistem lama" lewat config/document_numbering.php (INVOICE/QUOTATION_NUMBER_START_*). */
class DocumentNumberStartOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedOrder(): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = $lead->procurementRequests()->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $q = $pr->quotations()->latest('id')->firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('material_only'));

        return SalesOrder::where('quotation_id', $q->id)->firstOrFail();
    }

    private function createInvoice(SalesOrder $so): Invoice
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);

        return Invoice::where('sales_order_id', $so->id)->latest('id')->firstOrFail();
    }

    private function createQuotation(): Quotation
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust Q', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::where('lead_id', $lead->id)->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);

        return $pr->quotations()->latest('id')->firstOrFail();
    }

    public function test_quotation_number_jumps_to_configured_start(): void
    {
        config(['document_numbering.quotation' => ['year' => now()->year, 'next_sequence' => 1255]]);

        $number = app(DocumentNumber::class)->nextQuotationNumber();
        $this->assertStringStartsWith('1255/GS-PN/', $number);
    }

    public function test_quotation_sequence_continues_normally_after_jump(): void
    {
        config(['document_numbering.quotation' => ['year' => now()->year, 'next_sequence' => 1255]]);

        $quotation = $this->createQuotation();
        $this->assertStringStartsWith('1255/GS-PN/', $quotation->number);

        $number = app(DocumentNumber::class)->nextQuotationNumber();
        $this->assertStringStartsWith('1256/GS-PN/', $number);
    }

    public function test_invoice_number_jumps_to_configured_start(): void
    {
        config(['document_numbering.invoice' => ['year' => now()->year, 'next_sequence' => 1020]]);

        $number = app(DocumentNumber::class)->nextInvoiceNumber();
        $this->assertStringStartsWith('1020/GS-INV/', $number);
    }

    public function test_invoice_sequence_continues_normally_after_jump(): void
    {
        config(['document_numbering.invoice' => ['year' => now()->year, 'next_sequence' => 1020]]);

        $invoice = $this->createInvoice($this->confirmedOrder());
        $this->assertStringStartsWith('1020/GS-INV/', $invoice->number);

        $number = app(DocumentNumber::class)->nextInvoiceNumber();
        $this->assertStringStartsWith('1021/GS-INV/', $number);
    }

    public function test_configured_start_is_ignored_when_lower_than_existing_numbers(): void
    {
        $so = $this->confirmedOrder();
        $this->createInvoice($so);

        config(['document_numbering.invoice' => ['year' => now()->year, 'next_sequence' => 1]]);

        $number = app(DocumentNumber::class)->nextInvoiceNumber();
        $this->assertStringStartsWith('2/GS-INV/', $number);
    }

    public function test_configured_start_is_ignored_when_year_does_not_match(): void
    {
        config(['document_numbering.quotation' => ['year' => now()->year - 1, 'next_sequence' => 1255]]);

        $number = app(DocumentNumber::class)->nextQuotationNumber();
        $this->assertStringStartsWith('1/GS-PN/', $number);
    }
}
