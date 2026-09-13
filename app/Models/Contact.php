<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contact extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_name',
        'phone',
        'email',
        'address',
        'npwp',
        'notes',
        'created_by',
    ];

    /**
     * The user who created this contact.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Dokumen NPWP terbaru yang diupload — kalau ada beberapa, ambil yang paling baru. */
    public function npwpDocument(): ?Attachment
    {
        return $this->attachments()
            ->where('category', 'npwp_document')
            ->latest()
            ->first();
    }

    /**
     * Nomor telepon dalam format internasional siap-WA (62xxxxxxxxxx), atau null bila tidak valid.
     */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        $digits = ltrim($digits, '0');           // 0812... -> 812...
        if (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;              // 812... -> 62812...
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
