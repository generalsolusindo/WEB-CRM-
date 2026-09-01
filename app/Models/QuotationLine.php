<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    use HasFactory;

    /**
     * The DPP (qty * selling_price) lives in `subtotal`; tax is derived
     * from the linked Tax rate and exposed as a computed attribute.
     *
     * @var list<string>
     */
    protected $appends = ['tax_amount', 'gross', 'line_total', 'effective_margin_percent'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quotation_id',
        'procurement_request_line_id',
        'item_name',
        'description',
        'qty',
        'unit',
        'cost_price',
        'selling_price',
        'discount_percent',
        'discount_amount',
        'markup_percent',
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
            'markup_percent' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /** Bruto sebelum diskon. */
    protected function gross(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal + (float) $this->discount_amount, 2));
    }

    /** PPN baris ini: DPP x tax_rate%, dibulatkan 2 desimal. */
    protected function taxAmount(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal * (float) $this->tax_rate / 100, 2));
    }

    /** Total baris = DPP + PPN. */
    protected function lineTotal(): Attribute
    {
        return Attribute::get(fn (): float => round((float) $this->subtotal + $this->tax_amount, 2));
    }

    /** Margin efektif setelah diskon terhadap cost. */
    protected function effectiveMarginPercent(): Attribute
    {
        return Attribute::get(function (): ?float {
            $totalCost = round((float) $this->qty * (float) $this->cost_price, 2);

            return $totalCost > 0
                ? round(((float) $this->subtotal - $totalCost) / $totalCost * 100, 2)
                : null;
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function procurementRequestLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestLine::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
