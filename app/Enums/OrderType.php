<?php

namespace App\Enums;

enum OrderType: string
{
    case MaterialOnly = 'material_only';
    case ServiceOnly = 'service_only';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::MaterialOnly => 'Material Only',
            self::ServiceOnly => 'Service Only',
            self::Mixed => 'Material + Service',
        };
    }

    public function paymentRule(): PaymentRule
    {
        return $this === self::MaterialOnly ? PaymentRule::Full100 : PaymentRule::Dp50;
    }

    /** @return array<int, array{value: string, label: string, payment_rule: string}> */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'payment_rule' => $type->paymentRule()->value,
        ], self::cases());
    }
}
