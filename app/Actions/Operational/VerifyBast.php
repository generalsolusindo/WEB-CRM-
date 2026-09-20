<?php

namespace App\Actions\Operational;

use App\Enums\ProjectStatus;
use App\Models\Bast;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Models\VendorServicePayment;
use App\Services\Notifications\Notify;
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

            if ($approved) {
                $this->releaseVendorFinalPayment($project);
            }

            return $locked;
        });
    }

    /**
     * BAST terverifikasi = syarat pelunasan vendor jasa terpenuhi: penanda "dibayar setelah
     * BAST" di Finance diganti tugas bayar pelunasan yang nyata.
     */
    private function releaseVendorFinalPayment(Project $project): void
    {
        $deal = VendorServicePayment::query()->with('vendor')->where('project_id', $project->id)->first();

        if (! $deal || ! $deal->canPayFinal()) {
            return;
        }

        app(Notify::class)->resolve('vendor_service.pay_after_bast', $project);

        $projectNo = 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT);
        $amount = number_format($deal->finalAmount(), 0, ',', '.');

        foreach (User::query()->where('role', 'finance')->where('is_active', true)->get() as $finance) {
            Notification::updateOrCreate(
                ['user_id' => $finance->id, 'type' => 'vendor_service.final_due', 'related_type' => $project->getMorphClass(), 'related_id' => $project->id],
                ['message' => "BAST {$projectNo} sudah diverifikasi — pelunasan vendor {$deal->vendor?->name} (Rp {$amount}) siap dibayar.", 'is_sent' => true, 'read_at' => null],
            );
        }
    }
}
