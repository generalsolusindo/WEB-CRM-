<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SowScopeSection extends Model
{
    protected $fillable = [
        'sow_id',
        'position',
        'title',
        'content',
    ];

    public function sow(): BelongsTo
    {
        return $this->belongsTo(Sow::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
