<?php

namespace App\Enums;

enum LeadStage: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Requirement = 'requirement';
    case Procurement = 'procurement';
    case Quotation = 'quotation';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Qualified => 'Terkualifikasi',
            self::Requirement => 'Requirement',
            self::Procurement => 'Procurement',
            self::Quotation => 'Quotation',
            self::Negotiation => 'Negosiasi',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $stage) => ['value' => $stage->value, 'label' => $stage->label()],
            self::cases(),
        );
    }
}
