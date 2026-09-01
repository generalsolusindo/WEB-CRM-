<?php

namespace App\Actions\Operational;

use App\Enums\SurveyReportStatus;
use App\Enums\SurveyStatus;
use App\Models\Notification;
use App\Models\Survey;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifySurveyReport
{
    public function __construct(private Notify $notify) {}

    public function handle(Survey $survey, User $user, bool $approved, ?string $notes = null): Survey
    {
        return DB::transaction(function () use ($survey, $user, $approved, $notes) {
            $locked = Survey::query()
                ->with(['lead.contact', 'lead.sales', 'report', 'surveyor'])
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SurveyStatus::ReportReview->value) {
                throw ValidationException::withMessages([
                    'survey' => 'Tidak ada laporan yang menunggu verifikasi.',
                ]);
            }

            $report = $locked->report;

            if (! $approved && trim((string) $notes) === '') {
                throw ValidationException::withMessages([
                    'notes' => 'Isi alasan penolakan agar surveyor tahu yang harus diperbaiki.',
                ]);
            }

            // Notifikasi "menunggu verifikasi" sudah ditindaklanjuti.
            Notification::query()
                ->where('type', 'survey.report_review')
                ->where('related_type', $locked->getMorphClass())
                ->where('related_id', $locked->id)
                ->delete();

            $customer = $locked->lead->contact?->name ?? 'customer';

            if ($approved) {
                $report->update([
                    'status' => SurveyReportStatus::Verified->value,
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                    'review_notes' => $notes,
                ]);
                $locked->update(['status' => SurveyStatus::Verified->value]);

                if ($locked->lead->sales) {
                    $this->notify->once(
                        $locked->lead->sales,
                        'survey.verified',
                        "Laporan survey {$locked->code} ({$customer}) sudah diverifikasi. Silakan lanjut ke requirement / quotation.",
                        $locked,
                    );
                }

                return $locked;
            }

            $report->update([
                'status' => SurveyReportStatus::Rejected->value,
                'review_notes' => $notes,
                'revision' => $report->revision + 1,
            ]);
            $locked->update(['status' => SurveyStatus::InProgress->value]);

            if ($locked->surveyor) {
                $this->notify->once(
                    $locked->surveyor,
                    'survey.rework',
                    "Laporan survey {$locked->code} perlu revisi: {$notes}",
                    $locked,
                );
            }

            return $locked;
        });
    }
}
