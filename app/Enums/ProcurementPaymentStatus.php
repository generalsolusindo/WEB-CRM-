<?php

namespace App\Enums;

enum ProcurementPaymentStatus: string
{
    case Draft = 'draft';
    case PendingPm = 'pending_pm';
    case RejectedPm = 'rejected_pm';
    case ApprovedPm = 'approved_pm';
    case Paid = 'paid';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingPm => 'Menunggu Persetujuan PM',
            self::RejectedPm => 'Ditolak PM',
            self::ApprovedPm => 'Menunggu Pembayaran Finance',
            self::Paid => 'Sudah Dibayar Finance',
            self::Confirmed => 'Dikonfirmasi Procurement',
        };
    }

    /** Status di mana Procurement masih bisa menyunting sourcing. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::RejectedPm], true);
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
