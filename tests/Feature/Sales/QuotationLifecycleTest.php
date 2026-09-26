<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_quotation_list_exposes_manual_temperature_and_automatic_pipeline_stage(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->lead->update(['temperature' => 'warm']);

        $this->actingAs($sales)->get('/sales/quotations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('quotations.data.0.lead.temperature', 'warm')
                ->where('quotations.data.0.lead.pipeline_stage', 'negotiation')
                ->where('quotations.data.0.lead.pipeline_stage_label', 'Negosiasi'));
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

    public function test_opening_create_page_again_redirects_to_the_latest_existing_quotation(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/revisions")
            ->assertRedirect();
        $revision = $quotation->revisions()->firstOrFail();
        $quotation->procurementRequest->update(['status' => 'searching']);

        $this->actingAs($sales)
            ->get("/sales/procurement-requests/{$quotation->procurement_request_id}/quotations/create")
            ->assertRedirect("/sales/quotations/{$revision->id}")
            ->assertSessionHas('success', 'Quotation untuk Procurement Request ini sudah tersedia.');

        $this->assertDatabaseCount('quotations', 2);
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

    public function test_commercial_edit_cannot_change_procurement_scope(): void
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

        $fresh = $quotation->lines()->firstOrFail();
        $this->assertSame($line->item_name, $fresh->item_name);
        $this->assertSame($line->qty, $fresh->qty);
        $this->assertSame($line->unit, $fresh->unit);
        $this->assertSame('2600000.00', $fresh->subtotal);
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

    public function test_commercial_edit_cannot_add_a_line_outside_procurement(): void
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
            ->assertSessionHasErrors('lines');

        $this->assertSame(1, $quotation->lines()->count());
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
            ->assertSessionHasErrors('lines');
    }

    public function test_commercial_edit_cannot_remove_a_procurement_line(): void
    {
        [$sales, $pr] = $this->readyProcurementRequest();
        $secondPrLine = $pr->lines()->create([
            'item_name' => 'Kabel', 'qty' => 1, 'unit' => 'meter', 'category' => 'material',
            'cost_price' => 10000, 'availability_status' => 'available',
        ]);
        $firstPrLine = $pr->lines()->whereKeyNot($secondPrLine->id)->firstOrFail();
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [
                ['procurement_request_line_id' => $firstPrLine->id, 'selling_price' => 1300000],
                ['procurement_request_line_id' => $secondPrLine->id, 'selling_price' => 15000],
            ],
        ]);
        $quotation = Quotation::where('procurement_request_id', $pr->id)->firstOrFail();

        $this->actingAs($sales)
            ->put("/sales/quotations/{$quotation->id}", [
                'lines' => [
                    ['procurement_request_line_id' => $firstPrLine->id, 'selling_price' => 1300000],
                ],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertSame(2, $quotation->lines()->count());
    }

    public function test_rejected_scope_revision_is_recosted_and_returns_to_the_same_quotation(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $projectManager = User::factory()->create(['role' => 'project_manager', 'is_active' => true]);
        $quotation->lead->update(['delegated_to' => $projectManager->id]);
        $quotation->update(['pm_review_status' => 'rejected', 'pm_review_notes' => 'Tambahkan jasa instalasi.']);
        $request = $quotation->procurementRequest;
        $requestLine = $request->lines()->firstOrFail();
        $vendor = Vendor::create(['name' => 'Vendor Existing']);
        $product = VendorProduct::create([
            'vendor_id' => $vendor->id,
            'item_name' => 'Kabel Existing',
            'category' => 'material',
            'price' => 700000,
            'unit' => 'meter',
            'is_active' => true,
        ]);
        $unchangedRequestLine = $request->lines()->create([
            'vendor_product_id' => $product->id,
            'item_name' => 'Kabel Existing',
            'category' => 'material',
            'description' => 'Kabel dari costing awal',
            'sourcing_note' => 'Vendor dan harga lama',
            'qty' => 10,
            'unit' => 'meter',
            'cost_price' => 700000,
            'availability_status' => 'available',
        ]);
        $quotation->lines()->create([
            'procurement_request_line_id' => $unchangedRequestLine->id,
            'item_name' => $unchangedRequestLine->item_name,
            'category' => $unchangedRequestLine->category,
            'description' => $unchangedRequestLine->description,
            'sourcing_note' => $unchangedRequestLine->sourcing_note,
            'qty' => $unchangedRequestLine->qty,
            'unit' => $unchangedRequestLine->unit,
            'cost_price' => $unchangedRequestLine->cost_price,
            'selling_price' => 900000,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'markup_percent' => 28.57,
            'tax_rate' => 0,
            'subtotal' => 9000000,
        ]);

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}/scope-revision", [
            'lines' => [
                [
                    'procurement_request_line_id' => $requestLine->id,
                    'item_name' => 'Router Enterprise Revisi',
                    'description' => 'Spesifikasi diperbarui',
                    'qty' => 3,
                    'unit' => 'unit',
                    'category' => 'material',
                ],
                [
                    'procurement_request_line_id' => $unchangedRequestLine->id,
                    'item_name' => $unchangedRequestLine->item_name,
                    'description' => $unchangedRequestLine->description,
                    'qty' => $unchangedRequestLine->qty,
                    'unit' => $unchangedRequestLine->unit,
                    'category' => $unchangedRequestLine->category,
                ],
                [
                    'procurement_request_line_id' => null,
                    'item_name' => 'Jasa Instalasi',
                    'description' => 'Instalasi dan konfigurasi',
                    'qty' => 1,
                    'unit' => 'lot',
                    'category' => 'service',
                ],
            ],
        ])->assertRedirect("/sales/quotations/{$quotation->id}");

        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertSame('procurement', $quotation->lead->fresh()->stage);
        $this->assertNull($quotation->fresh()->pm_review_status);
        $this->assertDatabaseCount('quotations', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $procurement->id,
            'type' => 'procurement_request.recost_requested',
            'related_id' => $request->id,
        ]);
        $this->assertDatabaseHas('procurement_request_lines', [
            'id' => $unchangedRequestLine->id,
            'vendor_product_id' => $product->id,
            'sourcing_note' => 'Vendor dan harga lama',
            'cost_price' => 700000,
            'availability_status' => 'available',
        ]);
        $this->assertDatabaseHas('procurement_request_lines', [
            'id' => $requestLine->id,
            'vendor_product_id' => null,
            'sourcing_note' => null,
            'cost_price' => 0,
            'availability_status' => 'searching',
        ]);

        // Kompatibilitas data yang sempat di-reset oleh implementasi recost lama.
        $unchangedRequestLine->update([
            'sourcing_note' => null,
            'cost_price' => 0,
            'availability_status' => 'searching',
        ]);
        $this->actingAs($procurement)->get("/procurement/procurement-requests/{$request->id}")
            ->assertInertia(fn ($page) => $page
                ->where('procurementRequest.lines.0.revision_state', 'changed')
                ->where('procurementRequest.lines.1.revision_state', 'unchanged')
                ->where('procurementRequest.lines.1.cost_price', '700000.00')
                ->where('procurementRequest.lines.1.sourcing_note', 'Vendor dan harga lama')
                ->where('procurementRequest.lines.1.availability_status', 'available')
                ->where('procurementRequest.lines.2.revision_state', 'new'));
        $unchangedRequestLine->update([
            'vendor_product_id' => $product->id,
            'sourcing_note' => 'Vendor dan harga lama',
            'cost_price' => 700000,
            'availability_status' => 'available',
        ]);

        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}/edit")->assertForbidden();

        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$request->id}/start")->assertRedirect();
        $recostLines = $request->fresh()->lines()->orderBy('id')->get();
        $this->actingAs($procurement)->put("/procurement/procurement-requests/{$request->id}/lines", [
            'lines' => $recostLines->map(fn ($line, $index) => [
                'id' => $line->id,
                'category' => $line->category,
                'sourcing_note' => $line->item_name === 'Kabel Existing' ? $line->sourcing_note : ($index === 0 ? 'Stok gudang' : 'Vendor jasa'),
                'vendor_product_id' => $line->vendor_product_id,
                'cost_price' => $line->item_name === 'Kabel Existing' ? $line->cost_price : ($index === 0 ? 1100000 : 250000),
                'tax_id' => null,
                'availability_status' => 'available',
            ])->all(),
        ])->assertRedirect();
        $this->actingAs($procurement)->post("/procurement/procurement-requests/{$request->id}/ready")->assertRedirect();

        $quotation->refresh()->load('lines');
        $this->assertSame($quotation->id, Quotation::sole()->id);
        $this->assertSame('ready', $request->fresh()->status);
        $this->assertSame('quotation', $quotation->lead->fresh()->stage);
        $this->assertCount(3, $quotation->lines);
        $this->assertSame('1100000.00', $quotation->lines->firstWhere('item_name', 'Router Enterprise Revisi')->cost_price);
        $this->assertSame('0.00', $quotation->lines->firstWhere('item_name', 'Jasa Instalasi')->selling_price);
        $this->assertSame('700000.00', $quotation->lines->firstWhere('item_name', 'Kabel Existing')->cost_price);
        $this->assertNull($quotation->quoted_at);
        $this->actingAs($projectManager)
            ->post("/project-manager/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertForbidden();

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}", [
            'lines' => $quotation->lines->map(fn ($line) => [
                'procurement_request_line_id' => $line->procurement_request_line_id,
                'selling_price' => $line->item_name === 'Jasa Instalasi' ? 400000 : 1500000,
            ])->all(),
        ])->assertRedirect("/sales/quotations/{$quotation->id}");

        $this->assertSame('400000.00', $quotation->fresh()->lines()->where('item_name', 'Jasa Instalasi')->value('selling_price'));
        $this->actingAs($projectManager)
            ->post("/project-manager/quotations/{$quotation->id}/review", ['approved' => true])
            ->assertRedirect();
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
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'))
            ->assertForbidden();

        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('material_only'))
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
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('material_only'))
            ->assertForbidden();

        $this->assertDatabaseCount('sales_orders', 1);
    }

    public function test_service_order_derives_dp_50_payment_rule(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('service_only'))
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
        $lead = $quotation->lead;
        $procurementRequest = $quotation->procurementRequest;
        $sourceLine = $procurementRequest->lines()->firstOrFail();

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertRedirect('/sales/quotations');

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseCount('quotation_lines', 0);
        $this->assertDatabaseHas('procurement_requests', ['id' => $procurementRequest->id, 'status' => 'ready']);
        $this->assertSame('procurement', $lead->fresh()->stage);

        $this->actingAs($sales)->post("/sales/procurement-requests/{$procurementRequest->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $sourceLine->id,
                'selling_price' => 1400000,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseCount('quotations', 1);
    }

    public function test_sent_quotation_cannot_be_deleted_permanently(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);

    }

    public function test_rejected_quotation_cannot_be_deleted_permanently(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'rejected']);

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
    }

    public function test_draft_previously_sent_to_customer_cannot_be_deleted_permanently(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['whatsapp_sent_at' => now(), 'whatsapp_sent_by' => $sales->id]);

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
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

        $revision = $quotation->revisions()->firstOrFail();
        $this->actingAs($sales)
            ->delete("/sales/quotations/{$revision->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('quotations', ['id' => $revision->id]);
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

    public function test_confirmed_quotation_cannot_be_deleted_even_without_invoice_or_project(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $quotation = $salesOrder->quotation;

        $this->actingAs($sales)
            ->delete("/sales/quotations/{$quotation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseHas('sales_orders', ['id' => $salesOrder->id]);
    }

    public function test_sent_quotation_can_be_cancelled_without_deleting_its_history(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/cancel", ['reason' => 'Customer membatalkan kebutuhan.'])
            ->assertRedirect("/sales/quotations/{$quotation->id}");

        $quotation->refresh();
        $this->assertSame('cancelled', $quotation->status);
        $this->assertSame($sales->id, $quotation->cancelled_by);
        $this->assertSame('Customer membatalkan kebutuhan.', $quotation->cancellation_reason);
        $this->assertNotNull($quotation->cancelled_at);
        $this->assertSame('lost', $quotation->lead->fresh()->stage);
        $this->assertDatabaseHas('procurement_requests', ['id' => $quotation->procurement_request_id]);
        $this->assertDatabaseHas('quotation_lines', ['quotation_id' => $quotation->id]);
    }

    public function test_cancelling_a_revision_cancels_the_complete_quotation_chain(): void
    {
        [$sales, $quotation] = $this->draftQuotation();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/revisions")->assertRedirect();
        $revision = $quotation->revisions()->firstOrFail();

        $this->actingAs($sales)
            ->post("/sales/quotations/{$revision->id}/cancel", ['reason' => 'Seluruh penawaran dihentikan customer.'])
            ->assertRedirect("/sales/quotations/{$revision->id}");

        $this->assertSame('cancelled', $quotation->fresh()->status);
        $this->assertSame('cancelled', $revision->fresh()->status);
    }

    public function test_cancellation_propagates_to_sales_order_and_unpaid_invoice(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $quotation = $salesOrder->quotation;
        $invoice = Invoice::create([
            'sales_order_id' => $salesOrder->id,
            'invoice_phase' => 'full',
            'status' => 'sent',
            'amount' => 2600000,
            'tax_amount' => 0,
        ]);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/cancel", ['reason' => 'Pesanan dibatalkan sebelum pembayaran.'])
            ->assertRedirect("/sales/quotations/{$quotation->id}");

        $this->assertSame('cancelled', $quotation->fresh()->status);
        $this->assertSame('cancelled', $salesOrder->fresh()->status);
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertSame($sales->id, $salesOrder->fresh()->cancelled_by);
        $this->assertSame($sales->id, $invoice->fresh()->cancelled_by);
    }

    public function test_transaction_with_payment_cannot_be_cancelled(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $invoice = Invoice::create([
            'sales_order_id' => $salesOrder->id,
            'invoice_phase' => 'full',
            'status' => 'partially_paid',
            'amount' => 2600000,
            'tax_amount' => 0,
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'amount_paid' => 100000,
            'paid_at' => now(),
        ]);

        $this->actingAs($sales)
            ->post("/sales/quotations/{$salesOrder->quotation_id}/cancel", ['reason' => 'Mencoba membatalkan transaksi.'])
            ->assertForbidden();

        $this->assertSame('confirmed', $salesOrder->fresh()->status);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
    }

    public function test_pristine_draft_uses_permanent_delete_instead_of_cancellation(): void
    {
        [$sales, $quotation] = $this->draftQuotation();

        $this->actingAs($sales)
            ->post("/sales/quotations/{$quotation->id}/cancel", ['reason' => 'Belum pernah diproses.'])
            ->assertForbidden();

        $this->assertSame('draft', $quotation->fresh()->status);
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

    public function test_blocked_confirmed_deletion_preserves_sales_order_attachments_and_notifications(): void
    {
        Storage::fake('local');
        [$sales, $salesOrder] = $this->confirmedSalesOrder('material_only');
        $quotation = $salesOrder->quotation;

        $salesOrder->attachments()->create([
            'category' => 'quotation_signed',
            'file_path' => 'sales-orders/signed-quotation.pdf',
            'uploaded_by' => $sales->id,
        ]);
        Storage::disk('local')->put('sales-orders/signed-quotation.pdf', 'dummy');

        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        Notification::create([
            'user_id' => $finance->id,
            'type' => 'sales_order.created',
            'message' => 'Sales Order siap dibuatkan invoice.',
            'related_type' => $salesOrder->getMorphClass(),
            'related_id' => $salesOrder->id,
            'is_sent' => true,
        ]);

        $this->actingAs($sales)->delete("/sales/quotations/{$quotation->id}")->assertForbidden();

        $this->assertDatabaseHas('attachments', ['attachable_type' => $salesOrder->getMorphClass(), 'attachable_id' => $salesOrder->id]);
        Storage::disk('local')->assertExists('sales-orders/signed-quotation.pdf');
        $this->assertDatabaseHas('notifications', ['related_type' => $salesOrder->getMorphClass(), 'related_id' => $salesOrder->id]);
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

    /** Quotation dengan dua item (Router dari PR + Kabel tambahan), keduanya sudah punya harga dari Procurement. */
    private function quotationWithTwoItems(): array
    {
        [$sales, $quotation] = $this->draftQuotation();
        $request = $quotation->procurementRequest;
        $kept = $request->lines()->firstOrFail();
        $kept->update(['category' => 'material', 'unit' => $kept->unit ?: 'unit']);
        $extra = $request->lines()->create([
            'item_name' => 'Kabel Tambahan', 'category' => 'material', 'qty' => 10, 'unit' => 'meter',
            'cost_price' => 700000, 'availability_status' => 'available',
        ]);
        $quotation->lines()->create([
            'procurement_request_line_id' => $extra->id, 'item_name' => 'Kabel Tambahan', 'category' => 'material',
            'qty' => 10, 'unit' => 'meter', 'cost_price' => 700000, 'selling_price' => 900000,
            'discount_percent' => 0, 'discount_amount' => 0, 'markup_percent' => 28.57, 'tax_rate' => 0, 'subtotal' => 9000000,
        ]);

        return [$sales, $quotation->fresh(), $kept, $extra];
    }

    private function scopeLine($line, array $override = []): array
    {
        return array_merge([
            'procurement_request_line_id' => $line->id, 'item_name' => $line->item_name, 'description' => $line->description,
            'qty' => $line->qty, 'unit' => $line->unit, 'category' => $line->category,
        ], $override);
    }

    public function test_revisi_kebutuhan_is_available_for_a_draft_that_was_never_rejected(): void
    {
        [$sales, $quotation] = $this->draftQuotation();

        $this->assertNull($quotation->pm_review_status);
        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}/scope-revision")->assertOk();
        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}/edit")
            ->assertInertia(fn ($page) => $page->where('canReviseScope', true));
    }

    public function test_removing_only_items_applies_immediately_without_procurement(): void
    {
        [$sales, $quotation, $kept, $extra] = $this->quotationWithTwoItems();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $quotation->update(['pm_review_status' => 'approved', 'manager_review_status' => 'approved', 'status' => 'sent']);

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}/scope-revision", [
            'lines' => [$this->scopeLine($kept->fresh())],
        ])->assertRedirect("/sales/quotations/{$quotation->id}")->assertSessionHas('success');

        $quotation->refresh();
        $this->assertSame('ready', $quotation->procurementRequest->status);
        $this->assertSame('draft', $quotation->status);
        $this->assertNull($quotation->pm_review_status);
        $this->assertNull($quotation->manager_review_status);
        $this->assertSame(1, $quotation->lines()->count());
        $this->assertDatabaseMissing('procurement_request_lines', ['id' => $extra->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $procurement->id, 'type' => 'procurement_request.recost_requested']);
    }

    public function test_adding_an_item_goes_to_procurement_for_costing(): void
    {
        [$sales, $quotation, $kept] = $this->quotationWithTwoItems();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}/scope-revision", [
            'lines' => [
                $this->scopeLine($kept->fresh()),
                ['procurement_request_line_id' => null, 'item_name' => 'Jasa Instalasi', 'description' => null, 'qty' => 1, 'unit' => 'lot', 'category' => 'service'],
            ],
        ])->assertRedirect();

        $this->assertSame('submitted', $quotation->procurementRequest->fresh()->status);
        $this->assertDatabaseHas('procurement_request_lines', ['item_name' => 'Jasa Instalasi', 'cost_price' => 0, 'availability_status' => 'searching']);
        $this->assertDatabaseHas('notifications', ['user_id' => $procurement->id, 'type' => 'procurement_request.recost_requested']);
    }

    /**
     * Regresi bug produksi: menambah item baru sambil TIDAK menyertakan item lama (dianggap
     * dihapus) memicu jalur "needs costing", bukan removeItemsOnly(). Item lama yang tidak
     * dipertahankan harus langsung hilang dari quotation saat itu juga — bukan cuma PR line-nya
     * yang terhapus lalu baris quotation-nya nyangkut dengan procurement_request_line_id NULL.
     * Kalau nyangkut, quotation itu jadi TIDAK BISA diedit sama sekali lewat form biasa (selalu
     * ditolak dengan pesan "susunan kebutuhan tidak dapat diubah"), walau Sales cuma ganti harga.
     */
    public function test_dropped_item_line_is_removed_immediately_even_when_another_item_also_needs_costing(): void
    {
        [$sales, $quotation, $kept, $extra] = $this->quotationWithTwoItems();

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}/scope-revision", [
            'lines' => [
                $this->scopeLine($kept->fresh()),
                ['procurement_request_line_id' => null, 'item_name' => 'Jasa Instalasi', 'description' => null, 'qty' => 1, 'unit' => 'lot', 'category' => 'service'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('procurement_request_lines', ['id' => $extra->id]);
        $this->assertDatabaseMissing('quotation_lines', ['quotation_id' => $quotation->id, 'procurement_request_line_id' => $extra->id]);
        $this->assertDatabaseMissing('quotation_lines', ['quotation_id' => $quotation->id, 'procurement_request_line_id' => null]);
    }

    public function test_recost_notifications_link_to_the_right_pages(): void
    {
        [$sales, $quotation] = $this->quotationWithTwoItems();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $pr = $quotation->procurementRequest;

        $recost = Notification::create(['user_id' => $procurement->id, 'type' => 'procurement_request.recost_requested', 'title' => 'x', 'message' => 'x', 'related_id' => $pr->id]);
        $ready = Notification::create(['user_id' => $sales->id, 'type' => 'procurement_request.ready', 'title' => 'x', 'message' => 'x', 'related_id' => $pr->id]);

        $this->actingAs($procurement)->post("/notifications/{$recost->id}/read")->assertRedirect("/procurement/procurement-requests/{$pr->id}");
        $this->actingAs($sales)->post("/notifications/{$ready->id}/read")->assertRedirect("/sales/quotations/{$quotation->id}");
    }

    public function test_scope_revision_without_any_change_is_rejected(): void
    {
        [$sales, $quotation, $kept, $extra] = $this->quotationWithTwoItems();

        $this->actingAs($sales)->put("/sales/quotations/{$quotation->id}/scope-revision", [
            'lines' => [$this->scopeLine($kept->fresh()), $this->scopeLine($extra->fresh())],
        ])->assertSessionHasErrors('lines');
    }

    public function test_scope_revision_is_blocked_after_sales_order_and_for_other_sales(): void
    {
        [$sales, $quotation, $kept] = $this->quotationWithTwoItems();
        $other = User::factory()->create(['role' => 'sales']);

        $this->actingAs($other)->get("/sales/quotations/{$quotation->id}/scope-revision")->assertForbidden();

        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('material_only'));
        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}/scope-revision")->assertForbidden();
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
