<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'coverage_area',
        'provides_survey',
        'provides_technical',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provides_survey' => 'boolean',
            'provides_technical' => 'boolean',
        ];
    }

    /**
     * The products offered by this vendor.
     */
    public function products(): HasMany
    {
        return $this->hasMany(VendorProduct::class);
    }

    /**
     * Technician / surveyor user accounts that belong to this vendor.
     */
    public function technicians(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }
}
