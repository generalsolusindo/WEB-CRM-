<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePrintLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedOrder(string $orderType = 'material_only'): SalesOrder
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
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload($orderType));

        return SalesOrder::where('quotation_id', $q->id)->firstOrFail();
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    public function test_full_payment_invoice_shows_100_percent_and_final_report_term(): void
    {
        $so = $this->confirmedOrder('material_only');
        $finance = $this->finance();
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);
        $invoice = Invoice::where('sales_order_id', $so->id)->latest('id')->firstOrFail();

        $html = $this->actingAs($finance)->get("/finance/invoices/{$invoice->id}/print")->assertOk()->getContent();

        $this->assertStringContainsString('DP/Pelunasan', $html);
        $this->assertStringContainsString('100%', $html);
        $this->assertStringContainsString('Final Report akan diserahkan setelah pembayaran penuh', $html);
        $this->assertStringContainsString('text-align:right', $html);
    }

    public function test_dp_invoice_shows_dp_percent(): void
    {
        $so = $this->confirmedOrder('mixed');
        $finance = $this->finance();
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp', 'dp_percent' => 30]);
        $invoice = Invoice::where('sales_order_id', $so->id)->latest('id')->firstOrFail();

        $html = $this->actingAs($finance)->get("/finance/invoices/{$invoice->id}/print")->assertOk()->getContent();

        $this->assertStringContainsString('DP/Pelunasan', $html);
        $this->assertStringContainsString('30%', $html);
    }
}
