<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveySurveyor extends Model
{
    protected $fillable = [
        'survey_id',
        'technician_id',
        'is_leader',
    ];

    protected function casts(): array
    {
        return ['is_leader' => 'boolean'];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
