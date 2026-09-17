<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'procurement_request_id',
        'lead_id',
        'contact_id',
        'sales_id',
        'status',
        'revision_number',
        'parent_quotation_id',
        'is_addendum',
        'valid_until',
        'quoted_at',
        'notes',
        'terms',
        'whatsapp_sent_at',
        'whatsapp_sent_by',
        'agreed_dpp',
        'pm_review_status',
        'pm_reviewed_by',
        'pm_reviewed_at',
        'pm_review_notes',
        'manager_review_status',
        'manager_reviewed_by',
        'manager_reviewed_at',
        'manager_review_notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'agreed_dpp' => 'decimal:2',
            'valid_until' => 'date',
            'quoted_at' => 'date',
            'pm_reviewed_at' => 'datetime',
            'manager_reviewed_at' => 'datetime',
            'is_addendum' => 'boolean',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    public function isFullyApproved(): bool
    {
        return $this->pm_review_status === 'approved' && $this->manager_review_status === 'approved';
    }

    public function pmReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_reviewed_by');
    }

    public function managerReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_reviewed_by');
    }

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'parent_quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'parent_quotation_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }
}
