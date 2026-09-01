<?php

namespace App\Actions\Procurement;

use App\Enums\ActualProcurementStatus;
use App\Enums\ProjectStatus;
use App\Models\ActualProcurement;
use App\Models\User;
use App\Models\VendorProduct;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;

class UpdateActualProcurement
{
    public function __construct(private Notify $notify) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ActualProcurement $item, User $procurement, array $data): ActualProcurement
    {
        return DB::transaction(function () use ($item, $procurement, $data) {
            $locked = ActualProcurement::query()
                ->with('project.salesOrder.contact')
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $project = $locked->project;
            $newStatus = $data['status'] ?? $locked->status;

            $vendorProductId = $data['vendor_product_id'] ?? null;

            $locked->update([
                'vendor_product_id' => $vendorProductId,
                'vendor_id' => $vendorProductId
                    ? VendorProduct::whereKey($vendorProductId)->value('vendor_id')
                    : $locked->vendor_id,
                'cost_price' => $data['cost_price'] ?? $locked->cost_price,
                'notes' => $data['notes'] ?? $locked->notes,
                'status' => $newStatus,
                'handled_by' => $procurement->id,
                'purchased_at' => $newStatus === ActualProcurementStatus::Pending->value
                    ? null
                    : ($locked->purchased_at ?? now()),
                'received_at' => $newStatus === ActualProcurementStatus::Received->value
                    ? ($locked->received_at ?? now())
                    : null,
            ]);

            $lockedProject = $project->fresh(['actualProcurements']);
            $items = $lockedProject->actualProcurements;

            $anyInProgress = $items->contains(
                fn ($i) => $i->status !== ActualProcurementStatus::Pending->value,
            );
            $allReceived = $items->isNotEmpty() && $items->every(
                fn ($i) => $i->status === ActualProcurementStatus::Received->value,
            );

            // Pindah otomatis ke "menunggu barang" begitu pembelian mulai berjalan.
            if ($anyInProgress && $lockedProject->status === ProjectStatus::Planning->value) {
                $lockedProject->update(['status' => ProjectStatus::WaitingResource->value]);
            }

            if ($allReceived) {
                $customer = $lockedProject->salesOrder->contact?->name ?? 'customer';
                $this->notify->onceForEach(
                    User::query()->where('role', 'operational')->where('is_active', true)->get(),
                    'project_procurement.ready',
                    "Semua barang project PRJ-".str_pad((string) $lockedProject->id, 6, '0', STR_PAD_LEFT)
                        ." ({$customer}) sudah diterima. Project bisa dilanjutkan.",
                    $lockedProject,
                );
            }

            return $locked->refresh();
        });
    }
}
