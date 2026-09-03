<?php

namespace App\Actions\Operational;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BriefSurvey
{
    public function __construct(private SyncSurveyTeam $syncTeam) {}

    /**
     * @param  list<int>  $surveyorIds
     */
    public function handle(Survey $survey, User $user, string $briefing, array $surveyorIds, int $leaderId): Survey
    {
        return DB::transaction(function () use ($survey, $user, $briefing, $surveyorIds, $leaderId) {
            $locked = Survey::query()
                ->with('lead.contact', 'surveyors')
                ->whereKey($survey->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== SurveyStatus::AwaitingBriefing->value) {
                throw ValidationException::withMessages([
                    'survey' => 'Survey ini belum siap diberi arahan.',
                ]);
            }

            $this->syncTeam->handle($locked, $surveyorIds, $leaderId);

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

            return $locked;
        });
    }
}
