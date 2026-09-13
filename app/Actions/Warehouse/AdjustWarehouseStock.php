<?php

namespace App\Actions\Warehouse;

use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustWarehouseStock
{
    public function handle(WarehouseItem $item, string $direction, int $qty): WarehouseItem
    {
        return DB::transaction(function () use ($item, $direction, $qty) {
            $locked = WarehouseItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $delta = $direction === 'in' ? $qty : -$qty;
            $newQty = $locked->qty_on_hand + $delta;

            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'qty' => "Stok tidak cukup — sisa saat ini {$locked->qty_on_hand}.",
                ]);
            }

            $locked->update(['qty_on_hand' => $newQty]);

            return $locked->refresh();
        });
    }
}
