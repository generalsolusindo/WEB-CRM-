<?php

namespace App\Enums;

enum SurveyStatus: string
{
    case Requested = 'requested';
    case FinanceReview = 'finance_review';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingBriefing = 'awaiting_briefing';
    case InProgress = 'in_progress';
    case ReportReview = 'report_review';
    case Verified = 'verified';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Permintaan Baru',
            self::FinanceReview => 'Menunggu Finance',
            self::AwaitingPayment => 'Menunggu Pembayaran',
            self::AwaitingBriefing => 'Menunggu Arahan Operasional',
            self::InProgress => 'Survey Berjalan',
            self::ReportReview => 'Verifikasi Laporan',
            self::Verified => 'Laporan Terverifikasi',
            self::Closed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
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
