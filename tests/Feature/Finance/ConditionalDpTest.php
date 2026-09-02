<?php

namespace Tests\Feature\Finance;

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

class ConditionalDpTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    /** SO mixed dengan 1 line: qty 2 x 1.300.000 = 2.600.000 */
    private function confirmedSalesOrder(): SalesOrder
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
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));

        return SalesOrder::firstOrFail();
    }

    public function test_dp_defaults_to_50_percent_when_not_supplied(): void
    {
        $so = $this->confirmedSalesOrder();

        $this->actingAs($this->finance())->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => 'dp',
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('1300000.00', $invoice->amount);
        $this->assertSame('50.00', $so->fresh()->dp_percent);
        $this->assertStringContainsString('(DP 50%)', $invoice->lines->first()->item_name);
    }

    public function test_finance_sets_custom_dp_percent(): void
    {
        $so = $this->confirmedSalesOrder();

        $this->actingAs($this->finance())->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => 'dp', 'dp_percent' => 30,
        ])->assertRedirect();

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertSame('780000.00', $invoice->amount);           // 30% x 2.6jt
        $this->assertSame('30.00', $so->fresh()->dp_percent);
        $this->assertStringContainsString('(DP 30%)', $invoice->lines->first()->item_name);
    }

    public function test_final_invoice_bills_exact_remaining_after_custom_dp(): void
    {
        $finance = $this->finance();
        $so = $this->confirmedSalesOrder();

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => 'dp', 'dp_percent' => 30,
        ]);
        $dp = Invoice::firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$dp->id}/payments", [
            'amount_paid' => (float) $dp->amount + (float) $dp->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")->assertRedirect();

        $final = Invoice::where('invoice_phase', 'final')->with('lines')->firstOrFail();
        // sisa = 2.6jt - 780rb (DP) = 1.82jt
        $this->assertSame('1820000.00', $final->amount);
        $this->assertSame('1820000.00', $final->lines->first()->subtotal);
    }

    public function test_dp_percent_out_of_bounds_rejected(): void
    {
        $so = $this->confirmedSalesOrder();

        $this->actingAs($this->finance())->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => 'dp', 'dp_percent' => 150,
        ])->assertSessionHasErrors('dp_percent');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_dp_percent_ignored_for_material_only_full_invoice(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'C', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified']);
        $lead->requirements()->create(['item_name' => 'X', 'qty' => 2, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $q = Quotation::firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('material_only'));
        $so = SalesOrder::firstOrFail();

        $this->actingAs($this->finance())->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => 'full', 'dp_percent' => 30,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('2600000.00', $invoice->amount); // 100%, dp_percent diabaikan
        $this->assertNull($so->fresh()->dp_percent);
    }
}
