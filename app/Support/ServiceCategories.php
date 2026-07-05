<?php

namespace App\Support;

class ServiceCategories
{
    public const LABELS = [
        'wash' => 'Wash',
        'dry' => 'Dry',
        'dry_extend' => 'Dry Extend',
        'fabcon' => 'Fabcon',
        'detergent' => 'Detergent',
        'fold' => 'Fold',
        'rush' => 'Rush',
        'delivery' => 'Delivery',
        'small' => 'Small Machine',
        'big' => 'Big Machine',
        'other' => 'Extra Services',
        'special' => 'Special Services',
    ];

    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function infer(?string $name): string
    {
        $name = strtolower(trim((string) $name));

        return match (true) {
            str_contains($name, 'dry') && (str_contains($name, 'extend') || str_contains($name, 'extra')) => 'dry_extend',
            str_contains($name, 'fabcon'), str_contains($name, 'fabric conditioner') => 'fabcon',
            str_contains($name, 'detergent') => 'detergent',
            str_contains($name, 'delivery'), str_contains($name, 'pickup') => 'delivery',
            str_contains($name, 'small machine') => 'small',
            str_contains($name, 'big machine') => 'big',
            str_contains($name, 'wash') => 'wash',
            str_contains($name, 'dry') => 'dry',
            str_contains($name, 'fold') => 'fold',
            default => 'other',
        };
    }
}
