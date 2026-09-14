<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceNumberSetting extends Model
{
    protected $fillable = ['year', 'next_sequence', 'updated_by'];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
