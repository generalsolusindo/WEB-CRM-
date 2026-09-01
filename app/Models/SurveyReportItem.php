<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_report_id',
        'item_name',
        'qty',
        'unit',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(SurveyReport::class, 'survey_report_id');
    }
}
