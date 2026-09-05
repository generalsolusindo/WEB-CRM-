<?php

namespace App\Actions\Sales;

use App\Models\Notification;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi quotation 2 tahap: Project Manager terlebih dahulu, baru Manager.
 * Quotation hanya bisa dikirim ke customer setelah keduanya menyetujui.
 */
class ReviewQuotation
{
    public function handle(Quotation $quotation, User $reviewer, string $reviewerRole, bool $approved, ?string $notes): Quotation
    {
        return DB::transaction(function () use ($quotation, $reviewer, $reviewerRole, $approved, $notes) {
            $locked = Quotation::query()
                ->with(['lead.delegatedTo', 'sales'])
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['quotation' => 'Quotation ini sudah tidak berstatus Draft.']);
            }

            if ($reviewerRole === 'pm') {
                if ($locked->lead?->delegated_to !== $reviewer->id) {
                    abort(403);
                }
                if ($locked->pm_review_status !== null) {
                    throw ValidationException::withMessages(['quotation' => 'Quotation ini sudah diverifikasi Project Manager.']);
                }

                $locked->update([
                    'pm_review_status' => $approved ? 'approved' : 'rejected',
                    'pm_reviewed_by' => $reviewer->id,
                    'pm_reviewed_at' => now(),
                    'pm_review_notes' => $notes,
                ]);
            } else {
                if ($locked->pm_review_status !== 'approved') {
                    throw ValidationException::withMessages(['quotation' => 'Menunggu verifikasi Project Manager terlebih dahulu.']);
                }
                if ($locked->manager_review_status !== null) {
                    throw ValidationException::withMessages(['quotation' => 'Quotation ini sudah diverifikasi Manager.']);
                }

                $locked->update([
                    'manager_review_status' => $approved ? 'approved' : 'rejected',
                    'manager_reviewed_by' => $reviewer->id,
                    'manager_reviewed_at' => now(),
                    'manager_review_notes' => $notes,
                ]);

                Notification::query()
                    ->where('type', 'quotation.pending_manager_review')
                    ->where('related_type', $locked->getMorphClass())
                    ->where('related_id', $locked->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }

            $this->notifyOutcome($locked, $reviewerRole, $approved, $notes);

            return $locked->refresh();
        });
    }

    private function notifyOutcome(Quotation $quotation, string $reviewerRole, bool $approved, ?string $notes): void
    {
        if (! $approved) {
            $by = $reviewerRole === 'pm' ? 'Project Manager' : 'Manager';
            if ($quotation->sales_id) {
                $this->upsertNotification(
                    $quotation->sales_id,
                    'quotation.review_rejected',
                    $quotation,
                    "Quotation {$quotation->number} ditolak oleh {$by}: {$notes}",
                );
            }

            return;
        }

        if ($reviewerRole === 'pm') {
            foreach (User::query()->where('role', 'management')->where('is_active', true)->get() as $manager) {
                $this->upsertNotification(
                    $manager->id,
                    'quotation.pending_manager_review',
                    $quotation,
                    "Quotation {$quotation->number} sudah diverifikasi Project Manager, menunggu verifikasi Anda.",
                );
            }

            return;
        }

        if ($quotation->sales_id) {
            $this->upsertNotification(
                $quotation->sales_id,
                'quotation.fully_approved',
                $quotation,
                "Quotation {$quotation->number} sudah disetujui Project Manager & Manager — siap dikirim ke customer.",
            );
        }
    }

    private function upsertNotification(int $userId, string $type, Quotation $quotation, string $message): void
    {
        Notification::updateOrCreate(
            [
                'user_id' => $userId,
                'type' => $type,
                'related_type' => $quotation->getMorphClass(),
                'related_id' => $quotation->id,
            ],
            ['message' => $message, 'is_sent' => true, 'read_at' => null],
        );
    }
}
