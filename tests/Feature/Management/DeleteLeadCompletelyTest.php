<?php

namespace Tests\Feature\Management;

use App\Models\ActualProcurement;
use App\Models\Attachment;
use App\Models\Bast;
use App\Models\Contact;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Sow;
use App\Models\SowScopeSection;
use App\Models\Survey;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorServicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProcurementProject;
use Tests\TestCase;

/**
 * Regresi paling penting untuk fitur "Hapus Total" Manajemen: setiap tabel yang
 * pernah tersentuh sepanjang perjalanan satu Lead (quotation, sales order, invoice,
 * project, procurement, SOW, BAST, deal vendor jasa, survey, dst) harus benar-benar
 * bersih setelahnya — tidak ada baris menggantung, tidak ada file tersisa di disk —
 * TAPI data milik Lead lain sama sekali tidak boleh ikut tersentuh.
 */
class DeleteLeadCompletelyTest extends TestCase
{
    use BuildsProcurementProject;
    use RefreshDatabase;

    public function test_management_deletes_every_related_row_and_file_but_leaves_other_leads_untouched(): void
    {
        Storage::fake('local');

        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);

        // ── Project yang TIDAK BOLEH ikut terhapus (punya lead sendiri) ──
        $untouched = $this->materialProject();
        $this->settleProcurement($untouched);
        // Alur asli (confirm quotation, bayar invoice, proses procurement) sendiri
        // sudah menghasilkan beberapa notifikasi & attachment (mis. dokumen quotation
        // tertanda tangan) — dijadikan angka dasar, BUKAN diasumsikan nol, supaya
        // assersi di bawah benar-benar membuktikan "cuma milik $project yang hilang".
        $untouchedNotificationCount = Notification::count();
        $untouchedAttachmentCount = Attachment::count();

        // ── Project yang akan dihapus total, dilengkapi semua jenis anak data ──
        $project = $this->materialProject();
        $this->settleProcurement($project);
        $project->refresh();
        $lead = $project->salesOrder->quotation->lead;
        $so = $project->salesOrder;
        $quotation = $so->quotation;
        $invoice = $so->invoices()->firstOrFail();
        $ops = User::factory()->create(['role' => 'operational', 'is_active' => true]);
        $vendor = Vendor::create(['name' => 'Vendor Uji Hapus', 'provides_technical' => true]);

        // Payment attachment.
        $payment = $invoice->payments()->firstOrFail();
        $payment->attachments()->create([
            'category' => 'payment_proof',
            'file_path' => UploadedFile::fake()->create('bukti.pdf', 10)->store('payment-proofs'),
        ]);

        // Sales Order & Invoice & Project attachment.
        foreach ([$so, $invoice, $project] as $entity) {
            $entity->attachments()->create([
                'category' => 'other',
                'file_path' => UploadedFile::fake()->image('doc.jpg')->store('misc'),
            ]);
        }

        // Project Task + lampiran before/after.
        $task = ProjectTask::create(['project_id' => $project->id, 'title' => 'Tugas Uji', 'status' => 'pending']);
        $task->attachments()->create([
            'category' => 'task_before',
            'file_path' => UploadedFile::fake()->image('before.jpg')->store('task-photos'),
        ]);

        // Bast + lampiran.
        $bast = Bast::create(['project_id' => $project->id, 'status' => 'submitted', 'submitted_by' => $ops->id, 'submitted_at' => now()]);
        $bast->attachments()->create([
            'category' => 'bast_document',
            'file_path' => UploadedFile::fake()->create('bast.pdf', 5)->store('bast'),
        ]);

        // SOW + scope section + lampiran.
        $sow = Sow::create(['project_id' => $project->id, 'status' => 'draft', 'number' => 'SOW-DEL-001', 'project_name' => 'Uji Hapus']);
        $sow->attachments()->create([
            'category' => 'other',
            'file_path' => UploadedFile::fake()->image('sow.jpg')->store('sow'),
        ]);
        $section = SowScopeSection::create(['sow_id' => $sow->id, 'key' => 'custom', 'title' => 'Custom', 'body' => 'x', 'active' => true, 'position' => 1]);

        // Deal Vendor Jasa + entry + lampiran.
        $vendorPayment = VendorServicePayment::create([
            'project_id' => $project->id, 'number' => 'VSP-DEL-001', 'vendor_id' => $vendor->id,
            'total_fee' => 1000000, 'terms' => 'dp_final', 'dp_amount' => 500000,
            'bank_name' => 'BCA', 'account_number' => '123', 'account_holder' => 'Vendor', 'status' => 'awaiting_dp',
        ]);
        $entry = $vendorPayment->entries()->create(['kind' => 'dp', 'amount' => 500000, 'paid_at' => now(), 'paid_by' => $management->id]);
        $entry->attachments()->create([
            'category' => 'payment_proof',
            'file_path' => UploadedFile::fake()->create('vsp.pdf', 5)->store('vsp'),
        ]);

        // Delivery Note.
        $deliveryNote = DeliveryNote::create([
            'number' => 'DN-DEL-001', 'sales_order_id' => $so->id, 'delivery_method' => 'sendiri',
            'delivery_address' => 'Jl. Uji Hapus', 'status' => 'sent', 'created_by' => $ops->id,
        ]);
        $deliveryNote->attachments()->create([
            'category' => 'other',
            'file_path' => UploadedFile::fake()->image('dn.jpg')->store('dn'),
        ]);

        // Meeting.
        $meeting = Meeting::create(['lead_id' => $lead->id, 'title' => 'Rapat Uji', 'meeting_date' => now()->toDateString(), 'notes' => 'x', 'created_by' => $management->id]);

        // Survey + report + invoice (billing terpisah) + lampiran.
        $sales = $lead->sales;
        $survey = Survey::create([
            'lead_id' => $lead->id, 'requested_by' => $sales->id, 'site_address' => 'Jl. Uji', 'site_region' => 'Jakarta',
            'delivery_mode' => 'internal', 'billable' => true, 'status' => 'verified', 'cost' => 200000,
        ]);
        $surveyInvoice = Invoice::create([
            'number' => '1/GS-SRV/09/2026', 'invoice_type' => 'survey', 'survey_id' => $survey->id,
            'status' => 'draft', 'amount' => 200000, 'tax_amount' => 0, 'created_by' => $sales->id,
        ]);
        $report = $survey->report()->create(['revision' => 1, 'status' => 'verified', 'summary' => 'Aman']);
        $report->attachments()->create([
            'category' => 'other',
            'file_path' => UploadedFile::fake()->image('report.jpg')->store('survey-report'),
        ]);

        // Notifikasi menunjuk ke beberapa entitas yang akan lenyap.
        Notification::create(['user_id' => $management->id, 'type' => 'quotation.pending_manager_review', 'title' => 'x', 'message' => 'x', 'related_type' => $quotation->getMorphClass(), 'related_id' => $quotation->id]);
        Notification::create(['user_id' => $management->id, 'type' => 'sow.completed', 'title' => 'x', 'message' => 'x', 'related_type' => $project->getMorphClass(), 'related_id' => $project->id]);

        // ── Kumpulkan file milik rantai $project saja (bukan milik $untouched) yang
        // harus lenyap dari disk setelah dihapus. ──
        $filePaths = Attachment::whereNotIn('id', Attachment::where('attachable_type', SalesOrder::class)->where('attachable_id', $untouched->salesOrder->id)->pluck('id'))
            ->pluck('file_path')->all();
        $proof = $project->procurementPayments()->firstOrFail()->proofs()->first();
        $this->assertNotNull($proof, 'Test setup harus sudah punya bukti pembayaran procurement.');

        // ── Eksekusi penghapusan ──
        $this->actingAs($management)
            ->delete("/management/opportunities/{$lead->id}")
            ->assertRedirect('/management/opportunities')
            ->assertSessionHas('success');

        // ── Rantai milik lead ini harus benar-benar bersih ──
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
        $this->assertDatabaseMissing('sales_orders', ['id' => $so->id]);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $surveyInvoice->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('procurement_requests', ['lead_id' => $lead->id]);
        $this->assertDatabaseCount('actual_procurements', ActualProcurement::where('project_id', $untouched->id)->count());
        $this->assertDatabaseMissing('procurement_payments', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('procurement_payment_proofs', ['id' => $proof->id]);
        $this->assertDatabaseMissing('project_tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('bast', ['id' => $bast->id]);
        $this->assertDatabaseMissing('sows', ['id' => $sow->id]);
        $this->assertDatabaseMissing('sow_scope_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('vendor_service_payments', ['id' => $vendorPayment->id]);
        $this->assertDatabaseMissing('vendor_service_payment_entries', ['id' => $entry->id]);
        $this->assertDatabaseMissing('delivery_notes', ['id' => $deliveryNote->id]);
        $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
        $this->assertDatabaseMissing('surveys', ['id' => $survey->id]);
        $this->assertDatabaseMissing('survey_reports', ['id' => $report->id]);
        // Yang tersisa cuma sebanyak baseline milik $untouched (mis. dokumen quotation
        // tertanda tangan, notifikasi dari alur confirm/bayar/procurement-nya sendiri)
        // — bukan nol mentah, supaya assersi ini betul-betul membuktikan "cuma milik
        // $project yang hilang", bukan kebetulan cocok karena semua notifikasi terhapus.
        $this->assertDatabaseCount('attachments', $untouchedAttachmentCount);
        $this->assertDatabaseHas('attachments', ['attachable_type' => SalesOrder::class, 'attachable_id' => $untouched->salesOrder->id]);
        $this->assertDatabaseCount('notifications', $untouchedNotificationCount);

        foreach ($filePaths as $path) {
            Storage::disk('local')->assertMissing($path);
        }

        // ── Contact TIDAK ikut terhapus. ──
        $this->assertDatabaseHas('contacts', ['id' => $lead->contact_id]);

        // ── Project/lead lain sama sekali tidak tersentuh. ──
        $this->assertDatabaseHas('projects', ['id' => $untouched->id]);
        $this->assertDatabaseHas('leads', ['id' => $untouched->salesOrder->quotation->lead_id]);
    }

    public function test_non_management_cannot_delete_a_lead_this_way(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $contact = Contact::create(['name' => 'Cust', 'created_by' => $sales->id]);
        $lead = Lead::create(['contact_id' => $contact->id, 'sales_id' => $sales->id, 'type' => 'opportunity', 'stage' => 'new']);

        $this->actingAs($sales)->delete("/management/opportunities/{$lead->id}")->assertForbidden();
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }

    public function test_management_can_delete_even_a_lead_with_real_payments_and_a_running_project(): void
    {
        $management = User::factory()->create(['role' => 'management', 'is_active' => true]);
        $project = $this->materialProject();
        $lead = $project->salesOrder->quotation->lead;

        // Kalau ini pakai Sales\DeleteLead / LeadPolicy::delete() biasa, akan ditolak
        // karena sudah ada Invoice + Project. forceDelete() Manajemen harus tetap tembus.
        $this->actingAs($management)->delete("/management/opportunities/{$lead->id}")->assertRedirect();
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
