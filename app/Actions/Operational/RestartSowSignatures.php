<?php

namespace App\Actions\Operational;

use App\Enums\SowStatus;
use App\Models\Notification;
use App\Models\Sow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestartSowSignatures
{
    public function handle(Sow $sow): Sow
    {
        return DB::transaction(function () use ($sow) {
            $locked = Sow::query()->with('project.salesOrder.contact', 'technician')
                ->whereKey($sow->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SowStatus::RejectedSignature->value) {
                throw ValidationException::withMessages(['sow' => 'SOW ini tidak dalam status ditolak tanda tangannya.']);
            }

            $locked->update([
                'status' => SowStatus::PendingTechnicianSignature->value,
                'technician_signature' => null,
                'technician_signed_at' => null,
                'vendor_signature' => null,
                'vendor_signed_at' => null,
                'vendor_signed_by' => null,
                'hr_signature_reviewed_by' => null,
                'hr_signature_reviewed_at' => null,
                'hr_signature_review_notes' => null,
            ]);

            if ($locked->technician) {
                $customer = $locked->project->salesOrder?->contact?->name ?? 'customer';
                Notification::updateOrCreate(
                    [
                        'user_id' => $locked->technician_id,
                        'type' => 'sow.pending_technician_signature',
                        'related_type' => $locked->getMorphClass(),
                        'related_id' => $locked->id,
                    ],
                    ['message' => "SOW {$locked->number} ({$customer}) perlu ditanda tangani ulang.", 'is_sent' => true, 'read_at' => null],
                );
            }

            return $locked->refresh();
        });
    }
}
