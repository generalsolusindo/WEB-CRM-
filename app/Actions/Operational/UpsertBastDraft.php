<?php

namespace App\Actions\Operational;

use App\Models\BastDraft;
use App\Models\Project;
use App\Models\User;

class UpsertBastDraft
{
    /** @param array<string, mixed> $data */
    public function handle(Project $project, User $user, array $data): BastDraft
    {
        $draft = $project->bastDraft;

        return BastDraft::updateOrCreate(
            ['project_id' => $project->id],
            [
                ...$data,
                'created_by' => $draft?->created_by ?? $user->id,
                'updated_by' => $user->id,
            ],
        );
    }
}
