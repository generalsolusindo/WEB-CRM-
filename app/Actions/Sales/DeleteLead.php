<?php

namespace App\Actions\Sales;

use App\Models\Lead;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\Survey;
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
            foreach (Quotation::where('lead_id', $lead->id)->get() as $quotation) {
                Notification::where('related_type', $quotation->getMorphClass())
                    ->where('related_id', $quotation->id)
                    ->delete();

                if ($salesOrder = $quotation->salesOrder) {
                    $salesOrder->attachments()->get()->each(
                        fn ($attachment) => Storage::disk('local')->delete($attachment->file_path)
                    );
                    $salesOrder->attachments()->delete();

                    Notification::where('related_type', $salesOrder->getMorphClass())
                        ->where('related_id', $salesOrder->id)
                        ->delete();

                    $salesOrder->delete();
                }

                $quotation->delete();
            }

            ProcurementRequest::where('lead_id', $lead->id)->get()->each->delete();

            foreach (Survey::where('lead_id', $lead->id)->with('report')->get() as $survey) {
                $survey->attachments()->get()->each(
                    fn ($attachment) => Storage::disk('local')->delete($attachment->file_path)
                );
                $survey->attachments()->delete();

                if ($survey->report) {
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
}
