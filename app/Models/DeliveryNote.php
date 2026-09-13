<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DeliveryNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'sales_order_id',
        'invoice_id',
        'delivery_method',
        'delivery_address',
        'shipper_name',
        'tracking_number',
        'approved_by_name',
        'status',
        'received_by',
        'received_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryNoteLine::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Bukti serah ke kurir/pengantar — wajib diupload Operational saat mengirim. */
    public function hasDispatchProof(): bool
    {
        return $this->attachments()->where('category', 'delivery_dispatch_proof')->exists();
    }

    /** Bukti barang diterima customer — opsional, bisa menyusul kapan saja. */
    public function hasReceivedProof(): bool
    {
        return $this->attachments()->where('category', 'delivery_received_proof')->exists();
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
