<?php

namespace App\Actions\Operational;

use App\Models\Project;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kebalikan dari CancelOutsideVendorNeed: Operasional menandai project yang tadinya
 * disangka cukup tim sendiri ternyata butuh vendor luar. Project seperti ini tidak
 * pernah muncul di daftar "Pengadaan Project" milik Procurement (lihat
 * ProjectProcurementController::index()) selama belum ditandai dan belum punya
 * kebutuhan barang — jadi tanpa aksi ini Procurement tidak akan pernah tahu project ini
 * ada, dan project tidak akan pernah punya vendor (`vendor_id`) sehingga SOW
 * (ProjectPolicy::manageSow()) tidak akan pernah bisa dibuat.
 */
class FlagOutsideVendorNeed
{
    public function __construct(private Notify $notify) {}

    public function handle(Project $project, User $operational): Project
    {
        return DB::transaction(function () use ($project) {
            $locked = Project::query()
                ->with('salesOrder.contact')
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->needs_outside_vendor) {
                throw ValidationException::withMessages([
                    'needs_outside_vendor' => 'Project ini sudah ditandai butuh vendor luar.',
                ]);
            }

            if ($locked->vendor_id !== null) {
                throw ValidationException::withMessages([
                    'needs_outside_vendor' => 'Project ini sudah punya vendor terpasang.',
                ]);
            }

            $locked->update(['needs_outside_vendor' => true]);

            $customer = $locked->salesOrder?->contact?->name ?? 'customer';
            $projectNo = 'PRJ-'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT);

            $this->notify->onceForEach(
                User::query()->where('role', 'procurement')->where('is_active', true)->get(),
                'vendor_service.needed',
                "Project untuk {$projectNo} ({$customer}) ditandai Operasional butuh vendor luar — carikan vendor & isi deal-nya.",
                $locked,
            );

            return $locked->fresh();
        });
    }
}
