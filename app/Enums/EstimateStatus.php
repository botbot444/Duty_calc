<?php

namespace App\Enums;

enum EstimateStatus: string
{
    /** Every charge was found and calculated. */
    case Estimated = 'estimated';

    /** Our data cannot support this request (unknown item or route, no rule or rate in force). */
    case Unsupported = 'unsupported';

    /** The trader left out an input the rules need (e.g. quantity for a per-kg duty). */
    case Incomplete = 'incomplete';
}
