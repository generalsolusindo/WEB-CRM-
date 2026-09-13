<?php

namespace App\Actions\Operational;

use App\Enums\SowStatus;
use App\Models\Notification;
use App\Models\Sow;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitSowForReview
{
    public function __construct(private Notify $notify) {}

    public function handle(Sow $sow): Sow
    {
        return DB::transaction(function () use ($sow) {
            $locked = Sow::query()->with(['project.salesOrder.contact', 'technician'])
                ->whereKey($sow->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [SowStatus::Draft->value, SowStatus::RejectedByHr->value], true)) {
                throw ValidationException::withMessages(['sow' => 'SOW ini sudah tidak bisa dikirim ulang.']);
            }

            if (! $locked->number || ! $locked->project_name || ! $locked->technician_id) {
                throw ValidationException::withMessages([
                    'sow' => 'Nomor SOW, Nama Proyek, dan Teknisi pelaksana wajib diisi sebelum dikirim ke HR.',
                ]);
            }

            if ($locked->technician?->vendor_id !== $locked->project->vendor_id) {
                throw ValidationException::withMessages([
                    'sow' => 'Teknisi yang dipilih sudah tidak sesuai dengan vendor project ini — pilih ulang teknisi.',
                ]);
            }

            $locked->update([
                'status' => SowStatus::PendingHrReview->value,
                'submitted_at' => now(),
            ]);

            $this->notify->resolve('sow.rejected_by_hr', $locked);

            $customer = $locked->project->salesOrder?->contact?->name ?? 'customer';

            foreach (User::query()->where('role', 'hr')->where('is_active', true)->get() as $hr) {
                Notification::updateOrCreate(
                    [
                        'user_id' => $hr->id,
                        'type' => 'sow.pending_hr_review',
                        'related_type' => $locked->getMorphClass(),
                        'related_id' => $locked->id,
                    ],
                    [
                        'message' => "SOW {$locked->number} ({$customer}) perlu direview.",
                        'is_sent' => true,
                        'read_at' => null,
                    ],
                );
            }

            return $locked->refresh();
        });
    }
}
