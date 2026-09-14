<?php

namespace Tests\Feature\Admin;

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

class DocumentNumberSettingTest extends TestCase
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

    public function test_administrator_sees_invoice_and_quotation_summary(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/document-numbering')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('invoice.lastSequence', 0)
                ->where('invoice.nextSequence', 1)
                ->where('invoice.hasCustomSetting', false)
                ->where('quotation.lastSequence', 0)
                ->where('quotation.nextSequence', 1)
                ->where('quotation.hasCustomSetting', false));
    }

    public function test_administrator_sets_next_invoice_sequence_and_it_is_used(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/document-numbering', [
            'document_type' => 'invoice', 'next_sequence' => 1020,
        ])->assertRedirect();

        $number = app(DocumentNumber::class)->nextInvoiceNumber();
        $year = now()->year;
        $month = now()->format('m');
        $this->assertSame("1020/GS-INV/{$month}/{$year}", $number);
    }

    public function test_administrator_sets_next_quotation_sequence_and_it_is_used(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/document-numbering', [
            'document_type' => 'quotation', 'next_sequence' => 1255,
        ])->assertRedirect();

        $number = app(DocumentNumber::class)->nextQuotationNumber();
        $year = now()->year;
        $month = now()->format('m');
        $this->assertSame("1255/GS-PN/{$month}/{$year}", $number);
    }

    public function test_invoice_sequence_continues_normally_after_custom_start(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);
        $this->actingAs($admin)->post('/admin/document-numbering', [
            'document_type' => 'invoice', 'next_sequence' => 1020,
        ]);

        $invoice = $this->createInvoice($this->confirmedOrder());
        $this->assertStringStartsWith('1020/GS-INV/', $invoice->number);

        $number = app(DocumentNumber::class)->nextInvoiceNumber();
        $this->assertStringStartsWith('1021/GS-INV/', $number);
    }

    public function test_quotation_sequence_continues_normally_after_custom_start(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);
        $this->actingAs($admin)->post('/admin/document-numbering', [
            'document_type' => 'quotation', 'next_sequence' => 1255,
        ]);

        $quotation = $this->createQuotation();
        $this->assertStringStartsWith('1255/GS-PN/', $quotation->number);

        $number = app(DocumentNumber::class)->nextQuotationNumber();
        $this->assertStringStartsWith('1256/GS-PN/', $number);
    }

    public function test_cannot_set_next_sequence_lower_than_existing_document(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'is_active' => true]);
        $this->createInvoice($this->confirmedOrder());

        $this->actingAs($admin)->post('/admin/document-numbering', [
            'document_type' => 'invoice', 'next_sequence' => 1,
        ])->assertSessionHasErrors('next_sequence');

        $this->createQuotation();
        $this->actingAs($admin)->post('/admin/document-numbering', [
            'document_type' => 'quotation', 'next_sequence' => 1,
        ])->assertSessionHasErrors('next_sequence');
    }

    public function test_non_administrator_cannot_access(): void
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);

        $this->actingAs($ops)->get('/admin/document-numbering')->assertForbidden();
        $this->actingAs($ops)->post('/admin/document-numbering', [
            'document_type' => 'invoice', 'next_sequence' => 100,
        ])->assertForbidden();
    }
}
