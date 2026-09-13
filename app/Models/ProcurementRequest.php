<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'status',
        'requested_by',
        'notes',
        'rejection_reason',
        'is_addendum',
        'addendum_of_sales_order_id',
    ];

    protected function casts(): array
    {
        return [
            'is_addendum' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** Sales Order berjalan yang menjadi dasar submission tambahan ini (hanya terisi bila is_addendum). */
    public function addendumOfSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'addendum_of_sales_order_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementRequestLine::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
