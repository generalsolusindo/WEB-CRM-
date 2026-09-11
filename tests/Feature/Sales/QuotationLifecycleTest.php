<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_is_built_from_ready_procurement_request(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)
            ->post("/sales/procurement-requests/{$pr->id}/quotations", [
                'lines' => [
                    ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000],
                ],
            ])
            ->assertRedirect();

        $quotation = Quotation::with('lines')->firstOrFail();
        $this->assertSame('draft', $quotation->status);
        $this->assertSame(1, $quotation->revision_number);

        $this->assertMatchesRegularExpression('#^1/GS-PN/\d{2}/\d{4}$#', $quotation->number);
        $this->assertSame(now()->addDays(10)->toDateString(), $quotation->valid_until->toDateString());

        $quotationLine = $quotation->lines->firstOrFail();
        $this->assertSame('1000000.00', $quotationLine->cost_price);
        $this->assertSame('1300000.00', $quotationLine->selling_price);
        $this->assertSame('30.00', $quotationLine->markup_percent);
        $this->assertSame('2600000.00', $quotationLine->subtotal);
        $this->assertSame($line->id, $quotationLine->procurement_request_line_id);
    }

    public function test_quotation_print_view_renders_for_owner_only(): void
    {
        [$sales, $quotation] = $this->draftQuotation();

        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}/print")
            ->assertOk()
            ->assertSee($quotation->number)
            ->assertSee('THANK YOU FOR YOUR BUSINESS!');

        $other = User::factory()->create(['role' => 'sales']);
        $this->actingAs($other)->get("/sales/quotations/{$quotation->id}/print")->assertForbidden();
    }

    public function test_quotation_cannot_be_created_from_non_ready_request(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $pr->update(['status' => 'searching']);
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)
            ->post("/sales/procurement-requests/{$pr->id}/quotations", [
                'lines' => [
                    ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_quotation_lines_must_match_procurement_request_exactly(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)
            ->post("/sales/procurement-requests/{$pr->id}/quotations", [
                'lines' => [
                    ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000],
                    ['procurement_request_line_id' => $line->id + 999, 'selling_price' => 50000],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_only_draft_quotation_can_be_updated(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1500000],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('1500000.00', $quotation->lines()->first()->selling_price);
        $this->assertSame('50.00', $quotation->lines()->first()->markup_percent);

        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1600000],
                ],
            ])
            ->assertForbidden();
    }

    public function test_revision_chain_is_limited_to_one_child(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/revisions")
            ->assertRedirect();

        $revision = Quotation::where('parent_quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame(2, $revision->revision_number);
        $this->assertSame("{$quotation->number}-R2", $revision->number);
        $this->assertSame('revised', $quotation->fresh()->status);
        $this->assertSame('draft', $revision->status);

        // Parent already has a revision -> a second attempt must fail.
        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/revisions")
            ->assertForbidden();

        $this->assertSame(1, Quotation::where('parent_quotation_id', $quotation->id)->count());
    }

    public function test_sales_order_is_created_only_from_sent_quotation_with_derived_payment_rule(): void
    {
        [$sales, $quotation] = $this->draftQuotation();

        // Draft quotation cannot be confirmed.
        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("mixed"))
            ->assertForbidden();

        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("material_only"))
            ->assertRedirect();

        $salesOrder = SalesOrder::with('lines')->firstOrFail();
        $this->assertMatchesRegularExpression('/^SO-\d{4}-0001$/', $salesOrder->number);
        $this->assertSame('material_only', $salesOrder->order_type);
        $this->assertSame('full_100', $salesOrder->payment_rule);
        $this->assertSame('confirmed', $salesOrder->status);
        $this->assertCount(1, $salesOrder->lines);
        $this->assertSame('confirmed', $quotation->fresh()->status);

        // Cannot confirm twice.
        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("material_only"))
            ->assertForbidden();

        $this->assertDatabaseCount('sales_orders', 1);
    }

    public function test_service_order_derives_dp_50_payment_rule(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload("service_only"))
            ->assertRedirect();

        $this->assertSame('dp_50', SalesOrder::firstOrFail()->payment_rule);
    }

    public function test_close_as_won_requires_paid_settlement_invoice_with_payment_proof(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('mixed');

        // No settlement invoice yet.
        $this->actingAs($sales)
            ->post("/sales/sales-orders/{$salesOrder->id}/close-won")
            ->assertSessionHasErrors('payment');

        $invoice = Invoice::create([
            'sales_order_id' => $salesOrder->id,
            'invoice_phase' => 'final',
            'status' => 'paid',
            'amount' => 2600000,
            'tax_amount' => 0,
        ]);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount_paid' => 2600000,
            'paid_at' => now(),
        ]);

        // Paid invoice but still missing payment proof.
        $this->actingAs($sales)
            ->post("/sales/sales-orders/{$salesOrder->id}/close-won")
            ->assertSessionHasErrors('payment');

        $payment->attachments()->create([
            'category' => 'payment_proof',
            'file_path' => 'proofs/pay.pdf',
        ]);

        $this->actingAs($sales)
            ->post("/sales/sales-orders/{$salesOrder->id}/close-won")
            ->assertSessionHas('success');

        $this->assertSame('won', $salesOrder->fresh()->status);
        $this->assertSame('won', $salesOrder->quotation->lead->fresh()->stage);

        // Sudah won -> tidak bisa di-close lagi.
        $this->actingAs($sales)
            ->post("/sales/sales-orders/{$salesOrder->id}/close-won")
            ->assertForbidden();
    }

    public function test_sales_role_cannot_create_invoices_through_any_route(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $invoiceCreateRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'invoice'))
            ->filter(fn ($route) => in_array('POST', $route->methods(), true));

        foreach ($invoiceCreateRoutes as $route) {
            $status = $this->actingAs($sales)->post('/'.ltrim($route->uri(), '/'))->status();
            $this->assertContains(
                $status,
                [403, 404, 405],
                "Route {$route->uri()} tidak boleh dapat dipakai oleh role sales untuk membuat invoice.",
            );
        }

        $salesAreaInvoiceRoutes = $invoiceCreateRoutes
            ->filter(fn ($route) => str_starts_with($route->uri(), 'sales/'));
        $this->assertCount(0, $salesAreaInvoiceRoutes, 'Tidak boleh ada route pembuatan invoice di area sales.');
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
            'description' => 'Dual WAN',
            'qty' => 2,
            'unit' => 'unit',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update([
            'cost_price' => 1000000,
            'availability_status' => 'available',
        ]);
        $pr->update(['status' => 'ready']);

        return [$sales, $pr->fresh('lines')];
    }

    /** @return array{User, Quotation} */
    private function draftQuotation(): array
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000],
            ],
        ]);

        return [$sales, Quotation::with('lines')->firstOrFail()];
    }

    /** @return array{User, SalesOrder} */
    private function confirmedSalesOrder(string $orderType): array
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload($orderType));

        return [$sales, SalesOrder::with('quotation.lead')->firstOrFail()];
    }
}
