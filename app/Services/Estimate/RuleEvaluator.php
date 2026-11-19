<?php

namespace App\Services\Estimate;

use App\Enums\CombineMode;
use App\Enums\RuleMethod;
use App\Models\TariffRule;
use Brick\Math\BigDecimal;
use LogicException;

/**
 * Works out one charge from one rule. Pure maths, no database, no rounding:
 * the result is exact and EstimateService rounds it once.
 *
 *   ad_valorem: rate x base
 *   specific:   specific_amount x quantity
 *   compound:   both parts, added (sum) or the higher one (max)
 *
 * Missing values throw instead of defaulting: a rule is never evaluated with a guessed rate.
 */
class RuleEvaluator
{
    public function evaluate(TariffRule $rule, BigDecimal $base, ?BigDecimal $quantity): BigDecimal
    {
        return match ($rule->method) {
            RuleMethod::AdValorem => $this->adValorem($rule, $base),
            RuleMethod::Specific => $this->specific($rule, $quantity),
            RuleMethod::Compound => $this->compound($rule, $base, $quantity),
        };
    }

    private function adValorem(TariffRule $rule, BigDecimal $base): BigDecimal
    {
        if ($rule->rate === null) {
            throw new LogicException("Rule {$rule->id} is ad valorem but has no rate");
        }

        return BigDecimal::of($rule->rate)->multipliedBy($base);
    }

    private function specific(TariffRule $rule, ?BigDecimal $quantity): BigDecimal
    {
        if ($rule->specific_amount === null) {
            throw new LogicException("Rule {$rule->id} has no specific amount");
        }
        if ($quantity === null) {
            throw new LogicException("Rule {$rule->id} needs a quantity");
        }

        return BigDecimal::of($rule->specific_amount)->multipliedBy($quantity);
    }

    private function compound(TariffRule $rule, BigDecimal $base, ?BigDecimal $quantity): BigDecimal
    {
        $adValorem = $this->adValorem($rule, $base);
        $specific = $this->specific($rule, $quantity);

        return match ($rule->combine) {
            CombineMode::Sum => $adValorem->plus($specific),
            CombineMode::Max => $adValorem->isGreaterThanOrEqualTo($specific) ? $adValorem : $specific,
            null => throw new LogicException("Compound rule {$rule->id} has no combine mode"),
        };
    }
}
