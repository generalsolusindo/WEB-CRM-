<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'unit',
        'qty_on_hand',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
