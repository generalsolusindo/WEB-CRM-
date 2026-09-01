<?php

namespace App\Actions\Operational;

use App\Enums\ActualProcurementStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkProjectReady
{
    public function handle(Project $project): Project
    {
        return DB::transaction(function () use ($project) {
            $locked = Project::query()
                ->with(['actualProcurements', 'technicians', 'tasks'])
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [
                ProjectStatus::Planning->value,
                ProjectStatus::WaitingResource->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'project' => 'Status project tidak dapat dipindah ke Siap.',
                ]);
            }

            $pendingItems = $locked->actualProcurements
                ->contains(fn ($item) => $item->status !== ActualProcurementStatus::Received->value);

            if ($pendingItems) {
                throw ValidationException::withMessages([
                    'project' => 'Masih ada item pengadaan yang belum diterima.',
                ]);
            }

            if ($locked->tasks->isEmpty()) {
                throw ValidationException::withMessages([
                    'project' => 'Buat minimal satu task sebelum project siap.',
                ]);
            }

            $leaderCount = $locked->technicians->where('is_leader', true)->count();

            if ($locked->technicians->isEmpty() || $leaderCount !== 1) {
                throw ValidationException::withMessages([
                    'project' => 'Tim technician harus terisi dengan tepat satu leader.',
                ]);
            }

            $locked->update(['status' => ProjectStatus::Ready->value]);

            return $locked;
        });
    }
}
