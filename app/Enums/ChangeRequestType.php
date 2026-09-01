<?php

namespace App\Enums;

enum ChangeRequestType: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Change = 'change';
    case UrgentAdditional = 'urgent_additional';

    public function label(): string
    {
        return match ($this) {
            self::Add => 'Tambah Item',
            self::Remove => 'Kurangi Item',
            self::Change => 'Ganti Item',
            self::UrgentAdditional => 'Additional Work (Urgent)',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
