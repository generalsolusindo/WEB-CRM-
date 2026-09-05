<?php

namespace App\Services\Operational;

use App\Models\DeliveryNoteLine;
use App\Models\SalesOrder;

/**
 * Ringkasan status pengiriman material sebuah Sales Order — dipakai sebagai
 * info di halaman Project (Operational, Management, Project Manager). Murni
 * informasi, tidak mengunci alur kerja apa pun.
 */
class MaterialDeliveryStatus
{
    /** @return array{total: int, complete: int, is_complete: bool} */
    public static function of(SalesOrder $salesOrder): array
    {
        $salesOrder->loadMissing('lines');
        $materialLines = $salesOrder->lines->where('category', 'material');

        if ($materialLines->isEmpty()) {
            return ['total' => 0, 'complete' => 0, 'is_complete' => true];
        }

        $delivered = DeliveryNoteLine::query()
            ->whereIn('sales_order_line_id', $materialLines->pluck('id'))
            ->selectRaw('sales_order_line_id, SUM(qty_delivered) as total')
            ->groupBy('sales_order_line_id')
            ->pluck('total', 'sales_order_line_id');

        $complete = $materialLines
            ->filter(fn ($line) => (float) ($delivered[$line->id] ?? 0) >= (float) $line->qty)
            ->count();

        return [
            'total' => $materialLines->count(),
            'complete' => $complete,
            'is_complete' => $complete === $materialLines->count(),
        ];
    }
}
