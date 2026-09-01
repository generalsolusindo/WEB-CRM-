<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $appends = ['tax_amount', 'line_total'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'sales_order_line_id',
        'item_name',
        'qty',
        'unit_price',
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
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /** PPN baris ini: DPP x tax_rate%, dibulatkan 2 desimal. */
    protected function taxAmount(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal * (float) $this->tax_rate / 100, 2));
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal + $this->tax_amount, 2));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
