<?php

namespace App\Actions\Operational;

use App\Enums\ActualProcurementStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Dipicu otomatis saat invoice muka Sales Order sudah lunas.
 * Membuat Project (draft) dan menurunkan daftar kebutuhan barang
 * (actual_procurements) dari procurement_request_lines — tanpa input manual.
 */
class InitializeProject
{
    public function __construct(private Notify $notify) {}

    public function handle(SalesOrder $salesOrder): Project
    {
        return DB::transaction(function () use ($salesOrder) {
            $order = SalesOrder::query()
                ->with(['contact:id,name', 'quotation.procurementRequest.lines.vendorProduct:id,vendor_id'])
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $project = $order->projects()->first();

            if (! $project) {
                $project = $order->projects()->create([
                    'status' => ProjectStatus::Draft->value,
                    'created_by' => null,
                ]);
            }

            if ($project->actualProcurements()->doesntExist()) {
                $lines = $order->quotation?->procurementRequest?->lines ?? collect();

                foreach ($lines as $line) {
                    $project->actualProcurements()->create([
                        'requested_by' => null,
                        'vendor_id' => $line->vendorProduct?->vendor_id,
                        'vendor_product_id' => $line->vendor_product_id,
                        'item_name' => $line->item_name,
                        'qty' => $line->qty,
                        'unit' => $line->unit,
                        'cost_price' => $line->cost_price,
                        'status' => ActualProcurementStatus::Pending->value,
                    ]);
                }
            }

            $customer = $order->contact?->name ?? 'customer';

            if ($project->actualProcurements()->exists()) {
                $this->notify->onceForEach(
                    User::query()->where('role', 'procurement')->where('is_active', true)->get(),
                    'project_procurement.requested',
                    "Pengadaan barang untuk Sales Order {$order->number} ({$customer}) sudah bisa diproses.",
                    $project,
                );
            }

            return $project;
        });
    }
}
