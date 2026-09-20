<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'status',
        'created_by',
        'planned_start',
        'planned_end',
        'delegated_to',
        'delegated_by',
        'delegated_at',
        'vendor_id',
        'needs_outside_vendor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_start' => 'date:Y-m-d',
            'planned_end' => 'date:Y-m-d',
            'delegated_at' => 'datetime',
            'needs_outside_vendor' => 'boolean',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function delegatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to');
    }

    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(ProjectTechnician::class);
    }

    public function bastDraft(): HasOne
    {
        return $this->hasOne(BastDraft::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function sow(): HasOne
    {
        return $this->hasOne(Sow::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Sudah absen (selfie kedatangan) — wajib sebelum bisa mengerjakan task. */
    public function hasCheckedIn(User $user): bool
    {
        return $this->attachments()
            ->where('category', 'checkin_selfie')
            ->where('uploaded_by', $user->id)
            ->exists();
    }

    /** Sudah absen pulang (selfie checkout). */
    public function hasCheckedOut(User $user): bool
    {
        return $this->attachments()
            ->where('category', 'checkout_selfie')
            ->where('uploaded_by', $user->id)
            ->exists();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function actualProcurements(): HasMany
    {
        return $this->hasMany(ActualProcurement::class);
    }

    public function procurementPayments(): HasMany
    {
        return $this->hasMany(ProcurementPayment::class);
    }

    /** Deal jasa vendor luar untuk project ini (diisi Procurement). */
    public function vendorServicePayment(): HasOne
    {
        return $this->hasOne(VendorServicePayment::class);
    }

    /** Pengajuan pembayaran pengadaan yang aktif (paling baru). */
    public function procurementPayment(): HasOne
    {
        return $this->hasOne(ProcurementPayment::class)->latestOfMany();
    }

    public function bastRecords(): HasMany
    {
        return $this->hasMany(Bast::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ProjectChangeRequest::class);
    }

    public function leader(): HasOne
    {
        return $this->hasOne(ProjectTechnician::class)->where('is_leader', true);
    }
}
