<?php

namespace App\Actions\Operational;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignProjectTechnicians
{
    /**
     * @param  array<int, int>  $technicianIds
     */
    public function handle(Project $project, User $assignedBy, array $technicianIds, int $leaderId): void
    {
        DB::transaction(function () use ($project, $assignedBy, $technicianIds, $leaderId) {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $ids = collect($technicianIds)->map(fn ($id) => (int) $id)->unique()->values();

            if ($ids->isEmpty()) {
                throw ValidationException::withMessages([
                    'technician_ids' => 'Pilih minimal satu technician.',
                ]);
            }

            if (! $ids->contains($leaderId)) {
                throw ValidationException::withMessages([
                    'leader_id' => 'Leader harus salah satu technician yang dipilih.',
                ]);
            }

            $validCount = User::query()
                ->whereIn('id', $ids)
                ->where('role', 'technician')
                ->where('is_active', true)
                ->count();

            if ($validCount !== $ids->count()) {
                throw ValidationException::withMessages([
                    'technician_ids' => 'Ada user yang bukan technician aktif.',
                ]);
            }

            $locked->technicians()->delete();

            foreach ($ids as $id) {
                $locked->technicians()->create([
                    'technician_id' => $id,
                    'is_leader' => $id === $leaderId,
                    'assigned_by' => $assignedBy->id,
                ]);
            }
        });
    }
}
