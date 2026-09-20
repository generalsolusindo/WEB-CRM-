<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Satu kali transfer ke vendor: DP atau pelunasan. */
class VendorServicePaymentEntry extends Model
{
    use SoftDeletes;

    public const KIND_DP = 'dp';

    public const KIND_FINAL = 'final';

    protected $fillable = [
        'vendor_service_payment_id',
        'kind',
        'amount',
        'paid_at',
        'paid_by',
        'notes',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(VendorServicePayment::class, 'vendor_service_payment_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
