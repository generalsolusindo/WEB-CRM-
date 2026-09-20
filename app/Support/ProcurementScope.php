<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class ProcurementScope
{
    /** @var list<string> */
    private const FIELDS = ['item_name', 'description', 'qty', 'unit', 'category'];

    /** @param array<string, mixed>|Model $current */
    public static function differs(Model $original, array|Model $current): bool
    {
        foreach (self::FIELDS as $field) {
            $before = $original->getAttribute($field);
            $after = $current instanceof Model ? $current->getAttribute($field) : ($current[$field] ?? null);

            if (self::normalize($field, $before) !== self::normalize($field, $after)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $field, mixed $value): string|float
    {
        if ($field === 'qty') {
            return round((float) $value, 2);
        }

        return trim((string) ($value ?? ''));
    }
}
