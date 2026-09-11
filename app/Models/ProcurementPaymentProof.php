<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPaymentProof extends Model
{
    protected $fillable = [
        'procurement_payment_id',
        'actual_procurement_id',
        'file_path',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ProcurementPayment::class, 'procurement_payment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ActualProcurement::class, 'actual_procurement_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
