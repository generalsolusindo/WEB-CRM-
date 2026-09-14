<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** SO campuran: 1 material 6jt + 1 jasa 4jt, tanpa diskon/pajak, dp. */
    private function mixedOrder(): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'category' => 'material', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $lead->requirements()->create(['item_name' => 'Jasa Pasang', 'category' => 'service', 'qty' => 1, 'unit' => 'lot', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = $lead->procurementRequests()->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $lines = $pr->lines->sortBy('id')->values();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $lines[0]->id, 'selling_price' => 6000000, 'category' => 'material'],
                ['procurement_request_line_id' => $lines[1]->id, 'selling_price' => 4000000, 'category' => 'service'],
            ],
        ]);
        $q = $pr->quotations()->latest('id')->firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('mixed'));

        return SalesOrder::where('quotation_id', $q->id)->with('lines')->firstOrFail();
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    private function draftInvoice(): Invoice
    {
        $finance = $this->finance();
        $so = $this->mixedOrder();
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);

        return Invoice::with('lines')->latest('id')->firstOrFail();
    }

    public function test_finance_can_edit_lines_due_date_and_notes(): void
    {
        $invoice = $this->draftInvoice();
        $lines = $invoice->lines->sortBy('id')->values();
        $finance = $this->finance();

        $this->actingAs($finance)
            ->put("/finance/invoices/{$invoice->id}", [
                'due_date' => '2026-12-01',
                'notes' => 'Revisi harga setelah negosiasi',
                'lines' => [
                    ['sales_order_line_id' => $lines[0]->sales_order_line_id, 'item_name' => 'Router (revisi)', 'category' => 'material', 'qty' => 2, 'unit_price' => 2500000, 'discount_amount' => 0, 'tax_rate' => 0],
                    ['sales_order_line_id' => $lines[1]->sales_order_line_id, 'item_name' => 'Jasa Pasang', 'category' => 'service', 'qty' => 1, 'unit_price' => 4000000, 'discount_amount' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertRedirect();

        $invoice->refresh()->load('lines');
        $this->assertSame('2026-12-01', $invoice->due_date->toDateString());
        $this->assertSame('Revisi harga setelah negosiasi', $invoice->notes);
        $this->assertSame('9000000.00', $invoice->amount);
        $this->assertSame('Router (revisi)', $invoice->lines->sortBy('id')->first()->item_name);
        $this->assertSame('2.00', $invoice->lines->sortBy('id')->first()->qty);
    }

    public function test_finance_can_add_and_remove_lines(): void
    {
        $invoice = $this->draftInvoice();
        $finance = $this->finance();

        $this->actingAs($finance)
            ->put("/finance/invoices/{$invoice->id}", [
                'lines' => [
                    ['sales_order_line_id' => null, 'item_name' => 'Item baru', 'category' => 'material', 'qty' => 1, 'unit_price' => 1000000, 'discount_amount' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertRedirect();

        $invoice->refresh()->load('lines');
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('Item baru', $invoice->lines->first()->item_name);
        $this->assertSame('1000000.00', $invoice->amount);
    }

    public function test_editing_resets_status_to_draft(): void
    {
        $invoice = $this->draftInvoice();
        $invoice->update(['status' => 'sent']);
        $lines = $invoice->lines->sortBy('id')->values();
        $finance = $this->finance();

        $this->actingAs($finance)
            ->put("/finance/invoices/{$invoice->id}", [
                'lines' => [
                    ['sales_order_line_id' => $lines[0]->sales_order_line_id, 'item_name' => $lines[0]->item_name, 'category' => 'material', 'qty' => 1, 'unit_price' => 6500000, 'discount_amount' => 0, 'tax_rate' => 0],
                    ['sales_order_line_id' => $lines[1]->sales_order_line_id, 'item_name' => $lines[1]->item_name, 'category' => 'service', 'qty' => 1, 'unit_price' => 4000000, 'discount_amount' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_cannot_edit_invoice_with_recorded_payment(): void
    {
        $invoice = $this->draftInvoice();
        $finance = $this->finance();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $lines = $invoice->lines->sortBy('id')->values();
        $this->actingAs($finance)
            ->put("/finance/invoices/{$invoice->id}", [
                'lines' => [
                    ['sales_order_line_id' => $lines[0]->sales_order_line_id, 'item_name' => 'Ganti nama', 'category' => 'material', 'qty' => 1, 'unit_price' => 1000000, 'discount_amount' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertForbidden();
    }

    public function test_cannot_edit_cancelled_invoice(): void
    {
        $invoice = $this->draftInvoice();
        $invoice->update(['status' => 'cancelled']);
        $finance = $this->finance();
        $lines = $invoice->lines->sortBy('id')->values();

        $this->actingAs($finance)
            ->put("/finance/invoices/{$invoice->id}", [
                'lines' => [
                    ['sales_order_line_id' => $lines[0]->sales_order_line_id, 'item_name' => 'Ganti nama', 'category' => 'material', 'qty' => 1, 'unit_price' => 1000000, 'discount_amount' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertForbidden();
    }

    /** sales_order_line_id datang sebagai string dari form HTTP sungguhan — bukan int PHP. */
    public function test_sales_order_line_id_as_string_from_real_form_submission_is_accepted(): void
    {
        $invoice = $this->draftInvoice();
        $lines = $invoice->lines->sortBy('id')->values();
        $finance = $this->finance();

        $this->actingAs($finance)
            ->put("/finance/invoices/{$invoice->id}", [
                'lines' => [
                    ['sales_order_line_id' => (string) $lines[0]->sales_order_line_id, 'item_name' => 'Router', 'category' => 'material', 'qty' => '1', 'unit_price' => '1200000', 'discount_amount' => '0', 'tax_rate' => '11'],
                    ['sales_order_line_id' => (string) $lines[1]->sales_order_line_id, 'item_name' => 'Jasa Pasang', 'category' => 'service', 'qty' => '1', 'unit_price' => '4000000', 'discount_amount' => '0', 'tax_rate' => '11'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_non_finance_cannot_edit_invoice(): void
    {
        $invoice = $this->draftInvoice();
        $sales = User::factory()->create(['role' => 'sales']);
        $lines = $invoice->lines->sortBy('id')->values();

        $this->actingAs($sales)
            ->put("/finance/invoices/{$invoice->id}", [
                'lines' => [
                    ['sales_order_line_id' => $lines[0]->sales_order_line_id, 'item_name' => 'Ganti nama', 'category' => 'material', 'qty' => 1, 'unit_price' => 1000000, 'discount_amount' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertForbidden();
    }

    public function test_pph23_amount_recalculates_after_line_edit(): void
    {
        $invoice = $this->draftInvoice();
        $invoice->update(['pph23_enabled' => true, 'pph23_rate' => 2]);
        $lines = $invoice->lines->sortBy('id')->values();
        $finance = $this->finance();

        $this->actingAs($finance)
            ->put("/finance/invoices/{$invoice->id}", [
                'lines' => [
                    ['sales_order_line_id' => $lines[0]->sales_order_line_id, 'item_name' => $lines[0]->item_name, 'category' => 'material', 'qty' => 1, 'unit_price' => 5000000, 'discount_amount' => 0, 'tax_rate' => 0],
                    ['sales_order_line_id' => $lines[1]->sales_order_line_id, 'item_name' => $lines[1]->item_name, 'category' => 'service', 'qty' => 1, 'unit_price' => 8000000, 'discount_amount' => 0, 'tax_rate' => 0],
                ],
            ])
            ->assertRedirect();

        // PPh 23 = 2% dari DPP baris jasa yang baru (8jt) = 160.000
        $this->assertSame(160000.0, (float) $invoice->fresh()->pph23_amount);
    }
}
