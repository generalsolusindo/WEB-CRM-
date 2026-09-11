<?php

namespace App\Actions\Procurement;

use App\Enums\ActualProcurementStatus;
use App\Models\ActualProcurement;
use App\Models\User;
use App\Services\Operational\ProjectMaterialProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveProcurementItem
{
    public function __construct(private ProjectMaterialProgress $progress) {}

    public function handle(ActualProcurement $item, User $procurement): ActualProcurement
    {
        return DB::transaction(function () use ($item, $procurement) {
            $locked = ActualProcurement::query()
                ->with('project')
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_paid && ! $locked->from_office_stock) {
                throw ValidationException::withMessages([
                    'item' => 'Barang belum dibayar Finance — belum bisa ditandai diterima.',
                ]);
            }

            $locked->update([
                'status' => ActualProcurementStatus::Received->value,
                'received_at' => $locked->received_at ?? now(),
                'handled_by' => $procurement->id,
            ]);

            $this->progress->sync($locked->project);

            return $locked->fresh();
        });
    }
}
