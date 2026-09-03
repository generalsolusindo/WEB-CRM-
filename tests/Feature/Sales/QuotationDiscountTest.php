<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_discount_fills_amount_and_reduces_subtotal(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 1300000,
                'discount_percent' => 10,
            ]],
        ])->assertRedirect();

        $quotationLine = Quotation::firstOrFail()->lines()->firstOrFail();
        $this->assertSame('10.00', $quotationLine->discount_percent);
        $this->assertSame('260000.00', $quotationLine->discount_amount);
        $this->assertSame('2340000.00', $quotationLine->subtotal);
        // markup pre-diskon = (2.6jt - 2jt) / 2jt
        $this->assertEqualsWithDelta(30.0, (float) $quotationLine->markup_percent, 0.01);
        // margin efektif = (2.34jt - 2jt) / 2jt
        $this->assertEqualsWithDelta(17.0, (float) $quotationLine->effective_margin_percent, 0.01);
    }

    public function test_rupiah_discount_backfills_percent(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 1300000,
                'discount_amount' => 260000,
            ]],
        ])->assertRedirect();

        $quotationLine = Quotation::firstOrFail()->lines()->firstOrFail();
        $this->assertSame('260000.00', $quotationLine->discount_amount);
        $this->assertSame('10.00', $quotationLine->discount_percent);
        $this->assertSame('2340000.00', $quotationLine->subtotal);
    }

    public function test_editable_tax_rate_is_stored_and_computed(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 1300000,
                'discount_percent' => 10,
                'tax_rate' => 11,
            ]],
        ])->assertRedirect();

        $quotationLine = Quotation::firstOrFail()->lines()->firstOrFail();
        $this->assertSame('11.00', $quotationLine->tax_rate);
        // 2.340.000 * 11%
        $this->assertEqualsWithDelta(257400.0, (float) $quotationLine->tax_amount, 0.01);
    }

    public function test_quick_pick_tax_id_seeds_rate_when_not_supplied(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();
        $ppn = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 1300000,
                'tax_id' => $ppn->id,
            ]],
        ])->assertRedirect();

        $quotationLine = Quotation::firstOrFail()->lines()->firstOrFail();
        $this->assertSame($ppn->id, $quotationLine->tax_id);
        $this->assertSame('11.00', $quotationLine->tax_rate);
    }

    public function test_discount_and_tax_rate_flow_into_sales_order(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 1300000,
                'discount_percent' => 10,
                'tax_rate' => 11,
            ]],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("material_only"));

        $soLine = SalesOrder::firstOrFail()->lines()->firstOrFail();
        $this->assertSame('260000.00', $soLine->discount_amount);
        $this->assertSame('10.00', $soLine->discount_percent);
        $this->assertSame('11.00', $soLine->tax_rate);
        $this->assertSame('2340000.00', $soLine->subtotal);
    }

    public function test_dp_invoice_carries_proportional_discount(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->confirmedDiscountedOrder('mixed');

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
        ])->assertRedirect();

        $line = Invoice::with('lines')->firstOrFail()->lines->first();
        $this->assertSame('1170000.00', $line->subtotal);   // 2.34jt / 2
        $this->assertSame('130000.00', $line->discount_amount); // 260rb / 2
        $this->assertSame('650000.00', $line->unit_price);  // (1.17jt + 130rb) / 2
    }

    public function test_final_invoice_bills_remaining_discount(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $so = $this->confirmedDiscountedOrder('mixed');

        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $dp = Invoice::firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$dp->id}/payments", [
            'amount_paid' => (float) $dp->amount + (float) $dp->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);
        Project::whereBelongsTo($so)->update(['status' => 'completed']);

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")->assertRedirect();

        $line = Invoice::where('invoice_phase', 'final')->with('lines')->firstOrFail()->lines->first();
        $this->assertSame('1170000.00', $line->subtotal);
        $this->assertSame('130000.00', $line->discount_amount);
    }

    public function test_agreed_dpp_distributes_discount_pro_rata_and_flows_to_sales_order(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'category' => 'material', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $lead->requirements()->create(['item_name' => 'Jasa Instalasi', 'category' => 'service', 'qty' => 1, 'unit' => 'lot', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $lines = $pr->lines->sortBy('id')->values();

        // bruto = 6.000.000 + 4.000.000 = 10.000.000 ; nilai DPP disepakati 8.000.000 -> diskon 20%
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'agreed_dpp' => 8000000,
            'lines' => [
                ['procurement_request_line_id' => $lines[0]->id, 'selling_price' => 6000000, 'discount_percent' => 50],
                ['procurement_request_line_id' => $lines[1]->id, 'selling_price' => 4000000],
            ],
        ])->assertRedirect();

        $quotation = Quotation::with('lines')->firstOrFail();
        $this->assertSame('8000000.00', $quotation->agreed_dpp);
        $qLines = $quotation->lines->sortBy('id')->values();
        $this->assertSame('4800000.00', $qLines[0]->subtotal);   // 6jt * 0.8 — diskon per-baris 50% diabaikan
        $this->assertSame('1200000.00', $qLines[0]->discount_amount);
        $this->assertSame('3200000.00', $qLines[1]->subtotal);   // 4jt * 0.8
        $this->assertSame(8000000.0, $quotation->lines->sum(fn ($l) => (float) $l->subtotal));

        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));
        $so = SalesOrder::firstOrFail();
        $this->assertSame('8000000.00', $so->agreed_dpp);
        $this->assertSame(8000000.0, $so->lines->sum(fn ($l) => (float) $l->subtotal));
    }

    private function confirmedDiscountedOrder(string $orderType): SalesOrder
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 1300000,
                'discount_percent' => 10,
            ]],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload($orderType));

        return SalesOrder::with('lines')->firstOrFail();
    }

    /** @return array{User, ProcurementRequest} */
    private function readyProcurementRequest(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'opportunity',
            'stage' => 'qualified',
        ]);
        $lead->requirements()->create([
            'item_name' => 'Router Enterprise',
            'qty' => 2,
            'unit' => 'unit',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);

        return [$sales, $pr->fresh('lines')];
    }
}
