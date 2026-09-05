<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Draft cetakan BAST yang disiapkan Operational sebelum teknisi berangkat ke lapangan —
 * terpisah dari record `Bast` (bukti serah terima yang sudah ditandatangani, diunggah teknisi).
 */
class BastDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'number',
        'event_date',
        'job_title',
        'work_description',
        'pic_name',
        'pic_position',
        'pic_address',
        'leader_name',
        'leader_position',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
