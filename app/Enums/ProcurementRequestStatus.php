<?php

namespace App\Enums;

enum ProcurementRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Searching = 'searching';
    case Ready = 'ready';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Baru Masuk',
            self::Searching => 'Sedang Dicari',
            self::Ready => 'Ready',
            self::Rejected => 'Ditolak',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
