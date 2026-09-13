<?php

namespace App\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Sponsor = 'sponsor';
    case Bisnis = 'bisnis';
    case SosialMedia = 'sosial_media';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Sponsor => 'Sponsor',
            self::Bisnis => 'Bisnis',
            self::SosialMedia => 'Sosial Media',
            self::Lainnya => 'Lainnya',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $source) => ['value' => $source->value, 'label' => $source->label()],
            self::cases(),
        );
    }
}
