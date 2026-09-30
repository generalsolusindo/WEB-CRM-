<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laporan produksi: sebagian klien mewajibkan invoice dicetak fisik lalu ditandatangani
 * basah + materai (biasanya nominal di atas Rp5 juta), sehingga gambar stempel/ttd
 * digital yang otomatis tercetak jadi masalah. Finance sekarang bisa mematikannya
 * per-invoice — nama terang & jabatan tetap tercetak, cuma gambarnya yang hilang.
 */
class InvoiceSignatureToggleTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedOrder(): SalesOrder
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = $lead->procurementRequests()->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 6000000]],
        ]);
        $q = $pr->quotations()->latest('id')->firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('material_only'));

        return SalesOrder::where('quotation_id', $q->id)->firstOrFail();
    }

    private function invoiceFor(SalesOrder $so, User $finance): Invoice
    {
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);

        return Invoice::where('sales_order_id', $so->id)->latest('id')->firstOrFail();
    }

    public function test_new_invoice_defaults_to_signature_on_and_stamp_image_is_printed(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $invoice = $this->invoiceFor($this->confirmedOrder(), $finance);

        $this->assertTrue($invoice->with_signature);

        $html = $this->actingAs($finance)->get("/finance/invoices/{$invoice->id}/print")->assertOk()->getContent();
        $this->assertStringContainsString('class="stamp"', $html);
    }

    public function test_finance_turns_signature_off_and_stamp_image_disappears_but_name_stays(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $invoice = $this->invoiceFor($this->confirmedOrder(), $finance);

        $this->actingAs($finance)->patch("/finance/invoices/{$invoice->id}/signature", ['with_signature' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($invoice->fresh()->with_signature);

        $html = $this->actingAs($finance)->get("/finance/invoices/{$invoice->id}/print")->assertOk()->getContent();
        $this->assertStringNotContainsString('class="stamp"', $html);
        // Nama terang & jabatan tetap tercetak walau gambarnya dimatikan.
        $this->assertStringContainsString('Adila Swasdika Putra', $html);

        // Boleh dinyalakan lagi kapan saja, termasuk setelah invoice sudah terkirim/lunas.
        $invoice->update(['status' => 'paid']);
        $this->actingAs($finance)->patch("/finance/invoices/{$invoice->id}/signature", ['with_signature' => true])
            ->assertSessionHas('success');
        $this->assertTrue($invoice->fresh()->with_signature);
    }

    public function test_cancelled_invoice_cannot_toggle_signature(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $invoice = $this->invoiceFor($this->confirmedOrder(), $finance);
        $invoice->update(['status' => 'cancelled']);

        $this->actingAs($finance)->patch("/finance/invoices/{$invoice->id}/signature", ['with_signature' => false])
            ->assertForbidden();

        $this->assertTrue($invoice->fresh()->with_signature);
    }

    public function test_non_finance_roles_cannot_toggle_signature(): void
    {
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $invoice = $this->invoiceFor($this->confirmedOrder(), $finance);

        foreach (['sales', 'management', 'operational'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->patch("/finance/invoices/{$invoice->id}/signature", ['with_signature' => false])
                ->assertForbidden();
        }

        $this->assertTrue($invoice->fresh()->with_signature);
    }
}
