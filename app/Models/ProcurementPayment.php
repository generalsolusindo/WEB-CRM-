<?php

namespace App\Models;

use App\Enums\ProcurementPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementPayment extends Model
{
    protected $fillable = [
        'project_id',
        'number',
        'status',
        'pricing_mode',
        'lump_sum_vendor_id',
        'lump_sum_amount',
        'bank_account_note',
        'submitted_by',
        'submitted_at',
        'pm_reviewed_by',
        'pm_reviewed_at',
        'pm_notes',
        'finance_paid_by',
        'finance_paid_at',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProcurementPaymentStatus::class,
            'lump_sum_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'pm_reviewed_at' => 'datetime',
            'finance_paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Baris material yang tercakup dalam pengajuan ini. */
    public function items(): HasMany
    {
        return $this->hasMany(ActualProcurement::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(ProcurementPaymentProof::class);
    }

    public function lumpSumVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'lump_sum_vendor_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function pmReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_reviewed_by');
    }

    public function financePaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_paid_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Item yang benar-benar dibeli (bukan dari stok kantor). */
    public function purchasableItems()
    {
        return $this->items->where('from_office_stock', false);
    }

    public function totalCost(): float
    {
        if ($this->pricing_mode === 'lump_sum') {
            return (float) ($this->lump_sum_amount ?? 0);
        }

        return (float) $this->purchasableItems()
            ->sum(fn ($item) => (float) $item->cost_price * (float) $item->qty);
    }
}
