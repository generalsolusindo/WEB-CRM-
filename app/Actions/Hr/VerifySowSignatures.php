<?php

namespace App\Actions\Hr;

use App\Enums\SowStatus;
use App\Models\Notification;
use App\Models\Sow;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifySowSignatures
{
    public function __construct(private Notify $notify) {}

    public function handle(Sow $sow, User $reviewer, bool $approved, ?string $notes): Sow
    {
        return DB::transaction(function () use ($sow, $reviewer, $approved, $notes) {
            $locked = Sow::query()->with('project.salesOrder.contact')
                ->whereKey($sow->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SowStatus::PendingHrVerification->value) {
                throw ValidationException::withMessages(['sow' => 'SOW ini sudah tidak menunggu verifikasi tanda tangan.']);
            }

            $locked->update([
                'status' => $approved ? SowStatus::PendingAdminSignature->value : SowStatus::RejectedSignature->value,
                'hr_signature_reviewed_by' => $reviewer->id,
                'hr_signature_reviewed_at' => now(),
                'hr_signature_review_notes' => $notes,
            ]);

            $this->notify->resolve('sow.pending_hr_verification', $locked);

            $customer = $locked->project->salesOrder?->contact?->name ?? 'customer';

            if (! $locked->created_by) {
                return $locked->refresh();
            }

            $message = $approved
                ? "Tanda tangan SOW {$locked->number} ({$customer}) sudah diverifikasi HR, menunggu tanda tangan Admin Project."
                : "Tanda tangan SOW {$locked->number} ({$customer}) ditolak HR: {$notes}";

            Notification::updateOrCreate(
                [
                    'user_id' => $locked->created_by,
                    'type' => $approved ? 'sow.pending_admin_signature' : 'sow.rejected_signature',
                    'related_type' => $locked->getMorphClass(),
                    'related_id' => $locked->id,
                ],
                ['message' => $message, 'is_sent' => true, 'read_at' => null],
            );

            return $locked->refresh();
        });
    }
}
