<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requirement extends Model
{
    use HasFactory;

    /**
     * Satuan tetap untuk kebutuhan material/jasa customer (dipilih Sales lewat dropdown).
     *
     * @var list<string>
     */
    public const UNITS = [
        'meter', 'node', 'rol', 'batang', 'pasang', 'titik', 'set', 'pack', 'core', 'kilo', 'unit', 'lot',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'item_name',
        'category',
        'description',
        'qty',
        'unit',
        'notes',
        'created_by',
        'submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
