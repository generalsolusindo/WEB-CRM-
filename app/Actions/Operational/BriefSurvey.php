<?php

namespace App\Actions\Operational;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BriefSurvey
{
    public function __construct(private Notify $notify) {}

    public function handle(Survey $survey, User $user, string $briefing): Survey
    {
        return DB::transaction(function () use ($survey, $user, $briefing) {
            $locked = Survey::query()
                ->with('lead.contact', 'surveyor')
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SurveyStatus::AwaitingBriefing->value) {
                throw ValidationException::withMessages([
                    'survey' => 'Survey ini belum siap diberi arahan.',
                ]);
            }

            $locked->update([
                'briefing' => $briefing,
                'briefed_by' => $user->id,
                'briefed_at' => now(),
                'status' => SurveyStatus::InProgress->value,
            ]);

            $locked->report()->firstOrCreate(
                ['survey_id' => $locked->id],
                ['status' => 'draft', 'revision' => 1],
            );

            if ($locked->surveyor) {
                $this->notify->once(
                    $locked->surveyor,
                    'survey.assigned',
                    "Kamu ditugaskan survey {$locked->code} di {$locked->site_region}. Cek arahan lalu kirim laporan.",
                    $locked,
                );
            }

            return $locked;
        });
    }
}
