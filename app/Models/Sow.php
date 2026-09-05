<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sow extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'status',
        'number',
        'project_name',
        'site_location',
        'client_name',
        'execution_date',
        'background',
        'scope_pre_work',
        'scope_other',
        'responsibilities',
        'schedule',
        'safety',
        'payment_terms',
        'output',
        'warranty',
        'notes',
        'closing',
        'technician_id',
        'technician_team_note',
        'client_pic_name',
        'client_pic_phone',
        'created_by',
        'updated_by',
        'submitted_at',
        'hr_content_reviewed_by',
        'hr_content_reviewed_at',
        'hr_content_review_notes',
        'technician_signature',
        'technician_signed_at',
        'vendor_signature',
        'vendor_signed_at',
        'vendor_signed_by',
        'hr_signature_reviewed_by',
        'hr_signature_reviewed_at',
        'hr_signature_review_notes',
        'admin_signature',
        'admin_signed_at',
        'admin_signed_by',
        'director_signature',
        'director_signed_at',
        'director_signed_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'hr_content_reviewed_at' => 'datetime',
            'technician_signed_at' => 'datetime',
            'vendor_signed_at' => 'datetime',
            'hr_signature_reviewed_at' => 'datetime',
            'admin_signed_at' => 'datetime',
            'director_signed_at' => 'datetime',
        ];
    }

    public function hrContentReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_content_reviewed_by');
    }

    public function vendorSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_signed_by');
    }

    public function hrSignatureReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_signature_reviewed_by');
    }

    public function adminSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_signed_by');
    }

    public function directorSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_signed_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
