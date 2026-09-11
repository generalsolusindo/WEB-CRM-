<?php

namespace App\Actions\Finance;

use App\Enums\ActualProcurementStatus;
use App\Enums\ProcurementPaymentStatus;
use App\Models\ProcurementPayment;
use App\Models\User;
use App\Services\Notifications\Notify;
use App\Services\Operational\ProjectMaterialProgress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordProcurementPayment
{
    public function __construct(private Notify $notify, private ProjectMaterialProgress $progress) {}

    /**
     * @param  array{item_ids: array<int, int>, proof?: UploadedFile|null, proof_scope?: string}  $data
     */
    public function handle(ProcurementPayment $payment, User $finance, array $data): ProcurementPayment
    {
        return DB::transaction(function () use ($payment, $finance, $data) {
            $locked = ProcurementPayment::query()
                ->with(['items', 'project'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ProcurementPaymentStatus::ApprovedPm) {
                throw ValidationException::withMessages([
                    'procurement_payment' => 'Pengajuan ini belum disetujui PM atau sudah dibayar.',
                ]);
            }

            // Hanya item yang dibeli & belum dibayar.
            $payableIds = $locked->items
                ->where('from_office_stock', false)
                ->where('is_paid', false)
                ->pluck('id')->all();
            $targetIds = array_values(array_intersect(
                array_map('intval', $data['item_ids'] ?? []),
                $payableIds,
            ));

            if ($targetIds === []) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Pilih minimal satu item yang belum dibayar.',
                ]);
            }

            $locked->items()
                ->whereIn('id', $targetIds)
                ->update([
                    'is_paid' => true,
                    'paid_at' => now(),
                    'status' => ActualProcurementStatus::Purchased->value,
                    'purchased_at' => now(),
                    'handled_by' => $finance->id,
                ]);

            $this->progress->sync($locked->project);

            /** @var UploadedFile|null $proof */
            $proof = $data['proof'] ?? null;
            if (! $proof instanceof UploadedFile) {
                throw ValidationException::withMessages([
                    'proof' => 'Bukti transfer wajib dilampirkan.',
                ]);
            }

            $path = $proof->store('procurement-payment-proofs');
            $scope = ($data['proof_scope'] ?? 'all') === 'items' ? 'items' : 'all';

            if ($scope === 'all') {
                $locked->proofs()->create([
                    'actual_procurement_id' => null,
                    'file_path' => $path,
                    'uploaded_by' => $finance->id,
                    'uploaded_at' => now(),
                ]);
            } else {
                foreach ($targetIds as $id) {
                    $locked->proofs()->create([
                        'actual_procurement_id' => $id,
                        'file_path' => $path,
                        'uploaded_by' => $finance->id,
                        'uploaded_at' => now(),
                    ]);
                }
            }

            $allPaid = $locked->items()
                ->where('from_office_stock', false)
                ->where('is_paid', false)
                ->doesntExist();

            if ($allPaid) {
                $locked->update([
                    'status' => ProcurementPaymentStatus::Paid->value,
                    'finance_paid_by' => $finance->id,
                    'finance_paid_at' => now(),
                ]);

                $projectNo = 'PRJ-'.str_pad((string) $locked->project_id, 6, '0', STR_PAD_LEFT);
                if ($locked->submitted_by) {
                    $this->notify->once(
                        $locked->submitted_by,
                        'procurement_payment.paid',
                        "Semua kebutuhan barang pengajuan {$locked->number} ({$projectNo}) sudah dibayar Finance. Silakan konfirmasi.",
                        $locked,
                    );
                }
            }

            return $locked->fresh();
        });
    }
}
