<?php

namespace App\Actions\Finance;

use App\Enums\SowStatus;
use App\Enums\VendorServicePaymentStatus;
use App\Models\Notification;
use App\Models\User;
use App\Models\VendorServicePayment;
use App\Models\VendorServicePaymentEntry;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Koreksi salah input: transfer dibatalkan (bukan dihapus) — nominal & bukti tetap tersimpan untuk audit. */
class CancelVendorServicePayment
{
    public function __construct(private Notify $notify) {}

    public function handle(VendorServicePayment $payment, VendorServicePaymentEntry $entry, User $finance, string $reason): void
    {
        DB::transaction(function () use ($payment, $entry, $finance, $reason) {
            $locked = VendorServicePayment::query()
                ->with(['project.salesOrder.contact', 'project.sow', 'vendor'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $target = $locked->entries()->whereKey($entry->id)->lockForUpdate()->first();

            if (! $target) {
                throw ValidationException::withMessages(['reason' => 'Pembayaran ini sudah dibatalkan atau bukan milik deal ini.']);
            }

            $project = $locked->project;

            if ($target->kind === VendorServicePaymentEntry::KIND_DP) {
                if ($locked->activeEntry(VendorServicePaymentEntry::KIND_FINAL)) {
                    throw ValidationException::withMessages(['reason' => 'Batalkan pelunasan terlebih dahulu sebelum membatalkan DP.']);
                }
                if ($project->sow && $project->sow->status !== SowStatus::Draft->value && $project->sow->status !== SowStatus::RejectedByHr->value) {
                    throw ValidationException::withMessages(['reason' => 'SOW project ini sudah diproses — koreksi DP memerlukan peninjauan bersama Operasional.']);
                }
                if (in_array($project->status, ['in_progress', 'verification', 'completed'], true)) {
                    throw ValidationException::withMessages(['reason' => 'Project sudah berjalan — koreksi DP memerlukan peninjauan bersama Operasional.']);
                }
            }

            $target->fill(['cancelled_by' => $finance->id, 'cancellation_reason' => $reason])->saveQuietly();
            $target->delete();

            $customer = $project->salesOrder?->contact?->name ?? 'customer';
            $projectNo = 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT);

            if ($target->kind === VendorServicePaymentEntry::KIND_DP) {
                $locked->update(['status' => VendorServicePaymentStatus::AwaitingDp->value, 'released_at' => null]);
                $this->notify->resolve('vendor_service.released', $project);

                $message = "DP vendor {$locked->vendor?->name} untuk {$projectNo} ({$customer}) dibatalkan Finance — menunggu dibayar ulang.";
                foreach (User::query()->whereIn('role', ['finance', 'operational'])->where('is_active', true)->get() as $user) {
                    Notification::updateOrCreate(
                        ['user_id' => $user->id, 'type' => $user->role === 'finance' ? 'vendor_service.dp_due' : 'vendor_service.payment_cancelled', 'related_type' => $project->getMorphClass(), 'related_id' => $project->id],
                        ['message' => $message, 'is_sent' => true, 'read_at' => null],
                    );
                }
            } else {
                $locked->update(['status' => VendorServicePaymentStatus::InProgress->value]);

                foreach (User::query()->where('role', 'finance')->where('is_active', true)->get() as $user) {
                    Notification::updateOrCreate(
                        ['user_id' => $user->id, 'type' => 'vendor_service.final_due', 'related_type' => $project->getMorphClass(), 'related_id' => $project->id],
                        ['message' => "Pelunasan vendor {$locked->vendor?->name} untuk {$projectNo} ({$customer}) dibatalkan — siap dibayar ulang.", 'is_sent' => true, 'read_at' => null],
                    );
                }
            }
        }, 3);
    }
}
