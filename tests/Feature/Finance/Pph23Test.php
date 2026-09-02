<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Pph23Test extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    /** SO service_only, 1 baris jasa: qty 1 x 1.000.000 = DPP 1.000.000 (dp_50). */
    private function serviceOrder(string $category = 'service'): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Instalasi', 'qty' => 1, 'unit' => 'paket', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 500000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [[
                'procurement_request_line_id' => $pr->lines->first()->id,
                'selling_price' => 1000000,
                'category' => $category,
            ]],
        ]);
        $q = Quotation::firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload($category === 'service' ? 'service_only' : 'material_only'));

        return SalesOrder::firstOrFail();
    }

    private function payDp(SalesOrder $so, User $finance): void
    {
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $dp = Invoice::where('invoice_phase', 'dp')->firstOrFail();
        $this->assertSame('0.00', $dp->pph23_amount); // DP tidak kena PPh 23
        $this->actingAs($finance)->post("/finance/invoices/{$dp->id}/payments", [
            'amount_paid' => (float) $dp->amount + (float) $dp->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_final_invoice_computes_pph23_2_percent_of_full_service_dpp(): void
    {
        $finance = $this->finance();
        $so = $this->serviceOrder();
        $this->payDp($so, $finance);
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);

        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice")->assertRedirect();

        $final = Invoice::where('invoice_phase', 'final')->firstOrFail();
        $this->assertSame('2.00', $final->pph23_rate);
        $this->assertSame('20000.00', $final->pph23_amount);           // 2% x 1.000.000 (DPP jasa penuh)
        $this->assertSame(500000.0, (float) $final->amount);           // sisa 50%
        $this->assertEqualsWithDelta(480000.0, $final->payableAmount(), 0.01); // 500rb - 20rb
    }

    public function test_material_only_final_has_no_pph23(): void
    {
        // material_only = full_100, tidak ada pelunasan; cek invoice full tidak kena PPh 23
        $finance = $this->finance();
        $so = $this->serviceOrder('material');

        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);
        $invoice = Invoice::firstOrFail();
        $this->assertSame('0.00', $invoice->pph23_amount);
    }

    public function test_invoice_lunas_by_cash_plus_pph23(): void
    {
        $finance = $this->finance();
        $so = $this->serviceOrder();
        $this->payDp($so, $finance);
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);
        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice");
        $final = Invoice::where('invoice_phase', 'final')->firstOrFail();

        // bayar kas sebesar payable (500rb - 20rb PPh23 = 480rb)
        $this->actingAs($finance)->post("/finance/invoices/{$final->id}/payments", [
            'amount_paid' => 480000, 'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame('paid', $final->fresh()->status);
    }

    public function test_finance_edits_pph23_rate_on_draft_then_locked_after_payment(): void
    {
        $finance = $this->finance();
        $so = $this->serviceOrder();
        $this->payDp($so, $finance);
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);
        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice");
        $final = Invoice::where('invoice_phase', 'final')->firstOrFail();

        $this->actingAs($finance)->post("/finance/invoices/{$final->id}/pph23", ['rate' => 4])
            ->assertRedirect();
        $this->assertSame('4.00', $final->fresh()->pph23_rate);
        $this->assertSame('40000.00', $final->fresh()->pph23_amount);

        // setelah ada pembayaran, rate terkunci
        $this->actingAs($finance)->post("/finance/invoices/{$final->id}/payments", [
            'amount_paid' => 100000, 'paid_at' => now()->toDateTimeString(),
        ]);
        $this->actingAs($finance)->post("/finance/invoices/{$final->id}/pph23", ['rate' => 2]);
        $this->assertSame('4.00', $final->fresh()->pph23_rate); // tidak berubah
    }

    public function test_finance_records_bukti_potong(): void
    {
        $finance = $this->finance();
        $so = $this->serviceOrder();
        $this->payDp($so, $finance);
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);
        $this->actingAs($finance)->post("/finance/sales-orders/{$so->id}/final-invoice");
        $final = Invoice::where('invoice_phase', 'final')->firstOrFail();

        $this->actingAs($finance)->post("/finance/invoices/{$final->id}/pph23", [
            'bukti_potong_no' => 'BP/2026/0099',
            'slip' => UploadedFile::fake()->create('bp.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $final->refresh();
        $this->assertSame('BP/2026/0099', $final->pph23_bukti_potong_no);
        $this->assertNotNull($final->pph23_recorded_at);
        $slip = $final->attachments()->where('category', 'pph23_slip')->firstOrFail();
        Storage::disk('local')->assertExists($slip->file_path);
    }

    public function test_non_finance_cannot_manage_pph23(): void
    {
        $so = $this->serviceOrder();
        $this->payDp($so, $this->finance());
        Project::create(['sales_order_id' => $so->id, 'status' => 'completed']);
        $this->actingAs($this->finance())->post("/finance/sales-orders/{$so->id}/final-invoice");
        $final = Invoice::where('invoice_phase', 'final')->firstOrFail();

        $sales = User::factory()->create(['role' => 'sales']);
        $this->actingAs($sales)->post("/finance/invoices/{$final->id}/pph23", ['rate' => 5])->assertForbidden();
    }
}
