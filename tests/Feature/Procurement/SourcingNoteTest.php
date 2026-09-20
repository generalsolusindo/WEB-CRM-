<?php

namespace Tests\Feature\Procurement;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourcingNoteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, ProcurementRequest} */
    private function submittedPr(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'CCTV 8 channel', 'qty' => 1, 'unit' => 'set', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");

        return [$sales, ProcurementRequest::with('lines')->firstOrFail()];
    }

    public function test_procurement_saves_sourcing_note_and_it_flows_to_quotation(): void
    {
        [$sales, $pr] = $this->submittedPr();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $line = $pr->lines->first();
        $note = 'Rekomendasi Hikvision DS-7608; alternatif Dahua XVR (-12%). Customer belum tentukan merk.';

        $this->actingAs($procurement)->put("/procurement/procurement-requests/{$pr->id}/lines", [
            'lines' => [[
                'id' => $line->id,
                'sourcing_note' => $note,
                'cost_price' => 4200000,
                'availability_status' => 'available',
            ]],
        ])->assertRedirect();

        $this->assertSame($note, $line->fresh()->sourcing_note);

        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $line->id, 'selling_price' => 5500000]],
        ])->assertRedirect();

        $this->assertSame($note, Quotation::firstOrFail()->lines()->firstOrFail()->sourcing_note);
    }

    public function test_sales_cannot_override_or_clear_procurement_sourcing_note(): void
    {
        [$sales, $pr] = $this->submittedPr();
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        $line = $pr->lines->first();

        $this->actingAs($procurement)->put("/procurement/procurement-requests/{$pr->id}/lines", [
            'lines' => [[
                'id' => $line->id, 'sourcing_note' => 'internal draft', 'cost_price' => 4000000, 'availability_status' => 'available',
            ]],
        ]);
        $pr->update(['status' => 'ready']);

        // Payload Sales tidak boleh mengubah catatan sourcing dari Procurement.
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 5000000,
                'sourcing_note' => 'Tersedia merk Hikvision atau Dahua — mohon konfirmasi pilihan.',
            ]],
        ]);
        $q = Quotation::firstOrFail();
        $this->assertSame('internal draft', $q->lines()->firstOrFail()->sourcing_note);

        // clear pada update
        $this->actingAs($sales)->put("/sales/quotations/{$q->id}", [
            'lines' => [[
                'procurement_request_line_id' => $line->id,
                'selling_price' => 5000000,
                'sourcing_note' => '',
            ]],
        ])->assertRedirect();
        $this->assertSame('internal draft', $q->lines()->firstOrFail()->sourcing_note);
    }
}
