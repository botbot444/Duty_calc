<?php

namespace App\Services\Estimate;

use App\Enums\CombineMode;
use App\Enums\RuleMethod;
use App\Enums\TaxType;
use App\Models\CalculationBreakdown;

/**
 * One charge in an estimate. Amounts are 2-decimal strings in ZMW.
 */
final class LineItem
{
    /** @param list<string> $baseComponents */
    public function __construct(
        public readonly TaxType $taxType,
        public readonly int $sequence,
        public readonly RuleMethod $method,
        public readonly ?string $rate,
        public readonly ?string $specificAmount,
        public readonly ?string $specificCurrency,
        public readonly ?CombineMode $combine,
        public readonly ?string $quantity,
        public readonly array $baseComponents,
        public readonly string $taxableBase,
        public readonly string $amount,
        public readonly int $ruleId,
        public readonly string $ruleEffectiveFrom,
        public readonly ?string $ruleEffectiveTo,
        public readonly int $sourceId,
        public readonly string $sourceName,
        public readonly int $sourcePage,
    ) {}

    public static function fromBreakdown(CalculationBreakdown $b): self
    {
        return new self(
            taxType: $b->tax_type,
            sequence: $b->sequence,
            method: $b->method,
            rate: $b->rate,
            specificAmount: $b->specific_amount,
            specificCurrency: $b->specific_currency,
            combine: $b->combine,
            quantity: $b->quantity,
            baseComponents: $b->base_components,
            taxableBase: $b->taxable_base_zmw,
            amount: $b->amount_zmw,
            ruleId: $b->tariff_rule_id,
            ruleEffectiveFrom: $b->rule_effective_from->toDateString(),
            ruleEffectiveTo: $b->rule_effective_to?->toDateString(),
            sourceId: $b->tariff_source_id,
            sourceName: $b->source_name,
            sourcePage: $b->source_page,
        );
    }

    /** Columns for calculation_breakdowns. */
    public function toBreakdownAttributes(): array
    {
        return [
            'sequence' => $this->sequence,
            'tax_type' => $this->taxType,
            'base_components' => $this->baseComponents,
            'taxable_base_zmw' => $this->taxableBase,
            'method' => $this->method,
            'rate' => $this->rate,
            'specific_amount' => $this->specificAmount,
            'specific_currency' => $this->specificCurrency,
            'combine' => $this->combine,
            'quantity' => $this->quantity,
            'amount_zmw' => $this->amount,
            'tariff_rule_id' => $this->ruleId,
            'rule_effective_from' => $this->ruleEffectiveFrom,
            'rule_effective_to' => $this->ruleEffectiveTo,
            'tariff_source_id' => $this->sourceId,
            'source_name' => $this->sourceName,
            'source_page' => $this->sourcePage,
        ];
    }

    public function toArray(): array
    {
        return [
            'tax_type' => $this->taxType->value,
            'label' => $this->taxType->label(),
            'sequence' => $this->sequence,
            'method' => $this->method->value,
            'rate' => $this->rate,
            'specific_amount' => $this->specificAmount,
            'specific_currency' => $this->specificCurrency,
            'combine' => $this->combine?->value,
            'quantity' => $this->quantity,
            'base_components' => $this->baseComponents,
            'taxable_base' => $this->taxableBase,
            'amount' => $this->amount,
            'rule_effective_from' => $this->ruleEffectiveFrom,
            'source' => ['name' => $this->sourceName, 'page' => $this->sourcePage],
        ];
    }
}
