<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Planning = 'planning';
    case WaitingResource = 'waiting_resource';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Verification = 'verification';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Planning => 'Perencanaan',
            self::WaitingResource => 'Menunggu Barang',
            self::Ready => 'Siap',
            self::InProgress => 'Berjalan',
            self::Verification => 'Verifikasi',
            self::Completed => 'Selesai',
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
