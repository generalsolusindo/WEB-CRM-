<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Searching = 'searching';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Unavailable => 'Tidak Tersedia',
            self::Searching => 'Masih Dicari',
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
