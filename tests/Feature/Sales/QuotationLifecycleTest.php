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

    public function test_terms_default_to_standard_text_when_left_blank(): void
    {
        [, $quotation] = $this->draftQuotation();

        $this->assertStringContainsString('Price Include Tax', $quotation->terms);
        $this->assertStringContainsString('Payment DP 50%', $quotation->terms);

        $res = $this->actingAs($quotation->sales)->get("/sales/quotations/{$quotation->id}/print");
        $res->assertOk()->assertSee('Price Include Tax')->assertSee('Warranty Services 1 Month');
    }

    public function test_sales_can_customize_terms_on_create_and_edit(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $line->id, 'selling_price' => 1300000]],
            'terms' => "Harga sudah termasuk pajak\nDP 30% di muka",
        ])->assertRedirect();

        $quotation = Quotation::with('lines')->firstOrFail();
        $this->assertSame("Harga sudah termasuk pajak\nDP 30% di muka", $quotation->terms);

        $res = $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}/print");
        $res->assertOk()->assertSee('DP 30% di muka')->assertDontSee('Payment DP 50%');

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}", [
            'terms' => 'Syarat baru saja',
            'lines' => $quotation->lines->map(fn ($l) => [
                'procurement_request_line_id' => $l->procurement_request_line_id,
                'selling_price' => $l->selling_price,
            ])->all(),
        ])->assertRedirect();

        $this->assertSame('Syarat baru saja', $quotation->fresh()->terms);
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

    public function test_draft_quotation_can_be_updated(): void
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
    }

    /**
     * Quotation yang diedit dianggap penawaran baru sejak hari itu — "Berlaku Sampai"
     * harus ikut dihitung ulang dari hari edit, bukan tetap memakai tanggal lama yang
     * dihitung dari saat quotation pertama kali dibuat.
     */
    public function test_editing_recalculates_valid_until_from_today(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['valid_until' => now()->subDays(20)->toDateString()]);
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1500000],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(now()->addDays(10)->toDateString(), $quotation->fresh()->valid_until->toDateString());
    }

    /**
     * "Date" di cetakan quotation (quoted_at) harus ikut terhitung ulang saat diedit — beda
     * dengan created_at (tetap tanggal dibuat pertama kali) dan updated_at (ikut berubah oleh
     * aksi lain seperti review PM/Manager, jadi tidak bisa dipakai untuk ini).
     */
    public function test_editing_recalculates_quoted_at_and_reflects_on_print(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['quoted_at' => now()->subDays(20)->toDateString()]);
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1500000],
                ],
            ])
            ->assertRedirect();

        $fresh = $quotation->fresh();
        $this->assertSame(now()->toDateString(), $fresh->quoted_at->toDateString());

        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}/print")
            ->assertOk()
            ->assertSee(now()->format('d/m/Y'));
    }

    /** Unit lama di luar daftar baku (mis. dari data lawas) tidak boleh menghalangi edit lain. */
    public function test_editing_with_legacy_unit_value_still_succeeds(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();
        $line->update(['unit' => 'Unit']); // nilai lama, di luar Requirement::UNITS (huruf besar, bukan format baku)

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    [
                        'procurement_request_line_id' => $line->procurement_request_line_id,
                        'unit' => 'Unit',
                        'selling_price' => 1450000,
                    ],
                ],
            ])
            ->assertRedirect();

        $fresh = $quotation->lines()->firstOrFail();
        $this->assertSame('Unit', $fresh->unit);
        $this->assertSame('1450000.00', $fresh->selling_price);
    }

    public function test_editing_can_change_item_name_qty_and_unit(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    [
                        'procurement_request_line_id' => $line->procurement_request_line_id,
                        'item_name' => 'Router Enterprise (revisi nama)',
                        'qty' => 4,
                        'unit' => 'unit',
                        'selling_price' => 1300000,
                    ],
                ],
            ])
            ->assertRedirect();

        $fresh = $quotation->lines()->first()->fresh();
        $this->assertSame('Router Enterprise (revisi nama)', $fresh->item_name);
        $this->assertSame('4.00', $fresh->qty);
        $this->assertSame('unit', $fresh->unit);
        // subtotal harus ikut dihitung ulang pakai qty baru (4 x 1300000)
        $this->assertSame('5200000.00', $fresh->subtotal);
    }

    public function test_editing_without_item_name_qty_unit_keeps_existing_values(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();
        $originalName = $line->item_name;
        $originalQty = $line->qty;
        $originalUnit = $line->unit;

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1400000],
                ],
            ])
            ->assertRedirect();

        $fresh = $quotation->lines()->firstOrFail();
        $this->assertSame($originalName, $fresh->item_name);
        $this->assertSame($originalQty, $fresh->qty);
        $this->assertSame($originalUnit, $fresh->unit);
    }

    public function test_editing_can_add_a_new_line_not_from_procurement(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1300000],
                    [
                        'procurement_request_line_id' => null,
                        'item_name' => 'Kabel Tambahan',
                        'qty' => 2,
                        'unit' => 'meter',
                        'category' => 'material',
                        'cost_price' => 20000,
                        'selling_price' => 30000,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $quotation->lines()->count());
        $newLine = $quotation->lines()->where('item_name', 'Kabel Tambahan')->firstOrFail();
        $this->assertNull($newLine->procurement_request_line_id);
        $this->assertSame('20000.00', $newLine->cost_price);
        $this->assertSame('30000.00', $newLine->selling_price);
        $this->assertSame('60000.00', $newLine->subtotal);
    }

    public function test_adding_a_new_line_without_cost_price_is_rejected(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();
        $originalCount = $quotation->lines()->count();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1300000],
                    [
                        'procurement_request_line_id' => null,
                        'item_name' => 'Item Tanpa Harga Beli',
                        'qty' => 1,
                        'unit' => 'unit',
                        'category' => 'material',
                        'selling_price' => 50000,
                    ],
                ],
            ])
            ->assertSessionHasErrors('lines.1.cost_price');

        $this->assertSame($originalCount, $quotation->fresh()->lines()->count());
    }

    public function test_a_new_line_cannot_supply_its_own_procurement_request_line_id_from_elsewhere(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();

        // Quotation kedua yang sepenuhnya lepas dari helper draftQuotation()/readyProcurementRequest()
        // (keduanya pakai firstOrFail() tanpa scope, jadi tidak aman dipanggil dua kali di test yang sama).
        $otherSales = User::factory()->create(['role' => 'sales']);
        $otherContact = Contact::create(['name' => 'Customer Lain', 'created_by' => $otherSales->id]);
        $otherLead = Lead::create([
            'contact_id' => $otherContact->id, 'sales_id' => $otherSales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $otherLead->requirements()->create(['item_name' => 'Kabel Lain', 'qty' => 1, 'unit' => 'unit', 'created_by' => $otherSales->id]);
        $this->actingAs($otherSales)->post("/sales/leads/{$otherLead->id}/submit-procurement");
        $otherPr = ProcurementRequest::where('lead_id', $otherLead->id)->with('lines')->firstOrFail();
        $otherPr->lines()->update(['cost_price' => 500000, 'availability_status' => 'available']);
        $otherPr->update(['status' => 'ready']);
        $this->actingAs($otherSales)->post("/sales/procurement-requests/{$otherPr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $otherPr->lines()->first()->id, 'selling_price' => 700000]],
        ]);
        $otherQuotation = Quotation::where('lead_id', $otherLead->id)->with('lines')->firstOrFail();
        $otherLine = $otherQuotation->lines()->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1300000],
                    ['procurement_request_line_id' => $otherLine->procurement_request_line_id, 'selling_price' => 1300000],
                ],
            ])
            ->assertSessionHasErrors('lines.1.procurement_request_line_id');
    }

    public function test_editing_can_delete_an_existing_line_leaving_at_least_one(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();

        // Tambah satu line dulu supaya ada 2 baris, baru hapus salah satunya.
        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}", [
            'lines' => [
                ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1300000],
                [
                    'procurement_request_line_id' => null, 'item_name' => 'Item Kedua', 'qty' => 1,
                    'unit' => 'unit', 'category' => 'material', 'cost_price' => 10000, 'selling_price' => 15000,
                ],
            ],
        ]);
        $this->assertSame(2, $quotation->lines()->count());

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1300000],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, $quotation->lines()->count());
        $this->assertDatabaseMissing('quotation_lines', ['item_name' => 'Item Kedua']);
    }

    public function test_cost_price_sent_by_client_for_an_existing_line_is_ignored(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();
        $realCost = $line->cost_price;

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [[
                    'procurement_request_line_id' => $line->procurement_request_line_id,
                    'selling_price' => 1300000,
                    'cost_price' => 1, // mencoba memalsukan harga beli jadi nyaris 0 (margin palsu)
                ]],
            ])
            ->assertRedirect();

        $fresh = $quotation->lines()->firstOrFail();
        $this->assertSame($realCost, $fresh->cost_price);
    }

    public function test_sent_quotation_can_still_be_updated_but_resets_to_draft_and_clears_review(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();
        $quotation->update([
            'status' => 'sent',
            'pm_review_status' => 'approved',
            'manager_review_status' => 'approved',
        ]);

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1600000],
                ],
            ])
            ->assertRedirect();

        $quotation->refresh();
        $this->assertSame('draft', $quotation->status);
        $this->assertNull($quotation->pm_review_status);
        $this->assertNull($quotation->manager_review_status);
        $this->assertSame('1600000.00', $quotation->lines()->first()->selling_price);
    }

    public function test_quotation_that_already_became_a_sales_order_cannot_be_updated(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $quotation = $salesOrder->quotation;
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1700000],
                ],
            ])
            ->assertForbidden();
    }

    public function test_quotation_with_a_newer_revision_cannot_be_updated(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $line = $quotation->lines()->firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/revisions");

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $line->procurement_request_line_id, 'selling_price' => 1700000],
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

    public function test_revision_inherits_custom_terms(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent', 'terms' => 'Syarat khusus quotation ini']);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/revisions")->assertRedirect();

        $revision = Quotation::where('parent_quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame('Syarat khusus quotation ini', $revision->terms);
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

    public function test_draft_quotation_can_be_deleted_by_its_owner(): void
    {
        [$sales, $quotation] = $this->draftQuotation();

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertRedirect('/sales/quotations');

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseCount('quotation_lines', 0);
    }

    public function test_sent_quotation_can_also_be_deleted(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertRedirect('/sales/quotations');

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
    }

    public function test_rejected_quotation_can_also_be_deleted(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'rejected']);

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertRedirect('/sales/quotations');

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
    }

    public function test_quotation_with_a_revision_cannot_be_deleted(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/revisions");

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
    }

    public function test_deleting_a_quotation_also_clears_its_dangling_notifications(): void
    {
        $pm = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        [$sales, $pr] = $this->readyProcurementRequest();
        $pr->lead->update(['delegated_to' => $pm->id]);
        $line = $pr->lines()->firstOrFail();

        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $line->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $pm->id,
            'type' => 'quotation.pending_pm_review',
            'related_id' => $quotation->id,
        ]);

        $this->actingAs($sales)->delete("/sales/quotations/{$quotation->id}")->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'related_type' => (new Quotation)->getMorphClass(),
            'related_id' => $quotation->id,
        ]);
    }

    public function test_only_the_owning_sales_can_delete_a_quotation(): void
    {
        [, $quotation] = $this->draftQuotation();
        $other = User::factory()->create(['role' => 'sales']);

        $this->actingAs($other)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
    }

    public function test_confirmed_quotation_can_be_deleted_when_sales_order_has_no_invoice_or_project(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $quotation = $salesOrder->quotation;

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertRedirect('/sales/quotations');

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseMissing('sales_orders', ['id' => $salesOrder->id]);
        $this->assertDatabaseCount('sales_order_lines', 0);
    }

    public function test_confirmed_quotation_cannot_be_deleted_once_an_invoice_exists(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $quotation = $salesOrder->quotation;
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $salesOrder->id, 'phase' => 'full']);

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseHas('sales_orders', ['id' => $salesOrder->id]);
    }

    public function test_confirmed_quotation_cannot_be_deleted_once_a_project_exists(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('mixed');
        $quotation = $salesOrder->quotation;
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $salesOrder->id, 'phase' => 'dp']);
        $invoice = $salesOrder->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('projects', ['sales_order_id' => $salesOrder->id]);

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseHas('sales_orders', ['id' => $salesOrder->id]);
    }

    public function test_deleting_confirmed_quotation_also_removes_sales_order_attachments_and_notifications(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $quotation = $salesOrder->quotation;

        $salesOrder->attachments()->create([
            'category' => 'quotation_signed',
            'file_path' => 'sales-orders/signed-quotation.pdf',
            'uploaded_by' => $sales->id,
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->put('sales-orders/signed-quotation.pdf', 'dummy');

        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        \App\Models\Notification::create([
            'user_id' => $finance->id,
            'type' => 'sales_order.created',
            'message' => 'Sales Order siap dibuatkan invoice.',
            'related_type' => $salesOrder->getMorphClass(),
            'related_id' => $salesOrder->id,
            'is_sent' => true,
        ]);

        $this->actingAs($sales)->delete("/sales/quotations/{$quotation->id}")->assertRedirect();

        $this->assertDatabaseMissing('attachments', ['attachable_type' => $salesOrder->getMorphClass(), 'attachable_id' => $salesOrder->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing('sales-orders/signed-quotation.pdf');
        $this->assertDatabaseMissing('notifications', ['related_type' => $salesOrder->getMorphClass(), 'related_id' => $salesOrder->id]);
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
