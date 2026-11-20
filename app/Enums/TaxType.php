<?php

namespace App\Enums;

enum TaxType: string
{
    case CustomsDuty = 'customs_duty';
    case Excise = 'excise';
    case Vat = 'vat';
    case ExportDuty = 'export_duty';

    /** English label; the frontend translates through __() later. */
    public function label(): string
    {
        return match ($this) {
            self::CustomsDuty => 'Customs duty',
            self::Excise => 'Excise duty',
            self::Vat => 'VAT',
            self::ExportDuty => 'Export duty',
        };
    }

    /**
     * The charges this deployment estimates (config dutycalc.charged_tax_types). Rules of
     * any other type on a tariff line are ignored, so import and export rules can share a line.
     *
     * @return list<self>
     */
    public static function charged(): array
    {
        return array_map(fn (string $t) => self::from($t), config('dutycalc.charged_tax_types', []));
    }
}
