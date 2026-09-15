<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class QuotationWhatsappTest extends TestCase
{
    use RefreshDatabase;

    /** Quotation berstatus draft, sudah disetujui PM & Manager (fully approved). */
    private function approvedQuotationForContact(string $phone = '0812-3456-7890'): Quotation
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
        $quotation = Quotation::firstOrFail();
        $quotation->update(['pm_review_status' => 'approved', 'manager_review_status' => 'approved']);

        return $quotation->fresh();
    }

    private function sales(Quotation $quotation): User
    {
        return User::find($quotation->sales_id);
    }

    public function test_send_whatsapp_marks_sent_and_returns_wa_link(): void
    {
        $quotation = $this->approvedQuotationForContact();

        $res = $this->actingAs($this->sales($quotation))
            ->post("/sales/quotations/{$quotation->id}/send-whatsapp");

        $res->assertRedirect();
        $res->assertSessionHas('whatsappUrl', fn ($url) => str_contains($url, 'https://wa.me/6281234567890')
            && str_contains($url, 'quotation%2F'.$quotation->id.'%2Fpdf'));

        $quotation->refresh();
        $this->assertSame('sent', $quotation->status);
        $this->assertNotNull($quotation->whatsapp_sent_at);
        $this->assertNotNull($quotation->whatsapp_sent_by);
    }

    /**
     * Regresi: setelah dikirim lewat WhatsApp (otomatis jadi status Sent), tombol
     * "Tandai Terkirim" terpisah tidak boleh masih bisa diklik — sebelum diperbaiki,
     * policy send() cuma numpang lewat update() (yang sudah dilonggarkan dari
     * Draft-only), jadi tombolnya tetap aktif di UI dan begitu diklik gagal dengan
     * error 409 mentah di controller alih-alih 403 yang wajar dari policy.
     */
    public function test_marking_sent_after_already_sent_via_whatsapp_is_forbidden_not_a_server_error(): void
    {
        $quotation = $this->approvedQuotationForContact();
        $sales = $this->sales($quotation);

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/send-whatsapp");
        $this->assertSame('sent', $quotation->fresh()->status);

        $this->actingAs($sales)->get("/sales/quotations/{$quotation->id}")
            ->assertInertia(fn ($page) => $page->where('permissions.send', false));

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/send")
            ->assertForbidden();
    }

    public function test_send_whatsapp_fails_without_valid_number(): void
    {
        $quotation = $this->approvedQuotationForContact(phone: '');

        $this->actingAs($this->sales($quotation))
            ->post("/sales/quotations/{$quotation->id}/send-whatsapp")
            ->assertSessionHas('error');

        $this->assertNull($quotation->fresh()->whatsapp_sent_at);
        $this->assertSame('draft', $quotation->fresh()->status);
    }

    public function test_cannot_send_whatsapp_before_fully_approved(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Budi', 'phone' => '0812-3456-7890', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $lead->requirements()->create(['item_name' => 'Router', 'qty' => 1, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = ProcurementRequest::with('lines')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = Quotation::firstOrFail();

        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/send-whatsapp")
            ->assertForbidden();
    }

    public function test_public_pdf_link_needs_valid_signature(): void
    {
        $quotation = $this->approvedQuotationForContact();

        $this->get("/quotation/{$quotation->id}/pdf")->assertForbidden();

        $signed = URL::temporarySignedRoute('quotations.pdf.public', now()->addDay(), ['quotation' => $quotation->id]);
        $ok = $this->get($signed);
        $ok->assertOk();
        $this->assertSame('application/pdf', $ok->headers->get('content-type'));
        // Harus tampil inline di browser customer, bukan dipaksa download.
        $this->assertStringStartsWith('inline', $ok->headers->get('content-disposition'));
    }

    public function test_quotation_pdf_endpoint_is_owner_only(): void
    {
        $quotation = $this->approvedQuotationForContact();
        $otherSales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($otherSales)->get("/sales/quotations/{$quotation->id}/pdf")->assertForbidden();
        $this->actingAs($this->sales($quotation))->get("/sales/quotations/{$quotation->id}/pdf")->assertOk();
    }

    public function test_can_resend_whatsapp_after_already_sent(): void
    {
        $quotation = $this->approvedQuotationForContact();
        $quotation->update(['status' => 'sent']);

        $this->actingAs($this->sales($quotation))
            ->post("/sales/quotations/{$quotation->id}/send-whatsapp")
            ->assertRedirect();

        $this->assertNotNull($quotation->fresh()->whatsapp_sent_at);
    }
}
