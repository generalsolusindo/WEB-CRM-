<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    /** Tim surveyor yang ditugaskan (leader + anggota). */
    public function surveyors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'survey_surveyors', 'survey_id', 'technician_id')
            ->withPivot('is_leader')
            ->withTimestamps();
    }

    public function surveyorAssignments(): HasMany
    {
        return $this->hasMany(SurveySurveyor::class);
    }

    /** Leader tim survey (bila sudah ditugaskan). */
    public function leaderUser(): ?User
    {
        $team = $this->relationLoaded('surveyors') ? $this->surveyors : $this->surveyors()->get();

        return $team->firstWhere('pivot.is_leader', true);
    }

    public function isSurveyor(User|int $user): bool
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        return $this->surveyorAssignments()->where('technician_id', $id)->exists();
    }

    public function isLeader(User|int $user): bool
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        return $this->surveyorAssignments()->where('technician_id', $id)->where('is_leader', true)->exists();
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Sudah absen (selfie kedatangan) — wajib sebelum bisa mengisi laporan survey. */
    public function hasCheckedIn(User $user): bool
    {
        return $this->attachments()
            ->where('category', 'checkin_selfie')
            ->where('uploaded_by', $user->id)
            ->exists();
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
