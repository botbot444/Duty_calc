<?php

namespace App\Enums;

/**
 * Money values a tariff rule's base can be built from, besides charges worked out earlier
 * on the same line. The valuation basis (FOB, CIF, …) is therefore data on each rule
 * (tariff_rules.base_components), never an `if` in the engine.
 */
enum ValueComponent: string
{
    /** Value of the goods as declared by the trader, converted to ZMW. */
    case DeclaredValue = 'declared_value';

    /** Freight to the border, converted to ZMW. */
    case Freight = 'freight';

    /** Insurance to the border, converted to ZMW. */
    case Insurance = 'insurance';

    /** Declared value + freight + insurance (CIF-style), converted to ZMW. */
    case CustomsValue = 'customs_value';

    /** The trader inputs (freight, insurance) a component needs. @return list<string> */
    public function inputs(): array
    {
        return match ($this) {
            self::DeclaredValue => [],
            self::Freight => ['freight'],
            self::Insurance => ['insurance'],
            self::CustomsValue => ['freight', 'insurance'],
        };
    }

    /** Plain-words name used in the valuation assumption. */
    public function label(): string
    {
        return match ($this) {
            self::DeclaredValue => 'value of the goods',
            self::Freight => 'freight',
            self::Insurance => 'insurance',
            self::CustomsValue => 'value of the goods + freight + insurance',
        };
    }

    /**
     * Short name for a set of value components used together, e.g. "FOB" or "CIF".
     *
     * @param  list<self>  $components
     */
    public static function basis(array $components): string
    {
        $values = array_map(fn (self $c) => $c->value, $components);
        sort($values);

        return match ($values) {
            ['declared_value'] => 'FOB',
            ['customs_value'], ['declared_value', 'freight', 'insurance'] => 'CIF',
            default => implode('+', $values),
        };
    }
}
