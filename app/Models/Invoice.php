<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'invoice_type',
        'sales_order_id',
        'survey_id',
        'invoice_phase',
        'status',
        'pph23_enabled',
        'amount',
        'tax_amount',
        'pph23_rate',
        'pph23_amount',
        'pph23_bukti_potong_no',
        'pph23_recorded_at',
        'pph23_recorded_by',
        'due_date',
        'whatsapp_sent_at',
        'whatsapp_sent_by',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'pph23_enabled' => 'boolean',
            'pph23_rate' => 'decimal:2',
            'pph23_amount' => 'decimal:2',
            'due_date' => 'date:Y-m-d',
            'whatsapp_sent_at' => 'datetime',
            'pph23_recorded_at' => 'datetime',
        ];
    }

    /**
     * Nilai faktur (DPP + PPN) — piutang penuh.
     */
    public function grandTotal(): float
    {
        return (float) $this->amount + (float) $this->tax_amount;
    }

    /**
     * Jumlah kas yang ditransfer customer (setelah dipotong PPh 23).
     */
    public function payableAmount(): float
    {
        return round($this->grandTotal() - (float) $this->pph23_amount, 2);
    }

    /**
     * Total penyelesaian: kas masuk + PPh 23 (dilunasi lewat bukti potong).
     */
    public function settledAmount(): float
    {
        return round((float) $this->payments()->sum('amount_paid') + (float) $this->pph23_amount, 2);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function isSurvey(): bool
    {
        return $this->invoice_type === 'survey';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function pph23RecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pph23_recorded_by');
    }

    public function totalPaid()
    {
        return $this->payments()->sum('amount_paid');
    }
}
