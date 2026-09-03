<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_tax_amount_is_computed_from_rate(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();
        $ppn = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000, 'tax_id' => $ppn->id],
            ],
        ])->assertRedirect();

        $quotationLine = Quotation::firstOrFail()->lines()->with('tax')->firstOrFail();
        $this->assertSame($ppn->id, $quotationLine->tax_id);
        $this->assertSame('2600000.00', $quotationLine->subtotal);
        $this->assertEqualsWithDelta(286000.0, (float) $quotationLine->tax_amount, 0.001);
        $this->assertArrayHasKey('tax_amount', $quotationLine->toArray());
    }

    public function test_line_without_tax_has_zero_tax_amount(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000, 'tax_id' => null],
            ],
        ])->assertRedirect();

        $quotationLine = Quotation::firstOrFail()->lines()->firstOrFail();
        $this->assertNull($quotationLine->tax_id);
        $this->assertSame(0.0, (float) $quotationLine->tax_amount);
    }

    public function test_sales_can_change_tax_on_draft_quotation(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();
        $ppn = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}", [
            'lines' => [
                [
                    'procurement_request_line_id' => $line->procurement_request_line_id,
                    'selling_price' => 1300000,
                    'tax_id' => $ppn->id,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame($ppn->id, $quotation->lines()->firstOrFail()->tax_id);
    }

    public function test_inactive_or_unknown_tax_is_rejected(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();
        $inactive = Tax::create(['name' => 'PPN lama', 'rate' => 10, 'is_active' => false]);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000, 'tax_id' => $inactive->id],
            ],
        ])->assertSessionHasErrors('lines.0.tax_id');

        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_sales_order_line_inherits_tax_from_quotation(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();
        $ppn = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000, 'tax_id' => $ppn->id],
            ],
        ]);
        $quotation = Quotation::firstOrFail();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('material_only'))
            ->assertRedirect();

        $soLine = SalesOrder::firstOrFail()->lines()->with('tax')->firstOrFail();
        $this->assertSame($ppn->id, $soLine->tax_id);
        $this->assertEqualsWithDelta(286000.0, (float) $soLine->tax_amount, 0.001);
    }

    public function test_non_administrator_cannot_manage_taxes(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($sales)->get('/admin/taxes')->assertForbidden();
        $this->actingAs($sales)->post('/admin/taxes', ['name' => 'X', 'rate' => 5])->assertForbidden();
        $this->actingAs($admin)->get('/admin/taxes')->assertOk();
    }

    public function test_administrator_can_create_update_and_delete_tax(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin)->post('/admin/taxes', [
            'name' => 'PPN 11%',
            'rate' => 11,
            'is_active' => true,
        ])->assertRedirect('/admin/taxes');
        $tax = Tax::firstOrFail();
        $this->assertSame('11.00', $tax->rate);

        $this->actingAs($admin)->put("/admin/taxes/{$tax->id}", [
            'name' => 'PPN 12%',
            'rate' => 12,
            'is_active' => false,
        ])->assertRedirect('/admin/taxes');
        $this->assertSame('12.00', $tax->fresh()->rate);
        $this->assertFalse($tax->fresh()->is_active);

        $this->actingAs($admin)->delete("/admin/taxes/{$tax->id}")->assertRedirect('/admin/taxes');
        $this->assertDatabaseCount('taxes', 0);
    }

    public function test_tax_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();
        $ppn = Tax::create(['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $line->id, 'selling_price' => 1300000, 'tax_id' => $ppn->id],
            ],
        ]);

        $this->actingAs($admin)->delete("/admin/taxes/{$ppn->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('taxes', ['id' => $ppn->id]);
    }

    public function test_document_totals_estimates_pph23_from_service_lines_only(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $line->id, 'selling_price' => 1000000, 'category' => 'service'],
            ],
        ]);
        $quotation = Quotation::with('lines')->firstOrFail();

        $totals = \App\Services\Sales\DocumentTotals::of($quotation->lines);
        $this->assertSame(2000000.0, $totals['service_dpp']);   // 2 unit x 1jt
        $this->assertSame(40000.0, $totals['pph23_estimate']);  // 2% x 2jt

        // baris material tidak masuk estimasi
        $quotation->lines()->update(['category' => 'material']);
        $totals = \App\Services\Sales\DocumentTotals::of($quotation->fresh('lines')->lines);
        $this->assertSame(0.0, $totals['pph23_estimate']);
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
}
