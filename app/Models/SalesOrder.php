<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SalesOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'quotation_id',
        'addendum_of_sales_order_id',
        'contact_id',
        'order_type',
        'payment_rule',
        'status',
        'agreed_dpp',
        'dp_percent',
        'po_number',
        'po_date',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'agreed_dpp' => 'decimal:2',
            'dp_percent' => 'decimal:2',
            'po_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Sales Order asal (yang sedang/sudah berjalan) yang menjadi dasar tambahan ini. */
    public function addendumOfSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'addendum_of_sales_order_id');
    }

    /** Daftar Sales Order tambahan (addendum) yang lahir dari Sales Order ini. */
    public function addenda(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'addendum_of_sales_order_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
