<?php

namespace App\Actions\Management;

use App\Models\Bast;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus SATU Lead beserta SELURUH rantai data di bawahnya sampai bersih total —
 * dipakai Manajemen untuk membersihkan data dummy/coba-coba, bukan Sales\DeleteLead
 * yang sengaja dikunci begitu ada Invoice/Project/pembayaran (lihat LeadPolicy::delete()).
 * Aksi ini TIDAK punya kunci semacam itu — dipanggil setelah LeadPolicy::forceDelete()
 * memastikan yang memanggil memang Manajemen, karena ini permanen dan tidak bisa
 * dibatalkan.
 *
 * Urutan hapus mengikuti aturan foreign key di database (lihat migrations):
 * - Bast, Invoice (+Payment), Delivery Note WAJIB dihapus manual dulu sebelum Project/
 *   Sales Order (constrained ->restrictOnDelete(), tidak auto-cascade).
 * - Task, tim teknisi, actual procurement, pembayaran procurement (+ bukti), SOW
 *   (+ scope section), draft BAST, deal vendor jasa (+ entry) — semua ini SUDAH
 *   ->cascadeOnDelete() ke Project, jadi otomatis ikut hilang begitu Project dihapus,
 *   tidak perlu disentuh manual di sini.
 * - Requirement, Meeting, Survey (+ laporan, tim surveyor) — sudah ->cascadeOnDelete()
 *   ke Lead, otomatis ikut hilang begitu Lead dihapus di baris terakhir.
 *
 * Yang TIDAK auto-cascade dan wajib dibersihkan manual di sini:
 * - Attachment (disimpan polimorfik tanpa foreign key) beserta file fisiknya di disk,
 *   untuk setiap entitas yang punya lampiran di sepanjang rantai ini.
 * - Notification (related_type/related_id polos tanpa foreign key) untuk setiap
 *   entitas yang ikut lenyap, supaya tidak ada notifikasi menggantung.
 * - Invoice tagihan Survey — relasinya ke Survey ->nullOnDelete() (bukan cascade),
 *   jadi kalau tidak dihapus manual akan jadi invoice yatim piatu.
 *
 * Contact TIDAK ikut dihapus — satu Contact bisa dipakai Lead lain juga.
 */
class DeleteLeadCompletely
{
    public function handle(Lead $lead): void
    {
        DB::transaction(function () use ($lead) {
            $this->deleteNotificationsFor($lead);

            foreach (Quotation::where('lead_id', $lead->id)->get() as $quotation) {
                $this->deleteNotificationsFor($quotation);

                if ($salesOrder = $quotation->salesOrder) {
                    $this->deleteSalesOrder($salesOrder);
                }

                $quotation->delete();
            }

            // Procurement Request baru bisa dihapus setelah semua Quotation di atasnya
            // hilang (quotations.procurement_request_id ->restrictOnDelete()).
            foreach (ProcurementRequest::where('lead_id', $lead->id)->get() as $procurementRequest) {
                $this->deleteNotificationsFor($procurementRequest);
                $procurementRequest->delete();
            }

            foreach (Survey::where('lead_id', $lead->id)->with(['report', 'invoices'])->get() as $survey) {
                // Pakai invoices() (jamak, semua status) bukan invoice() (cuma yang aktif),
                // supaya invoice survey yang sudah dibatalkan pun ikut bersih, tidak jadi
                // yatim piatu (Invoice.survey_id ->nullOnDelete(), bukan cascade).
                foreach ($survey->invoices as $invoice) {
                    $this->deleteInvoice($invoice);
                }

                $this->deleteAttachmentsFor($survey);
                $this->deleteNotificationsFor($survey);

                if ($survey->report) {
                    $this->deleteAttachmentsFor($survey->report);
                    $this->deleteNotificationsFor($survey->report);
                }
            }

            // Requirement, Meeting, dan Survey (+ report, surveyors) ikut terhapus
            // otomatis lewat foreign key cascadeOnDelete begitu Lead ini dihapus.
            $lead->delete();
        });
    }

    private function deleteSalesOrder(SalesOrder $salesOrder): void
    {
        foreach ($salesOrder->projects as $project) {
            $this->deleteProject($project);
        }

        foreach ($salesOrder->invoices as $invoice) {
            $this->deleteInvoice($invoice);
        }

        foreach ($salesOrder->deliveryNotes as $deliveryNote) {
            $this->deleteAttachmentsFor($deliveryNote);
            $this->deleteNotificationsFor($deliveryNote);
        }
        $salesOrder->deliveryNotes()->delete();

        $this->deleteAttachmentsFor($salesOrder);
        $this->deleteNotificationsFor($salesOrder);
        $salesOrder->delete();
    }

    private function deleteProject(Project $project): void
    {
        // Bast (bukan draft-nya) ->restrictOnDelete() ke Project, wajib dihapus manual
        // duluan — sisanya (task, actual procurement, pembayaran procurement+bukti, SOW+
        // scope section, draft BAST, deal vendor jasa+entry, tim teknisi, change request)
        // sudah cascadeOnDelete, otomatis ikut hilang begitu Project di bawah ini dihapus.
        foreach ($project->tasks as $task) {
            $this->deleteAttachmentsFor($task);
            $this->deleteNotificationsFor($task);
        }

        foreach ($project->actualProcurements as $actualProcurement) {
            $this->deleteNotificationsFor($actualProcurement);
        }

        if ($sow = $project->sow) {
            $this->deleteAttachmentsFor($sow);
            $this->deleteNotificationsFor($sow);
            foreach ($sow->scopeSections as $section) {
                $this->deleteAttachmentsFor($section);
            }
        }

        foreach ($project->procurementPayments as $payment) {
            $this->deleteNotificationsFor($payment);
            foreach ($payment->proofs as $proof) {
                if ($proof->file_path) {
                    Storage::disk('local')->delete($proof->file_path);
                }
            }
        }

        if ($vendorServicePayment = $project->vendorServicePayment) {
            $this->deleteNotificationsFor($vendorServicePayment);
            foreach ($vendorServicePayment->entries as $entry) {
                $this->deleteAttachmentsFor($entry);
            }
        }

        foreach (Bast::where('project_id', $project->id)->get() as $bast) {
            $this->deleteAttachmentsFor($bast);
            $this->deleteNotificationsFor($bast);
            $bast->delete();
        }

        $this->deleteAttachmentsFor($project);
        $this->deleteNotificationsFor($project);
        $project->delete();
    }

    private function deleteInvoice(Invoice $invoice): void
    {
        foreach ($invoice->payments as $payment) {
            $this->deleteAttachmentsFor($payment);
        }

        $this->deleteAttachmentsFor($invoice);
        $this->deleteNotificationsFor($invoice);
        $invoice->delete();
    }

    private function deleteAttachmentsFor(Model $related): void
    {
        /** @var MorphMany $attachments */
        $attachments = $related->attachments();
        $attachments->get()->each(
            fn ($attachment) => Storage::disk('local')->delete($attachment->file_path)
        );
        $attachments->delete();
    }

    private function deleteNotificationsFor(Model $related): void
    {
        Notification::where('related_type', $related->getMorphClass())
            ->where('related_id', $related->getKey())
            ->delete();
    }
}
