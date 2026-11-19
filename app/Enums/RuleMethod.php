<?php

namespace App\Enums;

enum RuleMethod: string
{
    /** rate x base */
    case AdValorem = 'ad_valorem';
    /** specific_amount x quantity */
    case Specific = 'specific';
    /** both sides, combined as the rule's CombineMode says */
    case Compound = 'compound';
}
