<?php

namespace Tests\Feature\Finance;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Payment;
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

        $attachment = Attachment::where('category', 'payment_proof')->firstOrFail();
        $this->assertSame(Payment::class, $attachment->attachable_type);
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

    public function test_cancel_payment_preserves_dp_proof_and_project_and_recalculates_every_total(): void
    {
        $invoice = $this->upfrontInvoice('material_only');
        $ops = User::factory()->create(['role' => 'operational']);
        $this->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 500000, 'paid_at' => now()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('dp.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('success');
        $dp = $invoice->payments()->firstOrFail();
        $proofPath = $dp->attachments()->firstOrFail()->file_path;
        $this->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 2100000, 'paid_at' => now()->toDateTimeString(),
        ])->assertSessionHas('success');
        $wrong = $invoice->payments()->latest('id')->firstOrFail();
        $project = $invoice->salesOrder->projects()->firstOrFail();
        $financeId = auth()->id();

        $this->post("/finance/invoices/{$invoice->id}/payments/{$wrong->id}/cancel", [
            'reason' => 'Salah input pelunasan, baru DP.',
        ])->assertSessionHas('success');

        $this->assertSoftDeleted('payments', ['id' => $wrong->id]);
        $this->assertDatabaseHas('payments', ['id' => $wrong->id, 'cancelled_by' => $financeId, 'cancellation_reason' => 'Salah input pelunasan, baru DP.']);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertEquals(500000, $invoice->fresh()->totalPaid());
        $this->assertEquals(500000, Invoice::withSum('payments', 'amount_paid')->findOrFail($invoice->id)->payments_sum_amount_paid);
        $this->assertSame(1, $invoice->payments()->count());
        Storage::disk('local')->assertExists($proofPath);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => $project->status]);
        $this->assertDatabaseMissing('notifications', ['type' => 'sales_order.ready_to_win']);
        $this->assertDatabaseHas('notifications', ['type' => 'invoice.payment_cancelled', 'user_id' => $ops->id]);
        $this->get("/finance/invoices/{$invoice->id}")->assertInertia(fn ($page) => $page
            ->has('payments', 1)->has('cancelledPayments', 1)
            ->where('cancelledPayments.0.reason', 'Salah input pelunasan, baru DP.'));
        $this->get('/finance/payments')->assertInertia(fn ($page) => $page->has('payments.data', 1));

        // Re-recording the real settlement restores eligibility without duplicating the project.
        $this->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 2100000, 'paid_at' => now()->toDateTimeString(),
        ])->assertSessionHas('success');
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, $invoice->salesOrder->projects()->count());
        $this->assertDatabaseHas('notifications', ['type' => 'sales_order.ready_to_win', 'read_at' => null]);
        $this->assertDatabaseMissing('notifications', ['type' => 'invoice.payment_cancelled', 'read_at' => null]);
    }

    public function test_cancel_only_payment_returns_to_sent_and_retains_cancelled_proof(): void
    {
        $invoice = $this->upfrontInvoice('material_only');
        $this->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => 100000, 'paid_at' => now()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('wrong.pdf', 10, 'application/pdf'),
        ]);
        $payment = $invoice->payments()->firstOrFail();
        $path = $payment->attachments()->firstOrFail()->file_path;
        $url = "/finance/invoices/{$invoice->id}/payments/{$payment->id}/cancel";
        $this->post($url, ['reason' => 'Salah invoice'])->assertSessionHas('success');
        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->totalPaid());
        Storage::disk('local')->assertExists($path);
        $this->post($url, ['reason' => 'Ulang'])->assertNotFound();
    }

    public function test_cancel_payment_requires_finance_reason_and_matching_invoice(): void
    {
        $invoice = $this->upfrontInvoice('material_only');
        $finance = auth()->user();
        $payment = $invoice->payments()->create(['amount_paid' => 100, 'paid_at' => now(), 'recorded_by' => $finance->id]);
        $url = "/finance/invoices/{$invoice->id}/payments/{$payment->id}/cancel";
        $this->post($url, ['reason' => '   '])->assertSessionHasErrors('reason');
        $other = Invoice::create(['sales_order_id' => $invoice->sales_order_id, 'invoice_phase' => 'final', 'status' => 'draft', 'amount' => 100, 'tax_amount' => 0, 'created_by' => $finance->id]);
        $this->post("/finance/invoices/{$other->id}/payments/{$payment->id}/cancel", ['reason' => 'Salah'])->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'sales']))->post($url, ['reason' => 'Salah'])->assertForbidden();
        $finance->update(['is_active' => false]);
        $this->actingAs($finance)->post($url, ['reason' => 'Salah'])->assertForbidden();
        $this->assertNotSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_cancel_payment_blocks_closed_order_and_cancelled_invoice(): void
    {
        $invoice = $this->upfrontInvoice('material_only');
        $payment = $invoice->payments()->create(['amount_paid' => 100, 'paid_at' => now(), 'recorded_by' => auth()->id()]);
        $invoice->salesOrder->update(['status' => 'won']);
        $url = "/finance/invoices/{$invoice->id}/payments/{$payment->id}/cancel";
        $this->post($url, ['reason' => 'Salah'])->assertSessionHasErrors('reason');
        $invoice->update(['status' => 'cancelled']);
        $this->post($url, ['reason' => 'Salah'])->assertForbidden();
        $this->assertNotSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_cancel_dp_payment_blocks_existing_final_invoice(): void
    {
        $invoice = $this->upfrontInvoice('mixed');
        $payment = $invoice->payments()->create(['amount_paid' => 1300000, 'paid_at' => now(), 'recorded_by' => auth()->id()]);
        $invoice->update(['status' => 'paid']);
        Invoice::create(['sales_order_id' => $invoice->sales_order_id, 'invoice_phase' => 'final', 'status' => 'draft', 'amount' => 1300000, 'tax_amount' => 0, 'created_by' => auth()->id()]);
        $this->post("/finance/invoices/{$invoice->id}/payments/{$payment->id}/cancel", ['reason' => 'Salah'])->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_cancel_excess_payment_keeps_invoice_paid_with_pph23(): void
    {
        $invoice = $this->upfrontInvoice('mixed');
        $invoice->update(['pph23_enabled' => true, 'pph23_amount' => 20000, 'status' => 'paid']);
        $invoice->payments()->create(['amount_paid' => 1280000, 'paid_at' => now(), 'recorded_by' => auth()->id()]);
        $excess = $invoice->payments()->create(['amount_paid' => 50000, 'paid_at' => now(), 'recorded_by' => auth()->id()]);
        $this->post("/finance/invoices/{$invoice->id}/payments/{$excess->id}/cancel", ['reason' => 'Duplikat'])->assertSessionHas('success');
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(1300000, $invoice->fresh()->settledAmount());
    }

    public function test_cancel_final_payment_does_not_change_paid_dp_or_invoice_phase(): void
    {
        $dp = $this->upfrontInvoice('mixed');
        $this->post("/finance/invoices/{$dp->id}/payments", [
            'amount_paid' => 1300000, 'paid_at' => now()->toDateTimeString(),
        ])->assertSessionHas('success');
        $final = Invoice::create(['sales_order_id' => $dp->sales_order_id, 'invoice_phase' => 'final', 'status' => 'sent', 'amount' => 1300000, 'tax_amount' => 0, 'created_by' => auth()->id()]);
        $this->post("/finance/invoices/{$final->id}/payments", [
            'amount_paid' => 1300000, 'paid_at' => now()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('final.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('success');
        $payment = $final->payments()->firstOrFail();
        $this->post("/finance/invoices/{$final->id}/payments/{$payment->id}/cancel", ['reason' => 'Belum ada pelunasan'])->assertSessionHas('success');
        $this->assertSame('paid', $dp->fresh()->status);
        $this->assertEquals(1300000, $dp->fresh()->totalPaid());
        $this->assertSame('sent', $final->fresh()->status);
        $this->assertSame('final', $final->fresh()->invoice_phase);
        $this->assertDatabaseMissing('notifications', ['type' => 'sales_order.ready_to_win']);
    }

    public function test_survey_payments_cannot_use_sales_payment_cancellation(): void
    {
        $invoice = $this->upfrontInvoice('material_only');
        $invoice->update(['invoice_type' => 'survey']);
        $payment = $invoice->payments()->create(['amount_paid' => 100, 'paid_at' => now(), 'recorded_by' => auth()->id()]);
        $this->post("/finance/invoices/{$invoice->id}/payments/{$payment->id}/cancel", ['reason' => 'Salah'])->assertForbidden();
        $this->assertNotSoftDeleted('payments', ['id' => $payment->id]);
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
