<?php

namespace App\Actions\Procurement;

use App\Enums\SowStatus;
use App\Enums\VendorServicePaymentStatus;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Models\VendorServicePayment;
use App\Services\DocumentNumber;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Procurement menyimpan deal jasa vendor luar untuk satu project. Sekaligus menandai
 * project dikerjakan lewat vendor tsb. Termin DP -> tugas bayar DP ke Finance;
 * bayar di akhir -> langsung dilepas ke Operasional (pelunasan menunggu BAST).
 */
class SaveVendorServicePayment
{
    public function __construct(private DocumentNumber $documentNumber, private Notify $notify) {}

    /**
     * @param  array{vendor_id: int, total_fee: float|string, terms: string, dp_amount?: float|string|null, bank_name: string, account_number: string, account_holder: string, notes?: string|null}  $data
     */
    public function handle(Project $project, User $procurement, array $data): VendorServicePayment
    {
        return DB::transaction(function () use ($project, $procurement, $data) {
            $locked = Project::query()
                ->with(['vendorServicePayment', 'sow.technician', 'salesOrder.contact'])
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $locked->vendorServicePayment;

            if ($locked->status === 'completed') {
                throw ValidationException::withMessages(['vendor_service' => 'Project sudah selesai.']);
            }

            if ($existing && ! $existing->isEditable()) {
                throw ValidationException::withMessages(['vendor_service' => 'Deal vendor yang sudah lunas tidak dapat diubah.']);
            }

            // Rantai tanda tangan SOW berjalan atas nama vendor & teknisi tertentu — vendor
            // tidak boleh diganti begitu SOW sudah dikirim ke HR atau lebih jauh.
            $sowInProgress = $locked->sow && ! in_array($locked->sow->status, [SowStatus::Draft->value, SowStatus::RejectedByHr->value], true);
            if ($sowInProgress && $locked->vendor_id !== null && $locked->vendor_id !== (int) $data['vendor_id']) {
                throw ValidationException::withMessages(['vendor_id' => 'Vendor tidak bisa diganti — SOW project ini sudah diproses (dikirim ke HR atau lebih jauh).']);
            }

            $hasDp = $data['terms'] === VendorServicePayment::TERMS_DP_FINAL;
            $total = round((float) $data['total_fee'], 2);
            $dp = $hasDp ? round((float) ($data['dp_amount'] ?? 0), 2) : null;

            if ($hasDp && ($dp <= 0 || $dp >= $total)) {
                throw ValidationException::withMessages(['dp_amount' => 'Nominal DP harus lebih dari 0 dan kurang dari total fee.']);
            }

            $attributes = [
                'vendor_id' => $data['vendor_id'],
                'total_fee' => $total,
                'terms' => $data['terms'],
                'dp_amount' => $dp,
                'bank_name' => $data['bank_name'],
                'account_number' => $data['account_number'],
                'account_holder' => $data['account_holder'],
                'notes' => $data['notes'] ?? null,
                'status' => ($hasDp ? VendorServicePaymentStatus::AwaitingDp : VendorServicePaymentStatus::InProgress)->value,
                'released_at' => $hasDp ? null : ($existing?->released_at ?? now()),
                'submitted_by' => $procurement->id,
                'submitted_at' => now(),
            ];

            $payment = $existing ?? new VendorServicePayment([
                'project_id' => $locked->id,
                'number' => $this->documentNumber->nextVendorServicePaymentNumber(),
            ]);
            $payment->fill($attributes)->save();

            $locked->update(['vendor_id' => $data['vendor_id']]);

            // Teknisi di draft SOW yang berasal dari vendor lain tidak valid lagi.
            $sow = $locked->sow;
            if ($sow && $sow->technician_id && $sow->technician?->vendor_id !== (int) $data['vendor_id']) {
                $sow->update(['technician_id' => null]);
            }

            $this->notify->resolve('vendor_service.needed', $locked);
            $this->notifyStakeholders($locked, $payment, $hasDp);

            return $payment->refresh();
        });
    }

    private function notifyStakeholders(Project $project, VendorServicePayment $payment, bool $hasDp): void
    {
        $customer = $project->salesOrder?->contact?->name ?? 'customer';
        $projectNo = 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT);
        $vendor = $payment->vendor()->value('name');

        foreach (['vendor_service.dp_due', 'vendor_service.pay_after_bast', 'vendor_service.released'] as $type) {
            $this->notify->resolve($type, $project);
        }

        $finance = User::query()->where('role', 'finance')->where('is_active', true)->get();

        if ($hasDp) {
            $this->upsert($finance, 'vendor_service.dp_due', $project,
                "DP vendor {$vendor} untuk {$projectNo} ({$customer}) menunggu dibayar.");

            return;
        }

        $this->upsert($finance, 'vendor_service.pay_after_bast', $project,
            "Vendor {$vendor} untuk {$projectNo} ({$customer}) dibayar lunas setelah BAST diverifikasi Operasional.");

        $operational = User::query()->where('role', 'operational')->where('is_active', true)->get();
        $this->upsert($operational, 'vendor_service.released', $project,
            "Vendor {$vendor} sudah ditetapkan untuk {$projectNo} ({$customer}) — project bisa dilanjutkan.");
    }

    /** @param  iterable<User>  $users */
    private function upsert(iterable $users, string $type, Project $project, string $message): void
    {
        foreach ($users as $user) {
            Notification::updateOrCreate(
                ['user_id' => $user->id, 'type' => $type, 'related_type' => $project->getMorphClass(), 'related_id' => $project->id],
                ['message' => $message, 'is_sent' => true, 'read_at' => null],
            );
        }
    }
}
