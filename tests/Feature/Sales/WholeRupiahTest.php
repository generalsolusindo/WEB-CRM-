<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Angka rupiah tidak boleh punya sen: harga jual, diskon, DPP, PPN, dan total harus bulat
 * dan saling menyesuaikan. Angka-angkanya diambil dari laporan quotation 1281 (PT Stupa).
 */
class WholeRupiahTest extends TestCase
{
    use RefreshDatabase;

    private function assertWhole(float|string|null $value, string $label): void
    {
        $this->assertSame(0.0, fmod((float) $value, 1.0), "{$label} masih berdesimal: {$value}");
    }

    public function test_agreed_dpp_quotation_has_only_whole_rupiah_and_hits_the_agreed_dpp_exactly(): void
    {
        [$sales, $quotation] = $this->stupaQuotation(agreedDpp: 10810813);

        $lines = $quotation->lines()->orderBy('id')->get();
        foreach ($lines as $line) {
            $this->assertWhole($line->selling_price, 'harga jual');
            $this->assertWhole($line->discount_amount, 'diskon');
            $this->assertWhole($line->subtotal, 'DPP baris');
            $this->assertWhole($line->tax_amount, 'PPN baris');
        }

        // sisa pembulatan ke baris terakhir: jumlah DPP persis sama dengan nilai disepakati
        $this->assertSame(10810813.0, round((float) $lines->sum('subtotal')));
        $this->assertSame(13307440.0, round((float) $lines->sum(fn ($l) => $l->subtotal + $l->discount_amount)));

        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}")
            ->assertInertia(fn ($page) => $page
                ->where('totals.subtotal', 10810813)
                ->where('totals.tax', 1189189)
                ->where('totals.grand_total', 12000002));
    }

    public function test_totals_are_whole_and_add_up_for_ordinary_discounts_and_tax(): void
    {
        [$sales, $quotation] = $this->stupaQuotation(agreedDpp: null, discountPercent: 12.5);

        $totals = $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}")->viewData('page')['props']['totals'];

        foreach (['gross', 'discount', 'subtotal', 'tax', 'grand_total', 'pph23_estimate'] as $key) {
            $this->assertWhole($totals[$key], $key);
        }
        $this->assertSame((float) $totals['subtotal'] + (float) $totals['tax'], (float) $totals['grand_total']);
        $this->assertSame((float) $totals['gross'] - (float) $totals['discount'], (float) $totals['subtotal']);
    }

    public function test_decimal_input_is_rounded_to_whole_rupiah(): void
    {
        [$sales, $quotation] = $this->stupaQuotation(agreedDpp: null, sellingPrices: [1000000.6, 2000000.4, 500000.5]);

        $prices = $quotation->lines()->orderBy('id')->pluck('selling_price')->map(fn ($p) => (float) $p)->all();

        $this->assertSame([1000001.0, 2000000.0, 500001.0], $prices);
    }

    public function test_dp_and_final_invoice_amounts_are_whole_and_add_up_to_the_order(): void
    {
        [$sales, $quotation] = $this->stupaQuotation(agreedDpp: 10810813);
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('service_only'));
        $so = SalesOrder::with('lines')->firstOrFail();
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);

        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $dp = Invoice::where('invoice_phase', 'dp')->with('lines')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$dp->id}/payments", [
            'amount_paid' => $dp->payableAmount(),
            'paid_at' => now()->toDateTimeString(),
        ]);
        Project::whereBelongsTo($so)->update(['status' => 'completed']);
        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")->assertRedirect();
        $final = Invoice::where('invoice_phase', 'final')->with('lines')->firstOrFail();

        foreach ([$dp, $final] as $invoice) {
            $this->assertWhole($invoice->amount, "amount {$invoice->invoice_phase}");
            $this->assertWhole($invoice->tax_amount, "PPN {$invoice->invoice_phase}");
            $this->assertWhole($invoice->grandTotal(), "total {$invoice->invoice_phase}");
            foreach ($invoice->lines as $line) {
                $this->assertWhole($line->unit_price, 'harga satuan invoice');
                $this->assertWhole($line->discount_amount, 'diskon invoice');
                $this->assertWhole($line->subtotal, 'DPP invoice');
            }
        }

        // DP + pelunasan = seluruh DPP order, tanpa selisih pembulatan
        $this->assertSame(10810813.0, (float) $dp->amount + (float) $final->amount);
    }

    /**
     * @param  array<int, float>|null  $sellingPrices
     * @return array{User, Quotation}
     */
    private function stupaQuotation(?int $agreedDpp, ?float $discountPercent = null, ?array $sellingPrices = null): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Abi', 'company_name' => 'PT Stupa', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        foreach (['Test Fluke UTP CAT6 (17 Node) - DSX-5000', 'Test Fluke FO 8 Core - DSX-5000', 'Transport'] as $name) {
            $lead->requirements()->create(['item_name' => $name, 'category' => 'service', 'qty' => 1, 'unit' => 'lot', 'created_by' => $sales->id]);
        }
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);

        $prices = $sellingPrices ?? [4598160, 6209280, 2500000];
        $payload = ['lines' => $pr->lines->sortBy('id')->values()->map(fn ($line, $i) => array_filter([
            'procurement_request_line_id' => $line->id,
            'selling_price' => $prices[$i],
            'tax_rate' => 11,
            'discount_percent' => $discountPercent,
        ], fn ($v) => $v !== null))->all()];
        if ($agreedDpp !== null) {
            $payload['agreed_dpp'] = $agreedDpp;
        }

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", $payload)->assertRedirect();

        return [$sales, Quotation::firstOrFail()];
    }
}
