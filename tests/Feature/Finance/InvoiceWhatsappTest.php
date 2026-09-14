<?php

namespace Tests\Feature\Finance;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvoiceWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private function finance(): User
    {
        return User::factory()->create(['role' => 'finance', 'is_active' => true]);
    }

    private function invoiceForContact(string $phone = '0812-3456-7890'): Invoice
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Budi', 'phone' => $phone, 'created_by' => $sales->id]);
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
        $q = Quotation::firstOrFail();
        $q->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$q->id}/confirm", $this->confirmPayload('material_only'));
        $so = SalesOrder::firstOrFail();

        $finance = $this->finance();
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'full']);

        return Invoice::firstOrFail();
    }

    public function test_send_whatsapp_marks_sent_and_returns_wa_link(): void
    {
        $invoice = $this->invoiceForContact();

        $res = $this->actingAs($this->finance())
            ->post("/finance/invoices/{$invoice->id}/send-whatsapp");

        $res->assertRedirect();
        $res->assertSessionHas('whatsappUrl', fn ($url) => str_contains($url, 'https://wa.me/6281234567890')
            && str_contains($url, 'invoice%2F'.$invoice->id.'%2Fpdf'));

        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertNotNull($invoice->whatsapp_sent_at);
        $this->assertNotNull($invoice->whatsapp_sent_by);
    }

    public function test_send_whatsapp_fails_without_valid_number(): void
    {
        $invoice = $this->invoiceForContact(phone: '');

        $this->actingAs($this->finance())
            ->post("/finance/invoices/{$invoice->id}/send-whatsapp")
            ->assertSessionHas('error');

        $this->assertNull($invoice->fresh()->whatsapp_sent_at);
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_public_pdf_link_needs_valid_signature(): void
    {
        $invoice = $this->invoiceForContact();

        $this->get("/invoice/{$invoice->id}/pdf")->assertForbidden();

        $signed = URL::temporarySignedRoute('invoices.pdf.public', now()->addDay(), ['invoice' => $invoice->id]);
        $ok = $this->get($signed);
        $ok->assertOk();
        $this->assertSame('application/pdf', $ok->headers->get('content-type'));
        // Harus tampil inline di browser customer, bukan dipaksa download (lihat fix serupa di Quotation).
        $this->assertStringStartsWith('inline', $ok->headers->get('content-disposition'));
    }

    public function test_finance_pdf_endpoint_is_finance_only(): void
    {
        $invoice = $this->invoiceForContact();
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)->get("/finance/invoices/{$invoice->id}/pdf")->assertForbidden();
        $this->actingAs($this->finance())->get("/finance/invoices/{$invoice->id}/pdf")->assertOk();
    }

    public function test_cancelled_invoice_cannot_be_sent(): void
    {
        $invoice = $this->invoiceForContact();
        $invoice->update(['status' => 'cancelled']);

        $this->actingAs($this->finance())
            ->post("/finance/invoices/{$invoice->id}/send-whatsapp")
            ->assertForbidden();
    }
}
