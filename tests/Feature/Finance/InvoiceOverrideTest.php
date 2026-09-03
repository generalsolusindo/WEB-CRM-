<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceOverrideTest extends TestCase
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

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $lines = $pr->lines->sortBy('id')->values();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $lines[0]->id, 'selling_price' => 6000000, 'category' => 'material'],
                ['procurement_request_line_id' => $lines[1]->id, 'selling_price' => 4000000, 'category' => 'service'],
            ],
        ]);
        $q = Quotation::firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('mixed'));

        return SalesOrder::with('lines')->firstOrFail();
    }

    public function test_finance_sets_agreed_dpp_and_ppn_at_invoice_creation(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder();

        // bruto 10jt -> nilai DPP 8jt (diskon 20%), PPN 11%, DP 50%
        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
            'agreed_dpp' => 8000000,
            'ppn_rate' => 11,
        ])->assertRedirect();

        // Sales Order ikut difinalisasi
        $so->refresh();
        $this->assertSame('8000000.00', $so->agreed_dpp);
        $this->assertSame(8000000.0, $so->lines->sum(fn ($l) => (float) $l->subtotal));
        $this->assertTrue($so->lines->every(fn ($l) => (float) $l->tax_rate === 11.0));

        $dp = Invoice::with('lines')->firstOrFail();
        $this->assertSame(4000000.0, (float) $dp->amount);        // 50% x 8jt
        $this->assertSame(440000.0, (float) $dp->tax_amount);     // 11% x 4jt
        $this->assertSame(4440000.0, $dp->grandTotal());
    }

    public function test_full_invoice_pdf_renders_with_groups_and_waterfall(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder();

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
            'agreed_dpp' => 8000000,
            'ppn_rate' => 11,
            'pph23_enabled' => '1',
            'pph23_rate' => 2,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertMatchesRegularExpression('#^\d+/GS-INV/\d{2}/\d{4}$#', $invoice->number);

        $res = $this->actingAs($finance)->get("/finance/invoices/{$invoice->id}/pdf");
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));

        $html = $this->actingAs($finance)->get("/finance/invoices/{$invoice->id}/print");
        $html->assertOk()->assertSee('Materials')->assertSee('Services')->assertSee('PPh 23')->assertSee('Total Tagihan');
    }

    public function test_dp_invoice_shows_settlement_breakdown(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder(); // bruto 10jt, tanpa diskon

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => 'dp', 'ppn_rate' => 0,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $res = $this->actingAs($finance)->get("/finance/invoices/{$invoice->id}");
        $res->assertOk();
        $settlement = $res->viewData('page')['props']['settlement'];

        $this->assertSame('50', $settlement['dp_percent']);
        $this->assertSame(10000000.0, $settlement['contract_payable']); // 100%
        $this->assertSame(5000000.0, $settlement['dp_payable']);        // 50%
        $this->assertSame(5000000.0, $settlement['remaining']);         // sisa pelunasan
    }

    public function test_ppn_zero_override_is_applied(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->mixedOrder();

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => 'dp', 'ppn_rate' => 0,
        ])->assertRedirect();

        $dp = Invoice::firstOrFail();
        $this->assertSame(0.0, (float) $dp->tax_amount);
        $this->assertSame(5000000.0, (float) $dp->amount); // 50% x 10jt, tanpa diskon
    }
}
