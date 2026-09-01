<?php

namespace App\Enums;

enum InvoicePhase: string
{
    case Dp = 'dp';
    case Full = 'full';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Dp => 'DP 50%',
            self::Full => 'Full 100%',
            self::Final => 'Pelunasan',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $phase) => ['value' => $phase->value, 'label' => $phase->label()],
            self::cases(),
        );
    }
}
