<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Role yang bisa dibuat/dikelola langsung lewat "Manajemen User" Administrator.
     * "vendor" sengaja dikecualikan — akun vendor wajib terhubung ke satu baris Vendor
     * (vendor_id) dan sudah punya alur pembuatan sendiri di Procurement > Akun PIC Vendor
     * yang menjaga aturan itu (satu vendor cuma boleh satu akun PIC).
     *
     * @var array<string, string>
     */
    public const ADMIN_ASSIGNABLE_ROLES = [
        'sales' => 'Sales',
        'procurement' => 'Procurement',
        'operational' => 'Operasional',
        'technician' => 'Teknisi',
        'finance' => 'Finance',
        'management' => 'Manajemen',
        'administrator' => 'Administrator',
        'project_manager' => 'Project Manager',
        'hr' => 'HR',
        'warehouse' => 'Gudang',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'nik',
        'signature_path',
        'password',
        'role',
        'vendor_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * In-app notifications addressed to this user (not Laravel's Notifiable table).
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Models\Notification::class);
    }

    /**
     * Vendor this account belongs to (null = internal / Head Office).
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Dokumen KTP terbaru yang diupload Procurement untuk akun vendor/teknisi ini. */
    public function ktpDocument(): ?Attachment
    {
        return $this->attachments()
            ->where('category', 'ktp_document')
            ->latest()
            ->first();
    }
}
