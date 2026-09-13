<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActualProcurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'procurement_payment_id',
        'requested_by',
        'vendor_id',
        'bank_account_note',
        'vendor_product_id',
        'item_name',
        'qty',
        'unit',
        'cost_price',
        'estimated_cost',
        'from_office_stock',
        'office_stock_note',
        'warehouse_item_id',
        'warehouse_qty',
        'is_paid',
        'paid_at',
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
            'estimated_cost' => 'decimal:2',
            'from_office_stock' => 'boolean',
            'warehouse_qty' => 'integer',
            'is_paid' => 'boolean',
            'paid_at' => 'datetime',
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

    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function procurementPayment(): BelongsTo
    {
        return $this->belongsTo(ProcurementPayment::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(ProcurementPaymentProof::class, 'actual_procurement_id');
    }
}
