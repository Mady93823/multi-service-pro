<?php

namespace App\Domain\Catalog\Enums;

enum PricingType: string
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';
    case Inspection = 'inspection';

    /**
     * Human-readable label (translated at render time on the frontend).
     */
    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed price',
            self::Hourly => 'Per hour',
            self::Inspection => 'Inspection first',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
