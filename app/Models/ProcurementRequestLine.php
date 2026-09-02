<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequestLine extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'procurement_request_id',
        'requirement_id',
        'vendor_product_id',
        'sourcing_note',
        'item_name',
        'category',
        'description',
        'qty',
        'unit',
        'cost_price',
        'tax_id',
        'availability_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function vendorProduct(): BelongsTo
    {
        return $this->belongsTo(VendorProduct::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
