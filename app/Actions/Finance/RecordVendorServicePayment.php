<?php

namespace App\Actions\Finance;

use App\Enums\VendorServicePaymentStatus;
use App\Models\Notification;
use App\Models\User;
use App\Models\VendorServicePayment;
use App\Models\VendorServicePaymentEntry;
use App\Services\Notifications\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finance mencatat transfer ke vendor jasa. Nominal TIDAK diinput Finance — selalu
 * mengikuti deal dari Procurement (DP, atau sisa pelunasan). Pelunasan hanya bisa setelah
 * BAST project diverifikasi Operasional.
 */
class RecordVendorServicePayment
{
    public function __construct(private Notify $notify) {}

    public function handle(VendorServicePayment $payment, User $finance, string $kind, string $paidAt, ?string $notes, UploadedFile $proof): VendorServicePaymentEntry
    {
        return DB::transaction(function () use ($payment, $finance, $kind, $paidAt, $notes, $proof) {
            $locked = VendorServicePayment::query()
                ->with(['project.salesOrder.contact', 'vendor'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($kind === VendorServicePaymentEntry::KIND_DP) {
                if (! $locked->canPayDp()) {
                    throw ValidationException::withMessages(['kind' => 'DP tidak bisa dibayar — sudah dibayar atau termin deal bukan DP.']);
                }
                $amount = (float) $locked->dp_amount;
            } elseif ($kind === VendorServicePaymentEntry::KIND_FINAL) {
                if (! $locked->bastVerified()) {
                    throw ValidationException::withMessages(['kind' => 'Pelunasan baru bisa dibayar setelah BAST diverifikasi Operasional.']);
                }
                if (! $locked->canPayFinal()) {
                    throw ValidationException::withMessages(['kind' => 'Pelunasan tidak bisa dibayar — DP belum dibayar atau pelunasan sudah tercatat.']);
                }
                $amount = $locked->finalAmount();
            } else {
                throw ValidationException::withMessages(['kind' => 'Jenis pembayaran tidak dikenal.']);
            }

            $entry = $locked->entries()->create([
                'kind' => $kind,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'paid_by' => $finance->id,
                'notes' => $notes,
            ]);

            $entry->attachments()->create([
                'category' => 'payment_proof',
                'file_path' => $proof->store('vendor-service-proofs'),
                'uploaded_by' => $finance->id,
            ]);

            $project = $locked->project;

            if ($kind === VendorServicePaymentEntry::KIND_DP) {
                $locked->update(['status' => VendorServicePaymentStatus::InProgress->value, 'released_at' => now()]);
                $this->notify->resolve('vendor_service.dp_due', $project);

                $customer = $project->salesOrder?->contact?->name ?? 'customer';
                $projectNo = 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT);
                foreach (User::query()->where('role', 'operational')->where('is_active', true)->get() as $operational) {
                    Notification::updateOrCreate(
                        ['user_id' => $operational->id, 'type' => 'vendor_service.released', 'related_type' => $project->getMorphClass(), 'related_id' => $project->id],
                        ['message' => "DP vendor {$locked->vendor?->name} untuk {$projectNo} ({$customer}) sudah dibayar — project bisa dilanjutkan.", 'is_sent' => true, 'read_at' => null],
                    );
                }
            } else {
                $locked->update(['status' => VendorServicePaymentStatus::Paid->value]);
                $this->notify->resolve('vendor_service.final_due', $project);
                $this->notify->resolve('vendor_service.pay_after_bast', $project);
            }

            return $entry;
        });
    }
}
