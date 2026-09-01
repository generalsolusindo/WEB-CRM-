<?php

namespace App\Enums;

enum PaymentRule: string
{
    case Full100 = 'full_100';
    case Dp50 = 'dp_50';

    public function label(): string
    {
        return match ($this) {
            self::Full100 => 'Full Payment 100%',
            self::Dp50 => 'Down Payment 50%',
        };
    }
}
