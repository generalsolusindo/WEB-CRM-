<?php

namespace Tests\Feature\Sales;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_create_lead_for_owned_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);

        $response = $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'new',
            'source' => 'website',
        ]);

        $lead = Lead::firstOrFail();
        $response->assertRedirectToRoute('sales.leads.show', $lead);
        $this->assertSame($sales->id, $lead->sales_id);
        $this->assertSame('lead', $lead->type);
    }

    public function test_lead_source_must_be_one_of_the_fixed_options(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);

        $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'new',
            'source' => 'Referral dari teman', // bebas teks lama — sudah tidak diterima
        ])->assertSessionHasErrors('source');

        foreach (['website', 'sponsor', 'bisnis', 'sosial_media', 'lainnya'] as $source) {
            $contact = Contact::create(['name' => "Customer {$source}", 'created_by' => $sales->id]);
            $this->actingAs($sales)->post('/sales/leads', [
                'contact_id' => $contact->id,
                'stage' => 'new',
                'source' => $source,
            ])->assertSessionDoesntHaveErrors('source');
        }
    }

    public function test_sales_can_set_and_update_customer_pic(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);

        $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'new',
            'pic_name' => 'Budi Santoso',
            'pic_position' => 'Manager Operasional',
        ]);

        $lead = Lead::firstOrFail();
        $this->assertSame('Budi Santoso', $lead->pic_name);
        $this->assertSame('Manager Operasional', $lead->pic_position);

        $this->actingAs($sales)->put("/sales/leads/{$lead->id}", [
            'contact_id' => $contact->id,
            'stage' => 'new',
            'pic_name' => 'Siti Aminah',
            'pic_position' => 'Site Supervisor',
        ]);

        $lead->refresh();
        $this->assertSame('Siti Aminah', $lead->pic_name);
        $this->assertSame('Site Supervisor', $lead->pic_position);
    }

    public function test_sales_cannot_create_lead_from_another_sales_contact(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Other Customer', 'created_by' => $other->id]);

        $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'new',
        ])->assertSessionHasErrors('contact_id');

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_sales_cannot_access_another_sales_lead(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $other->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $other->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        $this->actingAs($sales)->get("/sales/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")->assertForbidden();
    }

    public function test_sales_can_update_temperature_for_owned_lead_only(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $otherSales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'opportunity',
            'stage' => 'quotation',
        ]);

        $this->actingAs($sales)
            ->patch("/sales/leads/{$lead->id}/temperature", ['temperature' => 'hot'])
            ->assertSessionHasNoErrors();

        $this->assertSame('hot', $lead->fresh()->temperature);

        $this->actingAs($otherSales)
            ->patch("/sales/leads/{$lead->id}/temperature", ['temperature' => 'warm'])
            ->assertForbidden();

        $this->assertSame('hot', $lead->fresh()->temperature);
    }

    public function test_lead_temperature_rejects_unknown_value(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        $this->actingAs($sales)
            ->patch("/sales/leads/{$lead->id}/temperature", ['temperature' => 'very_hot'])
            ->assertSessionHasErrors('temperature');

        $this->assertSame('cold', $lead->fresh()->temperature);
    }

    public function test_owner_can_mark_unconverted_pipeline_as_lost(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'opportunity',
            'stage' => 'quotation',
        ]);

        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/mark-lost")
            ->assertSessionHas('success');

        $this->assertSame('lost', $lead->fresh()->stage);
    }

    public function test_owner_can_convert_new_lead_with_reachable_contact_in_one_step(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'created_by' => $sales->id,
        ]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        // Convert langsung dari stage 'new' -> tidak perlu diubah ke Qualified secara terpisah dulu.
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")
            ->assertRedirectToRoute('sales.leads.show', $lead);

        $lead->refresh();
        $this->assertSame('opportunity', $lead->type);
        $this->assertSame('qualified', $lead->stage);
    }

    public function test_convert_is_blocked_until_contact_is_reachable(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id,
            'sales_id' => $sales->id,
            'type' => 'lead',
            'stage' => 'new',
        ]);

        // Kontak belum punya telepon/email.
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")
            ->assertSessionHas('error');
        $this->assertSame('lead', $lead->fresh()->type);

        // Lengkapi kontak -> berhasil, sekaligus otomatis jadi Qualified.
        $contact->update(['phone' => '08123456789']);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/convert")
            ->assertSessionHas('success');
        $this->assertSame('opportunity', $lead->fresh()->type);
        $this->assertSame('qualified', $lead->fresh()->stage);
    }

    public function test_invalid_stage_is_rejected(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);

        $this->actingAs($sales)->post('/sales/leads', [
            'contact_id' => $contact->id,
            'stage' => 'anything',
        ])->assertSessionHasErrors('stage');
    }

    public function test_empty_lead_can_be_deleted(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'lead', 'stage' => 'new',
        ]);

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}")->assertRedirect('/sales/leads');

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_deleting_lead_cascades_requirement_procurement_request_and_quotation(): void
    {
        [$sales, $quotation] = $this->quotationForLead();
        $lead = $quotation->lead;
        $pr = $quotation->procurementRequest;

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}")->assertRedirect('/sales/leads');

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('requirements', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('procurement_requests', ['id' => $pr->id]);
        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
    }

    public function test_lead_cannot_be_deleted_once_a_quotation_sales_order_has_invoice(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder();
        $lead = $salesOrder->quotation->lead;
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $salesOrder->id, 'phase' => 'full']);

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}")->assertForbidden();

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
        $this->assertDatabaseHas('sales_orders', ['id' => $salesOrder->id]);
    }

    public function test_lead_cannot_be_deleted_once_a_quotation_sales_order_has_project(): void
    {
        [$sales, $salesOrder] = $this->confirmedSalesOrder('mixed');
        $lead = $salesOrder->quotation->lead;
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $salesOrder->id, 'phase' => 'dp']);
        $invoice = $salesOrder->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('projects', ['sales_order_id' => $salesOrder->id]);

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}")->assertForbidden();

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_lead_cannot_be_deleted_once_a_survey_has_invoice(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
        ]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/surveys", [
            'site_address' => 'Jl. Uji Coba No. 1',
            'site_region' => 'Sidoarjo',
            'delivery_mode' => 'vendor',
            'billable' => true,
        ]);
        $survey = Survey::where('lead_id', $lead->id)->firstOrFail();
        $survey->update(['status' => 'finance_review', 'cost' => 500000]);
        $finance = User::factory()->create(['role' => 'finance', 'is_active' => true]);
        $this->actingAs($finance)->post("/finance/surveys/{$survey->id}/invoice", [])->assertSessionHas('success');

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}")->assertForbidden();

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
        $this->assertDatabaseHas('surveys', ['id' => $survey->id]);
    }

    public function test_deleting_lead_removes_sales_order_attachments_and_notifications(): void
    {
        Storage::fake('local');
        [$sales, $salesOrder] = $this->confirmedSalesOrder();
        $quotation = $salesOrder->quotation;
        $lead = $quotation->lead;

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

        $this->actingAs($sales)->delete("/sales/leads/{$lead->id}")->assertRedirect();

        $this->assertDatabaseMissing('attachments', ['attachable_type' => $salesOrder->getMorphClass(), 'attachable_id' => $salesOrder->id]);
        Storage::disk('local')->assertMissing('sales-orders/signed-quotation.pdf');
        $this->assertDatabaseMissing('notifications', ['related_type' => $salesOrder->getMorphClass(), 'related_id' => $salesOrder->id]);
    }

    /** @return array{User, Quotation} */
    private function quotationForLead(): array
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'created_by' => $sales->id]);
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

        return [$sales, Quotation::where('procurement_request_id', $pr->id)->with('lead', 'procurementRequest')->firstOrFail()];
    }

    /** @return array{User, SalesOrder} */
    private function confirmedSalesOrder(string $orderType = 'material_only'): array
    {
        [$sales, $quotation] = $this->quotationForLead();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload($orderType));

        return [$sales, SalesOrder::with('quotation.lead')->where('quotation_id', $quotation->id)->firstOrFail()];
    }
}
