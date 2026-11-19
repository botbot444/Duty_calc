<?php

namespace App\Enums;

/**
 * The tariff line's statistical unit. The trader gives quantity in this unit,
 * so the engine never converts units.
 */
enum StatUnit: string
{
    case Kilogram = 'kg';
    case Unit = 'u';
    case Litre = 'l';

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'kilograms',
            self::Unit => 'units (pieces)',
            self::Litre => 'litres',
        };
    }
}
