<?php

namespace App\Actions\Procurement;

use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Procurement membatalkan tanda "butuh vendor luar" yang disetel Sales sejak Lead —
 * dipakai kalau ternyata project ini bisa dikerjakan tim teknisi sendiri. Tanda ini
 * tidak pernah bisa diubah lewat jalur lain: Lead terkunci total begitu Procurement
 * Request pernah dibuat, dan Project cuma menyalinnya sekali saat dibuat, jadi tanpa
 * aksi ini Operasional selamanya tertahan menunggu deal vendor yang sebenarnya tidak
 * pernah akan datang (lihat ProjectPolicy::vendorReleased()).
 *
 * Sengaja HANYA boleh selama belum ada deal vendor tersimpan sama sekali — begitu
 * deal sudah ada (walau baru draft/DP), pembatalannya harus lewat pembongkaran deal
 * itu sendiri (SaveVendorServicePayment/CancelVendorServicePayment), bukan di sini.
 */
class CancelOutsideVendorNeed
{
    public function __construct(private Notify $notify) {}

    public function handle(Project $project, User $procurement): Project
    {
        return DB::transaction(function () use ($project) {
            $locked = Project::query()
                ->with(['vendorServicePayment', 'salesOrder.contact', 'salesOrder.quotation'])
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->needs_outside_vendor) {
                throw ValidationException::withMessages([
                    'needs_outside_vendor' => 'Project ini memang tidak ditandai butuh vendor luar.',
                ]);
            }

            if ($locked->vendorServicePayment !== null) {
                throw ValidationException::withMessages([
                    'needs_outside_vendor' => 'Deal vendor sudah tersimpan untuk project ini — batalkan deal-nya dulu, bukan lewat sini.',
                ]);
            }

            $locked->update(['needs_outside_vendor' => false]);

            // Notifikasi "cari vendor" yang dulu dikirim ke Procurement saat project ini
            // dibuat jadi tidak relevan lagi.
            $this->notify->resolve('vendor_service.needed', $locked);

            $this->notifyStakeholders($locked);

            return $locked->fresh();
        });
    }

    private function notifyStakeholders(Project $project): void
    {
        $customer = $project->salesOrder?->contact?->name ?? 'customer';
        $projectNo = 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT);
        $message = "Project {$projectNo} ({$customer}) ternyata TIDAK jadi butuh vendor luar — dibatalkan Procurement, bisa langsung dikerjakan tim teknisi sendiri.";

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($project) {
                $query->where('role', 'operational');

                $salesId = $project->salesOrder?->quotation?->sales_id;
                if ($salesId) {
                    $query->orWhere('id', $salesId);
                }
            })
            ->get();

        foreach ($recipients as $user) {
            Notification::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'vendor_service.no_longer_needed',
                    'related_type' => $project->getMorphClass(),
                    'related_id' => $project->id,
                ],
                ['message' => $message, 'is_sent' => true, 'read_at' => null],
            );
        }
    }
}
