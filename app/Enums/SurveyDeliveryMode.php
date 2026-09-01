<?php

namespace App\Enums;

enum SurveyDeliveryMode: string
{
    case Internal = 'internal';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Surveyor Internal / HO',
            self::Vendor => 'Vendor Luar',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $mode) => ['value' => $mode->value, 'label' => $mode->label()],
            self::cases(),
        );
    }
}
