<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Material = 'material';
    case Service = 'service';
    case Reimburse = 'reimburse';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Service => 'Jasa',
            self::Reimburse => 'Biaya Reimburse',
        };
    }

    /** Baris jasa yang menjadi dasar pemotongan PPh 23. */
    public function isService(): bool
    {
        return $this === self::Service;
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
