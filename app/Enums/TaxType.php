<?php

namespace App\Enums;

enum TaxType: string
{
    case CustomsDuty = 'customs_duty';
    case Excise = 'excise';
    case Vat = 'vat';
}
