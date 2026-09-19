<?php

namespace App\Enums;

enum LeadTemperature: string
{
    case Cold = 'cold';
    case Warm = 'warm';
    case Hot = 'hot';

    public function label(): string
    {
        return match ($this) {
            self::Cold => 'Cold',
            self::Warm => 'Warm',
            self::Hot => 'Hot',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $temperature) => ['value' => $temperature->value, 'label' => $temperature->label()],
            self::cases(),
        );
    }
}
