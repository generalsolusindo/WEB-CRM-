<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCreationTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    public function test_full_invoice_for_material_only(): void
    {
        $finance = $this->finance();
        $so = $this->confirmedSalesOrder('material_only');

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'full',
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('full', $invoice->invoice_phase);
        $this->assertMatchesRegularExpression('#^1/GS-INV/\d{2}/\d{4}$#', $invoice->number);
        $this->assertSame('2600000.00', $invoice->amount);
        $this->assertSame('2600000.00', $invoice->lines->first()->subtotal);
        $this->assertStringNotContainsString('DP', $invoice->lines->first()->item_name);
    }

    public function test_dp_invoice_for_mixed_is_half_per_line_with_tax(): void
    {
        $finance = $this->finance();
        $tax = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);
        $so = $this->confirmedSalesOrder('mixed', $tax->id);

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('dp', $invoice->invoice_phase);
        $line = $invoice->lines->first();
        $this->assertSame('1300000.00', $line->subtotal);
        $this->assertStringContainsString('(DP 50%)', $line->item_name);
        $this->assertEqualsWithDelta(143000.0, (float) $line->tax_amount, 0.01);
        $this->assertSame('1300000.00', $invoice->amount);
        $this->assertSame('143000.00', $invoice->tax_amount);
    }

    /**
     * Order jasa/campuran biasanya DP (pekerjaan baru mau dimulai), tapi Finance boleh pilih
     * Full manual kalau pekerjaan sudah dieksekusi/selesai duluan sebelum sempat ditagih
     * (mis. addendum yang langsung dikerjakan tanpa lewat penawaran).
     */
    public function test_full_invoice_can_be_chosen_manually_for_mixed_order(): void
    {
        $finance = $this->finance();
        $so = $this->confirmedSalesOrder('mixed');

        $this->actingAs($finance)->get("/finance/sales-orders/{$so->id}/invoices/create")
            ->assertInertia(fn ($page) => $page
                ->where('allowedPhases', [['value' => 'dp', 'label' => 'DP 50%'], ['value' => 'full', 'label' => 'Full 100%']])
                ->where('defaultPhase', 'dp'));

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'full',
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('full', $invoice->invoice_phase);
        $this->assertSame('2600000.00', $invoice->amount);
        $this->assertSame('2600000.00', $invoice->lines->first()->subtotal);
        $this->assertStringNotContainsString('DP', $invoice->lines->first()->item_name);
        $this->assertNull($so->fresh()->dp_percent);
    }

    public function test_wrong_phase_for_order_type_is_rejected(): void
    {
        $finance = $this->finance();
        $so = $this->confirmedSalesOrder('material_only');

        $this->actingAs($finance)->get("/finance/sales-orders/{$so->id}/invoices/create")
            ->assertInertia(fn ($page) => $page->where('allowedPhases', [['value' => 'full', 'label' => 'Full 100%']]));

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id,
            'phase' => 'dp',
        ])->assertSessionHasErrors('phase');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_cannot_create_second_upfront_invoice_but_can_after_cancel(): void
    {
        $finance = $this->finance();
        $so = $this->confirmedSalesOrder('mixed');

        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp'])
            ->assertSessionHasErrors('sales_order');
        $this->assertDatabaseCount('invoices', 1);

        $invoice = Invoice::firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/cancel")->assertSessionHas('success');

        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp'])
            ->assertRedirect();
        $this->assertSame(2, Invoice::count());
    }

    public function test_only_finance_can_create_invoice(): void
    {
        $so = $this->confirmedSalesOrder('material_only');

        foreach (['sales', 'procurement', 'operational'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post('/finance/invoices', [
                'sales_order_id' => $so->id, 'phase' => 'full',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_send_and_cancel_flow(): void
    {
        $finance = $this->finance();
        $so = $this->confirmedSalesOrder('material_only');
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);
        $invoice = Invoice::firstOrFail();

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/send")->assertSessionHas('success');
        $this->assertSame('sent', $invoice->fresh()->status);

        Payment::create(['invoice_id' => $invoice->id, 'amount_paid' => 100, 'paid_at' => now()]);
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/cancel")->assertForbidden();
    }

    private function confirmedSalesOrder(string $orderType, ?int $taxId = null): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create([
            'item_name' => 'Router', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id,
        ]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available', 'tax_id' => $taxId]);
        $pr->update(['status' => 'ready']);
        $line = $pr->lines()->first();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $line->id, 'selling_price' => 1300000, 'tax_id' => $taxId]],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload($orderType));

        return SalesOrder::with('lines')->firstOrFail();
    }
}
