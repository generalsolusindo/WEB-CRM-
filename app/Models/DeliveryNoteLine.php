<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteLine extends Model
{
    protected $fillable = [
        'delivery_note_id',
        'sales_order_line_id',
        'item_name',
        'unit',
        'qty_ordered',
        'qty_previous_balance',
        'qty_delivered',
        'qty_balance',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:2',
            'qty_previous_balance' => 'decimal:2',
            'qty_delivered' => 'decimal:2',
            'qty_balance' => 'decimal:2',
        ];
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }
}
