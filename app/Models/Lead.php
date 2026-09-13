<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'contact_id',
        'sales_id',
        'type',
        'stage',
        'source',
        'notes',
        'pic_name',
        'pic_position',
        'pic_phone',
        'delegated_to',
        'delegated_by',
        'delegated_at',
    ];

    protected function casts(): array
    {
        return [
            'delegated_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function delegatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to');
    }

    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function procurementRequests(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class);
    }

    public function latestProcurementRequest(): HasOne
    {
        return $this->hasOne(ProcurementRequest::class)->latestOfMany();
    }

    /**
     * Requirement dianggap terkunci selama ada Procurement Request yang masih aktif.
     * Bila PR terbaru sudah 'rejected', requirement dibuka lagi untuk revisi Sales.
     */
    public function requirementsLocked(): bool
    {
        $latest = $this->relationLoaded('latestProcurementRequest')
            ? $this->latestProcurementRequest
            : $this->latestProcurementRequest()->first();

        return $latest !== null && $latest->status !== 'rejected';
    }

    /**
     * Sales Order aktif (bukan hasil addendum) milik Lead ini — dasar untuk mengajukan
     * pengajuan tambahan (addendum) tanpa perlu Lead baru.
     */
    public function activeSalesOrderForAddendum(): ?SalesOrder
    {
        return SalesOrder::query()
            ->whereHas('quotation', fn ($q) => $q->where('lead_id', $this->id))
            ->whereNull('addendum_of_sales_order_id')
            ->whereIn('status', [
                SalesOrderStatus::Confirmed->value,
                SalesOrderStatus::Won->value,
                SalesOrderStatus::InProgress->value,
                SalesOrderStatus::Completed->value,
            ])
            ->latest('id')
            ->first();
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }
}
