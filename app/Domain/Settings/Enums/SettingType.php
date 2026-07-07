<?php

namespace App\Domain\Settings\Enums;

enum SettingType: string
{
    case String = 'string';
    case Integer = 'int';
    case Boolean = 'bool';
    case Json = 'json';
    case Decimal = 'decimal';

    /**
     * Cast a raw stored value to its PHP type.
     */
    public function cast(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::String => $value,
            self::Integer => (int) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Json => json_decode($value, true),
            self::Decimal => $value, // keep as string, same as Eloquent decimal casts
        };
    }

    /**
     * Serialize a PHP value for storage in the value column.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => $value ? '1' : '0',
            self::Json => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }
}
