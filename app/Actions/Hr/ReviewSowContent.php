<?php

namespace App\Actions\Hr;

use App\Enums\SowStatus;
use App\Models\Notification;
use App\Models\Sow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewSowContent
{
    public function handle(Sow $sow, User $reviewer, bool $approved, ?string $notes): Sow
    {
        return DB::transaction(function () use ($sow, $reviewer, $approved, $notes) {
            $locked = Sow::query()->with(['project.salesOrder.contact', 'technician', 'creator'])
                ->whereKey($sow->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SowStatus::PendingHrReview->value) {
                throw ValidationException::withMessages(['sow' => 'SOW ini sudah tidak menunggu review HR.']);
            }

            $locked->update([
                'status' => $approved ? SowStatus::PendingTechnicianSignature->value : SowStatus::RejectedByHr->value,
                'hr_content_reviewed_by' => $reviewer->id,
                'hr_content_reviewed_at' => now(),
                'hr_content_review_notes' => $notes,
            ]);

            $customer = $locked->project->salesOrder?->contact?->name ?? 'customer';

            if ($approved) {
                if ($locked->technician) {
                    Notification::updateOrCreate(
                        [
                            'user_id' => $locked->technician_id,
                            'type' => 'sow.pending_technician_signature',
                            'related_type' => $locked->getMorphClass(),
                            'related_id' => $locked->id,
                        ],
                        [
                            'message' => "SOW {$locked->number} ({$customer}) perlu Anda tanda tangani.",
                            'is_sent' => true,
                            'read_at' => null,
                        ],
                    );
                }
            } elseif ($locked->created_by) {
                Notification::updateOrCreate(
                    [
                        'user_id' => $locked->created_by,
                        'type' => 'sow.rejected_by_hr',
                        'related_type' => $locked->getMorphClass(),
                        'related_id' => $locked->id,
                    ],
                    [
                        'message' => "SOW {$locked->number} ({$customer}) dikembalikan HR: {$notes}",
                        'is_sent' => true,
                        'read_at' => null,
                    ],
                );
            }

            return $locked->refresh();
        });
    }
}
