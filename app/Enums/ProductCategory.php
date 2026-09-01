<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Material = 'material';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Service => 'Jasa',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $category) => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}
