<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementPaymentStatus;
use App\Models\ActualProcurement;
use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitProcurementPayment
{
    public function __construct(private DocumentNumber $documentNumber, private Notify $notify) {}

    /**
     * @param  array{pricing_mode: string, lump_sum_vendor_id?: int|null, lump_sum_amount?: float|null}  $data
     */
    public function handle(Project $project, User $procurement, array $data): ProcurementPayment
    {
        return DB::transaction(function () use ($project, $procurement, $data) {
            $locked = Project::query()
                ->with(['procurementPayment'])
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $locked->procurementPayment;

            // Ada pengajuan yang sedang diproses (belum tuntas / belum ditolak)?
            if ($existing && ! $existing->status->isEditable()
                && $existing->status !== ProcurementPaymentStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'procurement_payment' => 'Pengajuan pembayaran sedang diproses dan belum bisa diajukan ulang.',
                ]);
            }

            $reuse = $existing && $existing->status->isEditable();

            // Item yang diikutkan: belum terhubung ke pengajuan mana pun, atau milik
            // pengajuan draft/ditolak yang mau kita pakai ulang.
            $items = $locked->actualProcurements()
                ->when(
                    $reuse,
                    fn ($q) => $q->where(fn ($w) => $w->whereNull('procurement_payment_id')->orWhere('procurement_payment_id', $existing->id)),
                    fn ($q) => $q->whereNull('procurement_payment_id'),
                )
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'procurement_payment' => 'Tidak ada kebutuhan barang baru untuk diajukan.',
                ]);
            }

            $mode = ($data['pricing_mode'] ?? 'itemized') === 'lump_sum' ? 'lump_sum' : 'itemized';
            $purchasable = $items->where('from_office_stock', false);

            foreach ($items as $item) {
                if ($item->from_office_stock) {
                    continue;
                }
                if ($mode === 'itemized' && ((float) $item->cost_price <= 0 || $item->vendor_id === null)) {
                    throw ValidationException::withMessages([
                        'lines' => "Item \"{$item->item_name}\" belum lengkap: pilih vendor dan isi harga, atau tandai ada stok kantor.",
                    ]);
                }
            }

            if ($purchasable->isNotEmpty() && $mode === 'lump_sum') {
                $vendorId = $data['lump_sum_vendor_id'] ?? null;
                $amount = (float) ($data['lump_sum_amount'] ?? 0);
                if (! $vendorId || $amount <= 0) {
                    throw ValidationException::withMessages([
                        'procurement_payment' => 'Mode borongan wajib memilih vendor dan mengisi total harga.',
                    ]);
                }
            }

            if ($purchasable->isEmpty()) {
                $mode = 'itemized';
            }

            $payment = $reuse ? $existing : new ProcurementPayment([
                'project_id' => $locked->id,
                'number' => $this->documentNumber->nextProcurementPaymentNumber(),
            ]);

            $payment->fill([
                'pricing_mode' => $mode,
                'lump_sum_vendor_id' => $mode === 'lump_sum' ? ($data['lump_sum_vendor_id'] ?? null) : null,
                'lump_sum_amount' => $mode === 'lump_sum' ? ($data['lump_sum_amount'] ?? null) : null,
                'bank_account_note' => $mode === 'lump_sum' ? ($data['bank_account_note'] ?? null) : null,
                'status' => ProcurementPaymentStatus::PendingPm->value,
                'submitted_by' => $procurement->id,
                'submitted_at' => now(),
                'pm_reviewed_by' => null,
                'pm_reviewed_at' => null,
                'pm_notes' => null,
                'finance_paid_by' => null,
                'finance_paid_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
            ])->save();

            ActualProcurement::query()
                ->whereIn('id', $items->pluck('id'))
                ->update(['procurement_payment_id' => $payment->id]);

            if ($locked->delegated_to) {
                $this->notify->once(
                    $locked->delegated_to,
                    'procurement_payment.pending_pm',
                    "Pengajuan pembayaran pengadaan {$payment->number} (PRJ-".str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT).") menunggu persetujuan Anda.",
                    $payment,
                );
            }

            return $payment->fresh();
        });
    }
}
