<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberEditTest extends TestCase
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

    private function createInvoice(): Invoice
    {
        $so = $this->confirmedOrder();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);

        return Invoice::where('sales_order_id', $so->id)->latest('id')->firstOrFail();
    }

    public function test_finance_can_edit_number_and_future_numbers_continue_from_it(): void
    {
        $invoice = $this->createInvoice();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->patch("/finance/invoices/{$invoice->id}/number", [
            'number' => '1020/GS-INV/09/2026',
        ])->assertRedirect();

        $this->assertSame('1020/GS-INV/09/2026', $invoice->fresh()->number);

        $next = app(DocumentNumber::class)->nextInvoiceNumber();
        $this->assertStringStartsWith('1021/GS-INV/', $next);
    }

    public function test_cannot_reuse_number_from_another_invoice(): void
    {
        $existing = $this->createInvoice();
        $invoice = $this->createInvoice();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->patch("/finance/invoices/{$invoice->id}/number", [
            'number' => $existing->number,
        ])->assertSessionHasErrors('number');
    }

    public function test_non_finance_cannot_edit_number(): void
    {
        $invoice = $this->createInvoice();
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)->patch("/finance/invoices/{$invoice->id}/number", [
            'number' => '9999/GS-INV/09/2026',
        ])->assertForbidden();
    }
}
