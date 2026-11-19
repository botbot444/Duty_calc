<?php

namespace App\Services\Estimate;

use App\Enums\EstimateStatus;
use App\Models\Calculation;

/**
 * What the engine returns, in the developer brief's response shape. Always built from a
 * saved Calculation, so a fresh result and a reloaded one are identical.
 */
final class EstimateResult
{
    public const CURRENCY = 'ZMW';

    /**
     * @param  list<LineItem>  $lineItems
     * @param  list<string>  $assumptions
     * @param  list<array{item: string, message: string}>  $missing
     */
    public function __construct(
        public readonly EstimateStatus $status,
        public readonly string $estimateId,
        public readonly ?array $product,
        public readonly ?array $route,
        public readonly array $valuation,
        public readonly array $lineItems,
        public readonly ?string $total,
        public readonly ?string $tariffEffectiveDate,
        public readonly string $calculatedOn,
        public readonly array $assumptions,
        public readonly array $missing,
        public readonly string $disclaimer,
        public readonly string $engineVersion,
    ) {}

    public static function fromCalculation(Calculation $calc): self
    {
        $calc->loadMissing('breakdowns', 'tradeRoute.origin', 'tradeRoute.destination');
        $route = $calc->tradeRoute;

        return new self(
            status: $calc->status,
            estimateId: $calc->id,
            product: $calc->hs_code === null ? null : [
                'id' => $calc->catalogue_item_id,
                'name' => $calc->catalogue_item_name,
                'hs_code' => $calc->hs_code,
                'official_description' => $calc->official_description,
                'stat_unit' => $calc->stat_unit,
            ],
            route: $route === null ? null : [
                'id' => $route->id,
                'origin' => $route->origin_iso2,
                'origin_name' => $route->origin->name,
                'destination' => $route->destination_iso2,
                'destination_name' => $route->destination->name,
                'border_post' => $route->border_post,
            ],
            valuation: [
                'basis' => 'CIF',
                'input_currency' => $calc->input_currency,
                'exchange_rate' => $calc->exchange_rate_value,
                'exchange_rate_date' => $calc->exchange_rate_effective_from?->toDateString(),
                'exchange_rate_source' => $calc->exchange_rate_source,
                'declared_value_zmw' => $calc->declared_value_zmw,
                'freight_zmw' => $calc->freight_zmw,
                'insurance_zmw' => $calc->insurance_zmw,
                'customs_value_zmw' => $calc->customs_value_zmw,
                'quantity' => $calc->quantity,
                'quantity_unit' => $calc->quantity_unit,
            ],
            lineItems: $calc->breakdowns->map(fn ($b) => LineItem::fromBreakdown($b))->values()->all(),
            total: $calc->total_zmw,
            tariffEffectiveDate: $calc->tariff_effective_date?->toDateString(),
            calculatedOn: $calc->calculated_on->toDateString(),
            assumptions: $calc->assumptions,
            missing: $calc->missing,
            disclaimer: config('dutycalc.disclaimer'),
            engineVersion: $calc->engine_version,
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'estimate_id' => $this->estimateId,
            'currency' => self::CURRENCY,
            'product' => $this->product,
            'route' => $this->route,
            'valuation' => $this->valuation,
            'line_items' => array_map(fn (LineItem $l) => $l->toArray(), $this->lineItems),
            'total_estimated_charges' => $this->total,
            'tariff_effective_date' => $this->tariffEffectiveDate,
            'calculated_on' => $this->calculatedOn,
            'assumptions' => $this->assumptions,
            'missing' => $this->missing,
            'disclaimer' => $this->disclaimer,
            'engine_version' => $this->engineVersion,
        ];
    }
}
