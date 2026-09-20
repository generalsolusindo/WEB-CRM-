<?php

namespace App\Models;

use App\Enums\VendorServicePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deal jasa vendor luar untuk satu project: fee, termin, dan rekening tujuan — diisi
 * Procurement, dibayar Finance (DP lalu pelunasan setelah BAST, atau lunas di akhir).
 */
class VendorServicePayment extends Model
{
    public const TERMS_DP_FINAL = 'dp_final';

    public const TERMS_PAY_AT_END = 'pay_at_end';

    protected $fillable = [
        'project_id',
        'number',
        'vendor_id',
        'total_fee',
        'terms',
        'dp_amount',
        'bank_name',
        'account_number',
        'account_holder',
        'notes',
        'status',
        'released_at',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorServicePaymentStatus::class,
            'total_fee' => 'decimal:2',
            'dp_amount' => 'decimal:2',
            'released_at' => 'datetime',
            'submitted_at' => 'datetime',
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

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** Transfer yang masih aktif (yang dibatalkan Finance tidak ikut). */
    public function entries(): HasMany
    {
        return $this->hasMany(VendorServicePaymentEntry::class);
    }

    public function activeEntry(string $kind): ?VendorServicePaymentEntry
    {
        return $this->entries()->where('kind', $kind)->latest('id')->first();
    }

    public function bastVerified(): bool
    {
        return $this->project->bastRecords()->where('status', 'verified')->exists();
    }

    /** Pelunasan hanya boleh dibayar setelah BAST diverifikasi Operasional (dan DP, kalau ada, sudah dibayar). */
    public function canPayFinal(): bool
    {
        return $this->status === VendorServicePaymentStatus::InProgress
            && $this->activeEntry(VendorServicePaymentEntry::KIND_FINAL) === null
            && $this->bastVerified();
    }

    public function canPayDp(): bool
    {
        return $this->hasDp()
            && $this->status === VendorServicePaymentStatus::AwaitingDp
            && $this->activeEntry(VendorServicePaymentEntry::KIND_DP) === null;
    }

    public function hasDp(): bool
    {
        return $this->terms === self::TERMS_DP_FINAL;
    }

    /** Sisa yang dibayar setelah BAST: total dikurangi DP (atau seluruh fee kalau bayar di akhir). */
    public function finalAmount(): float
    {
        return round((float) $this->total_fee - ($this->hasDp() ? (float) $this->dp_amount : 0), 2);
    }

    public function dpPercent(): ?float
    {
        return $this->hasDp() && (float) $this->total_fee > 0
            ? round((float) $this->dp_amount / (float) $this->total_fee * 100, 2)
            : null;
    }

    /** Deal masih boleh diubah Procurement selama belum ada transfer yang tercatat. */
    public function isEditable(): bool
    {
        return $this->status !== VendorServicePaymentStatus::Paid && ! $this->entries()->exists();
    }
}
