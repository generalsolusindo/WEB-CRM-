<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManualLinesTest extends TestCase
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

    public function test_finance_edits_line_items_manually_without_touching_sales_order(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder();
        $soLines = $so->lines->sortBy('id')->values();

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
            'notes' => 'Negosiasi ulang harga router jadi 5jt',
            'lines' => [
                ['sales_order_line_id' => $soLines[0]->id, 'item_name' => 'Router (harga nego)', 'category' => 'material', 'qty' => 1, 'unit_price' => 5000000, 'discount_amount' => 0, 'tax_rate' => 11],
                ['sales_order_line_id' => $soLines[1]->id, 'item_name' => 'Jasa Pasang', 'category' => 'service', 'qty' => 1, 'unit_price' => 4000000, 'discount_amount' => 0, 'tax_rate' => 11],
            ],
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('Negosiasi ulang harga router jadi 5jt', $invoice->notes);
        $this->assertSame(9000000.0, (float) $invoice->amount);
        $this->assertSame(990000.0, (float) $invoice->tax_amount);
        $this->assertSame('Router (harga nego)', $invoice->lines->sortBy('id')->first()->item_name);

        // Data Sales Order asli tidak berubah sama sekali.
        $so->refresh();
        $this->assertNull($so->agreed_dpp);
        $this->assertSame(6000000.0, (float) $soLines[0]->fresh()->subtotal);
        $this->assertSame(0.0, (float) $soLines[0]->fresh()->tax_rate);
    }

    public function test_finance_can_add_and_remove_line_items_manually(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder();
        $soLines = $so->lines->sortBy('id')->values();

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
            'lines' => [
                // hanya kirim baris jasa (baris material "dihapus" dari invoice ini)
                ['sales_order_line_id' => $soLines[1]->id, 'item_name' => 'Jasa Pasang', 'category' => 'service', 'qty' => 1, 'unit_price' => 4000000, 'tax_rate' => 0],
                // baris baru yang tidak berasal dari Sales Order sama sekali
                ['sales_order_line_id' => null, 'item_name' => 'Biaya Tambahan Perjalanan', 'category' => 'service', 'qty' => 1, 'unit_price' => 500000, 'tax_rate' => 0],
            ],
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertCount(2, $invoice->lines);
        $this->assertSame(4500000.0, (float) $invoice->amount);
        $this->assertTrue($invoice->lines->contains('item_name', 'Biaya Tambahan Perjalanan'));
        $this->assertNull($invoice->lines->firstWhere('item_name', 'Biaya Tambahan Perjalanan')->sales_order_line_id);
    }

    public function test_manual_lines_ignore_agreed_dpp_and_ppn_overrides(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder();
        $soLines = $so->lines->sortBy('id')->values();

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
            'agreed_dpp' => 1000000,
            'ppn_rate' => 99,
            'lines' => [
                ['sales_order_line_id' => $soLines[0]->id, 'item_name' => 'Router', 'category' => 'material', 'qty' => 1, 'unit_price' => 6000000, 'tax_rate' => 11],
            ],
        ])->assertRedirect();

        $so->refresh();
        $this->assertNull($so->agreed_dpp);
        $this->assertSame(0.0, (float) $soLines[0]->fresh()->tax_rate);

        $invoice = Invoice::firstOrFail();
        $this->assertSame(6000000.0, (float) $invoice->amount);
        $this->assertSame(660000.0, (float) $invoice->tax_amount);
    }

    public function test_rejects_untrusted_sales_order_line_id_on_manual_line(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder();
        $otherSo = $this->mixedOrder();
        $foreignLineId = $otherSo->lines->first()->id;

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
            'lines' => [
                ['sales_order_line_id' => $foreignLineId, 'item_name' => 'Router', 'category' => 'material', 'qty' => 1, 'unit_price' => 6000000, 'tax_rate' => 0],
            ],
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->where('sales_order_id', $so->id)->firstOrFail();
        // sales_order_line_id yang bukan milik Sales Order ini diabaikan (di-null-kan), bukan dipakai apa adanya.
        $this->assertNull($invoice->lines->first()->sales_order_line_id);
    }

    public function test_non_finance_cannot_create_invoice_with_manual_lines(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $so = $this->mixedOrder();

        $this->actingAs($sales)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
            'lines' => [
                ['sales_order_line_id' => null, 'item_name' => 'X', 'category' => 'material', 'qty' => 1, 'unit_price' => 100000],
            ],
        ])->assertForbidden();
    }
}
