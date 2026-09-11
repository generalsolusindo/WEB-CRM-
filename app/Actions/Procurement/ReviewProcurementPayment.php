<?php

namespace App\Actions\Procurement;

use App\Enums\ActualProcurementStatus;
use App\Enums\ProcurementPaymentStatus;
use App\Models\ProcurementPayment;
use App\Models\User;
use App\Services\Notifications\Notify;
use App\Services\Operational\ProjectMaterialProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewProcurementPayment
{
    public function __construct(private Notify $notify, private ProjectMaterialProgress $progress) {}

    public function handle(ProcurementPayment $payment, User $pm, bool $approved, ?string $notes): ProcurementPayment
    {
        return DB::transaction(function () use ($payment, $pm, $approved, $notes) {
            $locked = ProcurementPayment::query()
                ->with(['project', 'items'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ProcurementPaymentStatus::PendingPm) {
                throw ValidationException::withMessages([
                    'procurement_payment' => 'Pengajuan ini tidak sedang menunggu persetujuan Anda.',
                ]);
            }

            $notes = trim((string) $notes) ?: null;

            if (! $approved && $notes === null) {
                throw ValidationException::withMessages([
                    'notes' => 'Alasan penolakan wajib diisi.',
                ]);
            }

            $projectNo = 'PRJ-'.str_pad((string) $locked->project_id, 6, '0', STR_PAD_LEFT);

            if (! $approved) {
                $locked->update([
                    'status' => ProcurementPaymentStatus::RejectedPm->value,
                    'pm_reviewed_by' => $pm->id,
                    'pm_reviewed_at' => now(),
                    'pm_notes' => $notes,
                ]);

                if ($locked->submitted_by) {
                    $this->notify->once(
                        $locked->submitted_by,
                        'procurement_payment.rejected_pm',
                        "Pengajuan pembayaran pengadaan {$locked->number} ({$projectNo}) ditolak PM: {$notes}",
                        $locked,
                    );
                }

                return $locked->fresh();
            }

            // Disetujui. Item stok kantor langsung tersedia — tidak lewat Finance.
            $locked->items()->where('from_office_stock', true)->update([
                'status' => ActualProcurementStatus::Received->value,
                'is_paid' => true,
                'received_at' => now(),
            ]);

            $needsFinance = $locked->items()->where('from_office_stock', false)->exists();

            $locked->update([
                'status' => $needsFinance
                    ? ProcurementPaymentStatus::ApprovedPm->value
                    : ProcurementPaymentStatus::Confirmed->value,
                'pm_reviewed_by' => $pm->id,
                'pm_reviewed_at' => now(),
                'pm_notes' => $notes,
                'confirmed_by' => $needsFinance ? null : $pm->id,
                'confirmed_at' => $needsFinance ? null : now(),
            ]);

            if ($needsFinance) {
                $this->notify->onceForEach(
                    User::query()->where('role', 'finance')->where('is_active', true)->get(),
                    'procurement_payment.approved_pm',
                    "Pengajuan pembayaran pengadaan {$locked->number} ({$projectNo}) sudah disetujui PM — siap dibayar.",
                    $locked,
                );
            } else {
                // Semua dari stok kantor — tidak ada yang perlu dibayar.
                $this->progress->sync($locked->project);
            }

            return $locked->fresh();
        });
    }
}
