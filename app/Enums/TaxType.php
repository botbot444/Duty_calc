<?php

namespace App\Enums;

enum TaxType: string
{
    case CustomsDuty = 'customs_duty';
    case Excise = 'excise';
    case Vat = 'vat';

    /** English label; the frontend translates through __() later. */
    public function label(): string
    {
        return match ($this) {
            self::CustomsDuty => 'Customs duty',
            self::Excise => 'Excise duty',
            self::Vat => 'VAT',
        };
    }
}
