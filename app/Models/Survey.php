<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'requested_by',
        'site_address',
        'site_region',
        'delivery_mode',
        'billable',
        'notes',
        'status',
        'vendor_id',
        'surveyor_id',
        'cost',
        'sourced_by',
        'sourced_at',
        'finance_handled_by',
        'finance_handled_at',
        'finance_note',
        'briefing',
        'briefed_by',
        'briefed_at',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $appends = ['code'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'billable' => 'boolean',
            'cost' => 'decimal:2',
            'sourced_at' => 'datetime',
            'finance_handled_at' => 'datetime',
            'briefed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function code(): Attribute
    {
        return Attribute::get(fn (): string => 'SVY-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function sourcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sourced_by');
    }

    public function financeHandledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_handled_by');
    }

    public function briefedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'briefed_by');
    }

    public function report(): HasOne
    {
        return $this->hasOne(SurveyReport::class);
    }

    /** Invoice survey yang masih aktif (mengabaikan yang dibatalkan). */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('status', '!=', 'cancelled');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Survey butuh keterlibatan Finance bila ditagih ke customer atau memakai vendor berbayar. */
    public function needsFinance(): bool
    {
        return $this->billable || $this->delivery_mode === 'vendor';
    }
}
