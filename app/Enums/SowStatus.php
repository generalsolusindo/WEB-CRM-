<?php

namespace App\Enums;

enum SowStatus: string
{
    case Draft = 'draft';
    case PendingHrReview = 'pending_hr_review';
    case RejectedByHr = 'rejected_by_hr';
    case PendingTechnicianSignature = 'pending_technician_signature';
    case PendingVendorSignature = 'pending_vendor_signature';
    case PendingHrVerification = 'pending_hr_verification';
    case RejectedSignature = 'rejected_signature';
    case PendingAdminSignature = 'pending_admin_signature';
    case PendingDirectorSignature = 'pending_director_signature';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingHrReview => 'Menunggu Review HR',
            self::RejectedByHr => 'Ditolak HR',
            self::PendingTechnicianSignature => 'Menunggu TTD Teknisi',
            self::PendingVendorSignature => 'Menunggu TTD PIC Vendor',
            self::PendingHrVerification => 'Menunggu Verifikasi TTD (HR)',
            self::RejectedSignature => 'TTD Ditolak HR',
            self::PendingAdminSignature => 'Menunggu TTD Admin Project',
            self::PendingDirectorSignature => 'Menunggu TTD Direktur',
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
