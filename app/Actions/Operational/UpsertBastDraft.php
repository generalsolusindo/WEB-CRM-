<?php

namespace App\Actions\Operational;

use App\Models\BastDraft;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Support\Facades\DB;

class UpsertBastDraft
{
    public function __construct(private DocumentNumber $documentNumber) {}

    /** @param array<string, mixed> $data */
    public function handle(Project $project, User $user, array $data): BastDraft
    {
        return DB::transaction(function () use ($project, $user, $data) {
            $draft = $project->bastDraft;

            if ($draft === null && empty($data['number'])) {
                $data['number'] = $this->documentNumber->nextBastNumber();
            }

            return BastDraft::updateOrCreate(
                ['project_id' => $project->id],
                [
                    ...$data,
                    'created_by' => $draft?->created_by ?? $user->id,
                    'updated_by' => $user->id,
                ],
            );
        });
    }
}
