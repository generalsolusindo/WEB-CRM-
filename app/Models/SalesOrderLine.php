<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $appends = ['tax_amount', 'gross', 'line_total'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sales_order_id',
        'quotation_line_id',
        'item_name',
        'description',
        'qty',
        'unit',
        'cost_price',
        'selling_price',
        'discount_percent',
        'discount_amount',
        'tax_id',
        'tax_rate',
        'subtotal',
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
            'selling_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected function gross(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal + (float) $this->discount_amount, 2));
    }

    protected function taxAmount(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal * (float) $this->tax_rate / 100, 2));
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal + $this->tax_amount, 2));
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(QuotationLine::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
