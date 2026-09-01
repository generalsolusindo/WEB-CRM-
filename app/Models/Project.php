<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'status',
        'created_by',
        'planned_start',
        'planned_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_start' => 'date:Y-m-d',
            'planned_end' => 'date:Y-m-d',
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

    public function technicians(): HasMany
    {
        return $this->hasMany(ProjectTechnician::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function actualProcurements(): HasMany
    {
        return $this->hasMany(ActualProcurement::class);
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
