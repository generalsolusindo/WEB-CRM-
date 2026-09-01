<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    use HasFactory;

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    protected $fillable = [
        'user_id',
        'type',
        'message',
        'related_type',
        'related_id',
        'is_sent',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_sent' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }
}
