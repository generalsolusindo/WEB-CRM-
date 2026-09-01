<?php

namespace App\Actions\Operational;

use App\Enums\ProjectStatus;
use App\Models\Bast;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyBast
{
    public function approve(Bast $bast, User $verifier): Bast
    {
        return $this->run($bast, $verifier, approved: true, notes: null);
    }

    public function reject(Bast $bast, User $verifier, string $notes): Bast
    {
        return $this->run($bast, $verifier, approved: false, notes: $notes);
    }

    private function run(Bast $bast, User $verifier, bool $approved, ?string $notes): Bast
    {
        return DB::transaction(function () use ($bast, $verifier, $approved, $notes) {
            $locked = Bast::query()
                ->with('project')
                ->whereKey($bast->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'bast' => 'BAST ini sudah diverifikasi.',
                ]);
            }

            $project = $locked->project;

            if ($project->status !== ProjectStatus::Verification->value) {
                throw ValidationException::withMessages([
                    'bast' => 'Project tidak sedang dalam tahap verifikasi.',
                ]);
            }

            $locked->update([
                'status' => $approved ? 'verified' : 'rejected',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'notes' => $notes ?? $locked->notes,
            ]);

            $project->update([
                'status' => $approved
                    ? ProjectStatus::Completed->value
                    : ProjectStatus::InProgress->value,
            ]);

            return $locked;
        });
    }
}
