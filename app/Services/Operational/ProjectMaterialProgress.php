<?php

namespace App\Services\Operational;

use App\Enums\ActualProcurementStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifications\Notify;

/**
 * Sinkronkan status project & notifikasi Operasional setiap kali progres
 * pengadaan barang berubah (dibayar / diterima / dari stok kantor).
 */
class ProjectMaterialProgress
{
    public function __construct(private Notify $notify) {}

    public function sync(Project $project): void
    {
        $items = $project->actualProcurements()->get();

        if ($items->isEmpty()) {
            return;
        }

        $anyStarted = $items->contains(fn ($i) => $i->status !== ActualProcurementStatus::Pending->value);

        if ($anyStarted && $project->status === ProjectStatus::Planning->value) {
            $project->update(['status' => ProjectStatus::WaitingResource->value]);
        }

        $noneLeftPending = $items->doesntContain(fn ($i) => $i->status === ActualProcurementStatus::Pending->value);

        if ($noneLeftPending) {
            $this->notify->resolve('project_procurement.requested', $project);
        }

        $allReceived = $items->every(fn ($i) => $i->status === ActualProcurementStatus::Received->value);

        if ($allReceived) {
            $project->loadMissing('salesOrder.contact');
            $customer = $project->salesOrder->contact?->name ?? 'customer';

            $this->notify->onceForEach(
                User::query()->where('role', 'operational')->where('is_active', true)->get(),
                'project_procurement.ready',
                'Semua barang project PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT)
                    ." ({$customer}) sudah diterima. Project bisa dilanjutkan.",
                $project,
            );
        }
    }
}
