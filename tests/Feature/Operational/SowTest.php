<?php

namespace Tests\Feature\Operational;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SowTest extends TestCase
{
    use RefreshDatabase;

    private function projectWithVendor(): array
    {
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Customer', 'company_name' => 'PT Customer', 'address' => 'Jl. Lokasi No. 1', 'created_by' => $sales->id]);
        $lead = Lead::create([
            'contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'qualified',
            'pic_name' => 'Avima', 'pic_phone' => '0857-0000-0000',
        ]);
        $lead->requirements()->create(['item_name' => 'ODP', 'qty' => 4, 'unit' => 'unit', 'created_by' => $sales->id]);
        $this->actingAs($sales)->post("/sales/leads/{$lead->id}/submit-procurement");
        $pr = $lead->procurementRequests()->with('lines')->latest('id')->firstOrFail();
        $pr->lines()->update(['cost_price' => 1000000, 'availability_status' => 'available']);
        $pr->update(['status' => 'ready']);
        $this->actingAs($sales)->post("/sales/procurement-requests/{$pr->id}/quotations", [
            'lines' => [['procurement_request_line_id' => $pr->lines()->first()->id, 'selling_price' => 1300000]],
        ]);
        $quotation = $pr->quotations()->latest('id')->firstOrFail();
        $quotation->update(['status' => 'sent']);
        $this->actingAs($sales)->post("/sales/quotations/{$quotation->id}/confirm", $this->confirmPayload('mixed'));
        $so = $quotation->salesOrder()->firstOrFail();

        $finance = User::factory()->create(['role' => 'finance']);
        $this->actingAs($finance)->post('/finance/invoices', ['sales_order_id' => $so->id, 'phase' => 'dp']);
        $invoice = $so->invoices()->latest('id')->firstOrFail();
        $this->actingAs($finance)->post("/finance/invoices/{$invoice->id}/payments", [
            'amount_paid' => (float) $invoice->amount + (float) $invoice->tax_amount,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $project = Project::where('sales_order_id', $so->id)->firstOrFail();
        $vendor = Vendor::create(['name' => 'Vendor A', 'provides_technical' => true, 'contact_person' => 'PIC Vendor A', 'phone' => '0811111111']);
        $project->update(['vendor_id' => $vendor->id]);
        $technician = User::factory()->create(['role' => 'technician', 'vendor_id' => $vendor->id, 'is_active' => true]);

        return [$project, $ops, $vendor, $technician];
    }

    public function test_cannot_manage_sow_before_project_marked_with_vendor(): void
    {
        [$project, $ops] = $this->projectWithVendor();
        $project->update(['vendor_id' => null]);

        $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow")->assertForbidden();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['number' => 'SOW-001'])->assertForbidden();
    }

    public function test_sow_form_prefills_from_lead_and_contact(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $response = $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow");
        $response->assertOk();
        $sow = $response->viewData('page')['props']['sow'];
        $this->assertSame('Jl. Lokasi No. 1', $sow['site_location']);
        $this->assertSame('PT Customer', $sow['client_name']);
        $this->assertSame('Avima', $sow['client_pic_name']);
        $this->assertSame('0857-0000-0000', $sow['client_pic_phone']);

        $vendor = $response->viewData('page')['props']['vendor'];
        $this->assertSame('PIC Vendor A', $vendor['contact_person']);

        $techOptions = $response->viewData('page')['props']['technicianOptions'];
        $this->assertCount(1, $techOptions);
    }

    public function test_default_texts_are_visible_before_first_save(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $response = $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow");
        $sow = $response->viewData('page')['props']['sow'];

        $this->assertNull($sow['id']);
        $this->assertStringContainsString('Vendor:', $sow['responsibilities']);
        $this->assertStringContainsString('APD', $sow['safety']);
        $this->assertStringContainsString('DP dibayarkan', $sow['payment_terms']);
        $this->assertStringContainsString('As-Built', $sow['output']);
        $this->assertStringContainsString('garansi', $sow['warranty']);
        $this->assertStringContainsString('GPS Map Camera', $sow['notes']);
        $this->assertStringContainsString('yang tercantum pada dokumen ini', $sow['closing']);
    }

    public function test_operational_fills_structured_schedule_fields(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'project_name' => 'Jasa X',
            'schedule_duration' => '3 – 5 hari',
            'schedule_start_date' => '2026-08-23',
            'schedule_end_date' => '2026-08-27',
        ])->assertRedirect();

        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('3 – 5 hari', $sow->schedule_duration);
        $this->assertSame('2026-08-23', $sow->schedule_start_date->format('Y-m-d'));
        $this->assertSame('2026-08-27', $sow->schedule_end_date->format('Y-m-d'));
    }

    public function test_schedule_dates_are_returned_as_plain_date_strings_not_datetimes(): void
    {
        [$project, $ops] = $this->projectWithVendor();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'project_name' => 'Jasa X',
            'schedule_start_date' => '2026-08-23',
            'schedule_end_date' => '2026-08-27',
        ]);

        $response = $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow");
        $sow = $response->viewData('page')['props']['sow'];

        $this->assertSame('2026-08-23', $sow['schedule_start_date']);
        $this->assertSame('2026-08-27', $sow['schedule_end_date']);

        // Simulasi round-trip: kirim ulang persis nilai yang diterima dari GET tadi
        // — ini yang gagal sebelum perbaikan (Carbon object bocor sebagai datetime ISO penuh).
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'project_name' => 'Jasa X',
            'schedule_start_date' => $sow['schedule_start_date'],
            'schedule_end_date' => $sow['schedule_end_date'],
        ])->assertSessionDoesntHaveErrors()->assertRedirect();
    }

    public function test_schedule_end_date_cannot_be_before_start_date(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'project_name' => 'Jasa X',
            'schedule_start_date' => '2026-08-27',
            'schedule_end_date' => '2026-08-23',
        ])->assertSessionHasErrors('schedule_end_date');
    }

    public function test_operational_can_save_draft_and_submit_to_hr(): void
    {
        [$project, $ops, , $technician] = $this->projectWithVendor();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001',
            'project_name' => 'Jasa Terminasi FO',
            'technician_id' => $technician->id,
        ])->assertRedirect();

        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('draft', $sow->status);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit")->assertRedirect();
        $sow->refresh();
        $this->assertSame('pending_hr_review', $sow->status);
        $this->assertNotNull($sow->submitted_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id,
            'type' => 'sow.pending_hr_review',
            'related_id' => $sow->id,
        ]);
    }

    public function test_submit_requires_number_project_name_and_technician(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['number' => 'SOW-001']);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit")
            ->assertSessionHasErrors('sow');

        $this->assertSame('draft', Sow::where('project_id', $project->id)->firstOrFail()->status);
    }

    public function test_number_is_auto_generated_when_left_blank_on_first_save(): void
    {
        [$project, $ops] = $this->projectWithVendor();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['project_name' => 'Jasa X']);

        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $expected = '1/GS-SOW/'.now()->format('m').'/'.now()->year;
        $this->assertSame($expected, $sow->number);
    }

    public function test_number_sequence_increments_and_manual_override_is_kept(): void
    {
        [$projectA, $ops] = $this->projectWithVendor();
        [$projectB] = $this->projectWithVendor();
        [$projectC] = $this->projectWithVendor();

        $this->actingAs($ops)->put("/operational/projects/{$projectA->id}/sow", ['project_name' => 'Jasa A']);
        $this->actingAs($ops)->put("/operational/projects/{$projectB->id}/sow", ['number' => 'SOW-MANUAL', 'project_name' => 'Jasa B']);
        $this->actingAs($ops)->put("/operational/projects/{$projectC->id}/sow", ['project_name' => 'Jasa C']);

        $month = now()->format('m');
        $year = now()->year;
        $this->assertSame("1/GS-SOW/{$month}/{$year}", Sow::where('project_id', $projectA->id)->firstOrFail()->number);
        $this->assertSame('SOW-MANUAL', Sow::where('project_id', $projectB->id)->firstOrFail()->number);
        $this->assertSame("2/GS-SOW/{$month}/{$year}", Sow::where('project_id', $projectC->id)->firstOrFail()->number);
    }

    public function test_default_texts_and_material_scope_section_are_seeded_on_first_save(): void
    {
        [$project, $ops] = $this->projectWithVendor();
        $project->actualProcurements()->create([
            'item_name' => 'ODP Outdoor 8 core', 'qty' => 4, 'unit' => 'unit',
            'cost_price' => 500000, 'from_office_stock' => false, 'status' => 'pending',
        ]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['project_name' => 'Jasa X']);

        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $this->assertStringContainsString('Vendor:', $sow->responsibilities);
        $this->assertStringContainsString('Client:', $sow->responsibilities);
        $this->assertStringContainsString('APD', $sow->safety);
        $this->assertStringContainsString('DP dibayarkan', $sow->payment_terms);
        $this->assertStringContainsString('As-Built', $sow->output);
        $this->assertStringContainsString('garansi', $sow->warranty);
        $this->assertStringContainsString('GPS Map Camera', $sow->notes);
        $this->assertStringContainsString('Jasa X', $sow->closing);

        $material = $sow->scopeSections()->firstOrFail();
        $this->assertSame('Pengadaan Material', $material->title);
        $this->assertStringContainsString('4.00 unit — ODP Outdoor 8 core', $material->content);
    }

    public function test_defaults_are_not_overwritten_on_subsequent_saves(): void
    {
        [$project, $ops] = $this->projectWithVendor();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['project_name' => 'Jasa X']);
        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $sow->update(['safety' => 'Custom safety']);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['project_name' => 'Jasa X', 'safety' => '']);

        $this->assertNull($sow->fresh()->safety);
    }

    public function test_operational_manages_scope_sections(): void
    {
        [$project, $ops] = $this->projectWithVendor();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['project_name' => 'Jasa X']);
        $sow = Sow::where('project_id', $project->id)->firstOrFail();

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/scope-sections", [
            'title' => 'Instalasi & Pemasangan', 'content' => 'Pemasangan ODP dan Roset.',
        ])->assertRedirect();

        $this->assertDatabaseHas('sow_scope_sections', [
            'sow_id' => $sow->id, 'title' => 'Instalasi & Pemasangan', 'position' => 2,
        ]);

        $section = $sow->scopeSections()->where('title', 'Instalasi & Pemasangan')->firstOrFail();

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow/scope-sections/{$section->id}", [
            'title' => 'Instalasi & Pemasangan Perangkat', 'content' => 'Diperbarui.',
        ])->assertRedirect();
        $this->assertSame('Instalasi & Pemasangan Perangkat', $section->fresh()->title);

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/scope-sections/{$section->id}/move", [
            'direction' => 'up',
        ])->assertRedirect();
        $material = $sow->scopeSections()->where('title', 'Pengadaan Material')->firstOrFail();
        $this->assertSame(2, $material->fresh()->position);
        $this->assertSame(1, $section->fresh()->position);

        $this->actingAs($ops)->delete("/operational/projects/{$project->id}/sow/scope-sections/{$section->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('sow_scope_sections', ['id' => $section->id]);
    }

    public function test_operational_uploads_and_deletes_scope_section_image(): void
    {
        Storage::fake('local');
        [$project, $ops] = $this->projectWithVendor();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['project_name' => 'Jasa X']);
        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $section = $sow->scopeSections()->firstOrFail();

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/scope-sections/{$section->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertRedirect();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => \App\Models\SowScopeSection::class,
            'attachable_id' => $section->id,
            'category' => 'sow_scope_image',
        ]);

        $image = $section->attachments()->firstOrFail();
        $this->actingAs($ops)->delete("/operational/projects/{$project->id}/sow/scope-sections/{$section->id}/images/{$image->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('attachments', ['id' => $image->id]);
    }

    public function test_editing_sow_after_it_was_sent_resets_review_and_signatures_to_draft(): void
    {
        [$project, $ops, $vendor, $technician] = $this->projectWithVendor();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'vendor_id' => $vendor->id, 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ]);
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit");
        $sow = Sow::where('project_id', $project->id)->firstOrFail();

        $hrReviewer = User::factory()->create(['role' => 'hr', 'is_active' => true]);
        $this->actingAs($hrReviewer)->post("/hr/sows/{$sow->id}/review", ['approved' => true]);
        $this->actingAs($technician)->post("/technician/sows/{$sow->id}/sign", ['signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=']);
        $sow->refresh();
        $this->assertSame('pending_vendor_signature', $sow->status);

        // Boleh diedit meski sudah lewat dari Draft — sekarang tidak lagi forbidden.
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-002', 'project_name' => 'Jasa Y (revisi)', 'technician_id' => $technician->id,
        ])->assertRedirect();

        $sow->refresh();
        $this->assertSame('draft', $sow->status);
        $this->assertSame('Jasa Y (revisi)', $sow->project_name);
        $this->assertNull($sow->submitted_at);
        $this->assertNull($sow->technician_signature);
        $this->assertNull($sow->technician_signed_at);
        $this->assertNull($sow->vendor_signature);
        $this->assertNull($sow->hr_content_reviewed_by);

        // Sub-bab ruang lingkup juga kembali bisa diubah.
        $section = $sow->scopeSections()->firstOrFail();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow/scope-sections/{$section->id}", [
            'title' => 'Ubah', 'content' => 'x',
        ])->assertRedirect();
        $this->assertSame('Ubah', $section->fresh()->title);

        // Pihak yang sebelumnya sudah terlibat (teknisi, karena statusnya sudah lewat
        // pending_technician_signature) diberi tahu bahwa SOW direset.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $technician->id, 'type' => 'sow.reset_for_edit', 'related_id' => $sow->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id, 'type' => 'sow.reset_for_edit', 'related_id' => $sow->id,
        ]);
        // PIC Vendor belum sempat melihat SOW ini (masih pending_technician_signature saat
        // teknisi baru saja tanda tangan dan lompat ke pending_vendor_signature — vendor
        // sudah boleh lihat begitu status itu tercapai), jadi ikut diberi tahu.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $vendorUser->id, 'type' => 'sow.reset_for_edit', 'related_id' => $sow->id,
        ]);

        // Bisa dikirim ulang ke HR dari awal.
        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/submit")->assertRedirect();
        $this->assertSame('pending_hr_review', $sow->fresh()->status);
    }

    public function test_cannot_edit_sow_once_completed(): void
    {
        [$project, $ops, $vendor, $technician] = $this->projectWithVendor();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ]);
        $sow = Sow::where('project_id', $project->id)->firstOrFail();
        $sow->update(['status' => 'completed']);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-002', 'project_name' => 'Jasa Y', 'technician_id' => $technician->id,
        ])->assertForbidden();

        // Tapi tetap bisa dilihat (read-only).
        $this->actingAs($ops)->get("/operational/projects/{$project->id}/sow")->assertOk();
    }

    public function test_technician_options_are_scoped_to_the_projects_vendor(): void
    {
        [$project, $ops, , $technician] = $this->projectWithVendor();
        $otherVendor = Vendor::create(['name' => 'Vendor B', 'provides_technical' => true]);
        $otherTechnician = User::factory()->create(['role' => 'technician', 'vendor_id' => $otherVendor->id, 'is_active' => true]);

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $otherTechnician->id,
        ])->assertSessionHasErrors('technician_id');

        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", [
            'number' => 'SOW-001', 'project_name' => 'Jasa X', 'technician_id' => $technician->id,
        ])->assertSessionDoesntHaveErrors();
    }

    public function test_can_upload_and_delete_background_images(): void
    {
        Storage::fake('local');
        [$project, $ops] = $this->projectWithVendor();
        $this->actingAs($ops)->put("/operational/projects/{$project->id}/sow", ['number' => 'SOW-001']);
        $sow = Sow::where('project_id', $project->id)->firstOrFail();

        $this->actingAs($ops)->post("/operational/projects/{$project->id}/sow/images", [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect();

        $this->assertSame(2, $sow->attachments()->count());
        $image = $sow->attachments()->first();

        $this->actingAs($ops)->delete("/operational/projects/{$project->id}/sow/images/{$image->id}")->assertRedirect();
        $this->assertSame(1, $sow->attachments()->count());
    }

    public function test_non_operational_cannot_access_sow(): void
    {
        [$project] = $this->projectWithVendor();
        $hr = User::factory()->create(['role' => 'hr', 'is_active' => true]);

        $this->actingAs($hr)->get("/operational/projects/{$project->id}/sow")->assertForbidden();
    }
}
