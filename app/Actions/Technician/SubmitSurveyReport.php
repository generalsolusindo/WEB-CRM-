<?php

namespace App\Actions\Technician;

use App\Enums\SurveyReportStatus;
use App\Enums\SurveyStatus;
use App\Models\Notification;
use App\Models\Survey;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitSurveyReport
{
    public function __construct(private Notify $notify) {}

    public function handle(Survey $survey, User $user): Survey
    {
        return DB::transaction(function () use ($survey, $user) {
            $locked = Survey::query()
                ->with(['lead.contact', 'report.items'])
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SurveyStatus::InProgress->value) {
                throw ValidationException::withMessages([
                    'survey' => 'Laporan hanya bisa dikirim saat survey berjalan.',
                ]);
            }

            $report = $locked->report;

            if (! $report || trim((string) $report->summary) === '') {
                throw ValidationException::withMessages([
                    'summary' => 'Isi ringkasan hasil survey sebelum mengirim.',
                ]);
            }

            if ($report->items->isEmpty() && $report->attachments()->count() === 0) {
                throw ValidationException::withMessages([
                    'report' => 'Lampirkan minimal satu foto/dokumen atau satu item rekomendasi.',
                ]);
            }

            $report->update([
                'status' => SurveyReportStatus::Submitted->value,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);

            $locked->update(['status' => SurveyStatus::ReportReview->value]);

            // Bersihkan notifikasi "perlu revisi" dari siklus sebelumnya.
            Notification::query()
                ->where('type', 'survey.rework')
                ->where('related_type', $locked->getMorphClass())
                ->where('related_id', $locked->id)
                ->delete();

            $customer = $locked->lead->contact?->name ?? 'customer';

            $this->notify->onceForEach(
                User::query()->where('role', 'operational')->where('is_active', true)->get(),
                'survey.report_review',
                "Laporan survey {$locked->code} ({$customer}) rev.{$report->revision} menunggu verifikasi.",
                $locked,
            );

            return $locked;
        });
    }
}
