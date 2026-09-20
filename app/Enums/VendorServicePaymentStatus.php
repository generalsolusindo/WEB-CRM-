<?php

namespace App\Enums;

enum VendorServicePaymentStatus: string
{
    case AwaitingDp = 'awaiting_dp';
    case InProgress = 'in_progress';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingDp => 'Menunggu DP Finance',
            self::InProgress => 'Vendor Berjalan',
            self::Paid => 'Lunas',
        };
    }
}
