<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Sales\SalesOrderSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    public function test_partial_then_full_payment_updates_invoice_status(): void
    {
        $finance = $this->finance();
        $invoice = $this->upfrontInvoice('material_only'); // grand 2.600.000

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 1000000, 'paid_at' => now()->toDateTimeString(),
        ])->assertSessionHas('success');
        $this->assertSame('partially_paid', $invoice->fresh()->status);

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 1600000, 'paid_at' => now()->toDateTimeString(),
        ]);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_upfront_invoice_paid_gate(): void
    {
        $finance = $this->finance();
        $invoice = $this->upfrontInvoice('mixed'); // dp, grand 1.300.000
        $settlement = app(SalesOrderSettlement::class);
        $so = $invoice->salesOrder;

        $this->assertFalse($settlement->upfrontInvoicePaid($so->fresh()));

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 1300000, 'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($settlement->upfrontInvoicePaid($so->fresh()));
    }

    public function test_operational_is_notified_once_when_upfront_invoice_is_paid(): void
    {
        $finance = $this->finance();
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        User::factory()->create(['role' => 'operational', 'is_active' => false]);
        $invoice = $this->upfrontInvoice('mixed');

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 700000, 'paid_at' => now()->toDateTimeString(),
        ]);
        $this->assertSame(0, Notification::where('type', 'invoice.upfront_paid')->count());

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 600000, 'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ops->id, 'type' => 'invoice.upfront_paid',
        ]);
        $this->assertSame(1, Notification::where('type', 'invoice.upfront_paid')->count());
    }

    public function test_payment_proof_is_stored_as_attachment(): void
    {
        Storage::fake('local');
        $finance = $this->finance();
        $invoice = $this->upfrontInvoice('material_only');

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 2600000,
            'paid_at' => now()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
        ])->assertSessionHas('success');

        $attachment = \App\Models\Attachment::where('category', 'payment_proof')->firstOrFail();
        $this->assertSame(\App\Models\Payment::class, $attachment->attachable_type);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_material_only_full_payment_with_proof_triggers_won(): void
    {
        Storage::fake('local');
        $finance = $this->finance();
        $invoice = $this->upfrontInvoice('material_only');

        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 2600000,
            'paid_at' => now()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
        ]);

        $this->assertDatabaseHas('notifications', ['type' => 'sales_order.ready_to_win']);
    }

    public function test_payments_list_is_finance_only(): void
    {
        $this->actingAs($this->finance())->get('/finance/payments')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'sales']))->get('/finance/payments')->assertForbidden();
    }

    public function test_sales_cannot_record_payment(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $invoice = $this->upfrontInvoice('material_only');

        $this->actingAs($sales)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 100, 'paid_at' => now()->toDateTimeString(),
        ])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    private function upfrontInvoice(string $orderType): Invoice
    {
        $finance = $this->finance();
        $so = $this->confirmedSalesOrder($orderType);
        $phase = $orderType === 'material_only' ? 'full' : 'dp';

        $this->actingAs($finance)->post('/finance/invoices', [
            'sales_order_id' => $so->id, 'phase' => $phase,
        ]);

        return Invoice::with('salesOrder')->firstOrFail();
    }

    private function confirmedSalesOrder(string $orderType): SalesOrder
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
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload($orderType));

        return SalesOrder::with('lines')->firstOrFail();
    }
}
