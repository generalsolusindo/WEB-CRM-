<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationNumberEditTest extends TestCase
{
    use RefreshDatabase;

    private function createQuotation(?User $sales = null): Quotation
    {
        $sales ??= User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::where('lead_id', $lead->id)->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);

        return $pr->quotations()->latest('id')->firstOrFail();
    }

    public function test_owner_can_edit_number_and_future_numbers_continue_from_it(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $quotation = $this->createQuotation($sales);

        $this->actingAs($sales)->patch("/sales/quotations/{$quotation->id}/number", [
            'number' => '1255/GS-PN/09/2026',
        ])->assertRedirect();

        $this->assertSame('1255/GS-PN/09/2026', $quotation->fresh()->number);

        $next = app(DocumentNumber::class)->nextQuotationNumber();
        $this->assertStringStartsWith('1256/GS-PN/', $next);
    }

    public function test_cannot_reuse_number_from_another_quotation(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $existing = $this->createQuotation($sales);
        $quotation = $this->createQuotation($sales);

        $this->actingAs($sales)->patch("/sales/quotations/{$quotation->id}/number", [
            'number' => $existing->number,
        ])->assertSessionHasErrors('number');
    }

    public function test_only_owner_sales_can_edit_number(): void
    {
        $quotation = $this->createQuotation();
        $otherSales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($otherSales)->patch("/sales/quotations/{$quotation->id}/number", [
            'number' => '9999/GS-PN/09/2026',
        ])->assertForbidden();
    }

    public function test_number_can_be_edited_regardless_of_status(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $quotation = $this->createQuotation($sales);
        $quotation->update(['status' => 'sent']);

        $this->actingAs($sales)->patch("/sales/quotations/{$quotation->id}/number", [
            'number' => '2000/GS-PN/09/2026',
        ])->assertRedirect();

        $this->assertSame('2000/GS-PN/09/2026', $quotation->fresh()->number);
    }

    /** Kolom quotations.number adalah VARCHAR(30) — validasi harus menolak sebelum kena error SQL. */
    public function test_number_longer_than_column_limit_is_rejected_by_validation(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $quotation = $this->createQuotation($sales);

        $this->actingAs($sales)->patch("/sales/quotations/{$quotation->id}/number", [
            'number' => str_repeat('9', 31),
        ])->assertSessionHasErrors('number');
    }
}
