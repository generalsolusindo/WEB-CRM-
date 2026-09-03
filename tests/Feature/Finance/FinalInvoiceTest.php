<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    public function test_final_invoice_rejected_for_material_only(): void
    {
        $finance = $this->finance();
        [$so] = $this->orderWithPaidDp('material_only');

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")
            ->assertSessionHasErrors('sales_order');
    }

    public function test_final_invoice_rejected_before_project_completed(): void
    {
        $finance = $this->finance();
        [$so] = $this->orderWithPaidDp('mixed');

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")
            ->assertSessionHasErrors('sales_order');
        $this->assertSame(1, Invoice::count()); // hanya DP
    }

    public function test_final_invoice_rejected_before_dp_paid(): void
    {
        $finance = $this->finance();
        [$so] = $this->confirmedOrder('mixed');
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")
            ->assertSessionHasErrors('sales_order');
    }

    public function test_final_invoice_bills_remaining_half_per_line(): void
    {
        $finance = $this->finance();
        [$so] = $this->orderWithPaidDp('mixed');
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")
            ->assertRedirect();

        $final = Invoice::where('invoice_phase', 'final')->with('lines')->firstOrFail();
        $this->assertSame('draft', $final->status);
        $this->assertMatchesRegularExpression('#^\d+/GS-INV/\d{2}/\d{4}$#', $final->number);
        $line = $final->lines->first();
        $this->assertSame('1300000.00', $line->subtotal);
        $this->assertStringContainsString('(Pelunasan)', $line->item_name);
        $this->assertSame('1300000.00', $final->amount);
    }

    public function test_final_paid_with_proof_lets_sales_close_as_won_once(): void
    {
        Storage::fake('local');
        $finance = $this->finance();
        [$so, $sales] = $this->orderWithPaidDp('mixed');
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice");
        $final = Invoice::where('invoice_phase', 'final')->firstOrFail();

        $this->actingAs($finance)->post("/finance/invoices/{$final->id}/payments", [
            'amount_paid' => 1300000,
            'paid_at' => now()->toDateTimeString(),
            'proof' => UploadedFile::fake()->create('final.pdf', 40, 'application/pdf'),
        ]);

        $this->assertSame(1, Notification::where('type', 'sales_order.ready_to_win')->count());

        $this->actingAs($sales)->post("/sales/sales-orders/{$so->id}/close-won")
            ->assertSessionHas('success');
        $this->assertSame('won', $so->fresh()->status);
    }

    /** @return array{SalesOrder, User} */
    private function orderWithPaidDp(string $orderType): array
    {
        [$so, $sales] = $this->confirmedOrder($orderType);
        $finance = $this->finance();
        $phase = $orderType === 'material_only' ? 'full' : 'dp';

        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => $phase]);
        $invoice = Invoice::firstOrFail();
        $grand = (float) $invoice->amount + (float) $invoice->tax_amount;
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => $grand, 'paid_at' => now()->toDateTimeString(),
        ]);

        return [$so->fresh(), $sales];
    }

    /** @return array{SalesOrder, User} */
    private function confirmedOrder(string $orderType): array
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

        return [SalesOrder::with('lines')->firstOrFail(), $sales];
    }
}
