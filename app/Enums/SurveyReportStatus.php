<?php

namespace App\Enums;

enum SurveyReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Perlu Revisi',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label()],
            self::cases(),
        );
    }
}
