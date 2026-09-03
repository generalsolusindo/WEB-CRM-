<?php

namespace App\Actions\Operational;

use App\Models\Survey;
use App\Services\Notifications\Notify;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SyncSurveyTeam
{
    public function __construct(private Notify $notify) {}

    /**
     * Menyelaraskan komposisi tim surveyor + menandai leader, lalu memberi tahu
     * anggota yang baru ditugaskan. Dipanggil saat briefing dan saat Operasional
     * mengubah tim selama survey berjalan.
     *
     * @param  list<int>  $surveyorIds
     */
    public function handle(Survey $survey, array $surveyorIds, int $leaderId, bool $notify = true): void
    {
        if (! in_array($leaderId, $surveyorIds, true)) {
            throw ValidationException::withMessages([
                'leader_id' => 'Leader harus salah satu anggota tim yang dipilih.',
            ]);
        }

        $before = $survey->surveyors()->pluck('users.id')->all();

        $survey->surveyors()->sync(
            (new Collection($surveyorIds))
                ->mapWithKeys(fn ($id) => [$id => ['is_leader' => $id === $leaderId]])
                ->all()
        );

        $survey->load('surveyors');

        if (! $notify) {
            return;
        }

        foreach ($survey->surveyors as $member) {
            if (in_array($member->id, $before, true)) {
                continue;
            }
            $this->notify->once(
                $member,
                'survey.assigned',
                "Kamu ditugaskan survey {$survey->code} di {$survey->site_region}. Cek arahan lalu kirim laporan.",
                $survey,
            );
        }
    }
}
