<?php

namespace App\Actions\Sales;

use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus Lead beserta seluruh data di bawahnya (Requirement, Procurement Request,
 * Quotation, Survey) — dipanggil hanya setelah LeadPolicy::delete() memastikan tidak
 * ada quotation dengan Sales Order berInvoice/berProject dan tidak ada survey
 * berinvoice, jadi tidak ada jejak transaksi nyata yang ikut hilang.
 *
 * Requirement, Meeting, dan Survey sendiri sudah di-cascade oleh foreign key saat
 * Lead dihapus — action ini fokus membereskan yang TIDAK otomatis: dokumen
 * (attachment, disimpan polimorfik tanpa foreign key) dan notifikasi yang menunjuk
 * ke quotation/Sales Order/survey yang ikut lenyap, supaya tidak ada file atau
 * notifikasi menggantung setelahnya.
 */
class DeleteLead
{
    public function handle(Lead $lead): void
    {
        DB::transaction(function () use ($lead) {
            $this->deleteNotificationsFor($lead);

            foreach (Quotation::where('lead_id', $lead->id)->get() as $quotation) {
                $this->deleteNotificationsFor($quotation);

                if ($salesOrder = $quotation->salesOrder) {
                    $salesOrder->attachments()->get()->each(
                        fn ($attachment) => Storage::disk('local')->delete($attachment->file_path)
                    );
                    $salesOrder->attachments()->delete();

                    $this->deleteNotificationsFor($salesOrder);

                    $salesOrder->delete();
                }

                $quotation->delete();
            }

            foreach (ProcurementRequest::where('lead_id', $lead->id)->get() as $procurementRequest) {
                $this->deleteNotificationsFor($procurementRequest);
                $procurementRequest->delete();
            }

            foreach (Survey::where('lead_id', $lead->id)->with('report')->get() as $survey) {
                $this->deleteNotificationsFor($survey);
                $survey->attachments()->get()->each(
                    fn ($attachment) => Storage::disk('local')->delete($attachment->file_path)
                );
                $survey->attachments()->delete();

                if ($survey->report) {
                    $this->deleteNotificationsFor($survey->report);
                    $survey->report->attachments()->get()->each(
                        fn ($attachment) => Storage::disk('local')->delete($attachment->file_path)
                    );
                    $survey->report->attachments()->delete();
                }
            }

            // Requirement, Meeting, dan Survey (+ report, surveyors) ikut terhapus
            // otomatis lewat foreign key cascadeOnDelete begitu Lead ini dihapus.
            $lead->delete();
        });
    }

    private function deleteNotificationsFor(Model $related): void
    {
        Notification::where('related_type', $related->getMorphClass())
            ->where('related_id', $related->getKey())
            ->delete();
    }
}
