<?php

namespace App\Enums;

enum ActualProcurementStatus: string
{
    case Pending = 'pending';
    case Purchased = 'purchased';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum Dibeli',
            self::Purchased => 'Sudah Dibeli',
            self::Received => 'Sudah Diterima',
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
