<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActualProcurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'requested_by',
        'vendor_id',
        'vendor_product_id',
        'item_name',
        'qty',
        'unit',
        'cost_price',
        'status',
        'handled_by',
        'purchased_at',
        'received_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'purchased_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorProduct(): BelongsTo
    {
        return $this->belongsTo(VendorProduct::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
