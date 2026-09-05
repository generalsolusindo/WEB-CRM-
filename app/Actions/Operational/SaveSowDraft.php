<?php

namespace App\Actions\Operational;

use App\Enums\SowStatus;
use App\Models\Project;
use App\Models\Sow;
use App\Models\User;

class SaveSowDraft
{
    /** @param array<string, mixed> $data */
    public function handle(Project $project, User $user, array $data): Sow
    {
        $sow = $project->sow;

        return Sow::updateOrCreate(
            ['project_id' => $project->id],
            [
                ...$data,
                'status' => $sow?->status ?? SowStatus::Draft->value,
                'created_by' => $sow?->created_by ?? $user->id,
                'updated_by' => $user->id,
            ],
        );
    }
}
