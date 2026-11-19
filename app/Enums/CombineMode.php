<?php

namespace App\Enums;

/**
 * How a compound rule joins its ad valorem and specific parts.
 * Max handles duties such as "40% or K5 per kg, whichever is higher".
 */
enum CombineMode: string
{
    case Sum = 'sum';
    case Max = 'max';
}
