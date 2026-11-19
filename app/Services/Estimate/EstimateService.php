<?php

namespace App\Services\Estimate;

use App\Enums\EstimateStatus;
use App\Enums\TaxType;
use App\Models\Calculation;
use App\Models\CatalogueItem;
use App\Models\ExchangeRate;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TradeRoute;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The calculation engine. The only place charges are worked out: the text and photo
 * routes both end here with a confirmed catalogue item.
 *
 * Steps (plan, section 6):
 *  1. item and tariff line, active and valid today            -> else unsupported
 *  2. trade route, known and supported                         -> else unsupported
 *  3. standard-rate rules in force today, ordered by sequence  -> else unsupported
 *  4. inputs the rules need (quantity), freight and insurance  -> else incomplete
 *  5. exchange rate in force (ZMW: rate 1)                     -> else unsupported
 *  6. customs value = CIF in ZMW
 *  7. each rule in sequence: base = sum of its base_components, amount from RuleEvaluator
 *  8. round each charge once (2 dp, half up); total = sum of rounded charges
 *  9. save calculation + breakdowns with every value copied in
 *
 * Every request is saved, including unsupported and incomplete ones. "Today" is the
 * Africa/Lusaka date from now(), so tests can fix it with Carbon::setTestNow().
 */
class EstimateService
{
    private const MONEY_SCALE = 2;

    public function __construct(private readonly RuleEvaluator $evaluator) {}

    public function estimate(EstimateRequest $request): EstimateResult
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $today = $now->toDateString();
        $run = new EstimateRun($request, $now);

        // 1. Catalogue item and tariff line.
        $item = CatalogueItem::with('tariffLine')->find($request->catalogueItemId);
        $line = $item?->tariffLine;
        $usable = false;

        if ($item === null) {
            $run->unsupported('catalogue_item', "We do not have the product {$request->catalogueItemId} in our catalogue.");
        } else {
            $run->recordItem($item, $line);
            if (! $item->active) {
                $run->unsupported('catalogue_item', "{$item->display_name} ({$item->id}) is no longer supported.");
            } elseif (! $line->isInForce($now)) {
                $run->unsupported('tariff_line', "The tariff line for {$item->display_name} (HS {$line->hs_code}) is not valid on {$today}.");
            } else {
                $usable = true;
            }
        }

        // 2. Trade route.
        $route = $request->tradeRouteId === null ? null
            : TradeRoute::with('origin', 'destination')->find($request->tradeRouteId);

        if ($route === null) {
            $run->unsupported('trade_route', 'The selected trade route is not known.');
        } else {
            $run->attributes['trade_route_id'] = $route->id;
            if (! $route->supported) {
                $run->unsupported('trade_route', "The route {$route->origin->name} to {$route->destination->name} via {$route->border_post} is not supported yet.");
            }
        }

        // 3. Rules in force today.
        $rules = $usable ? $this->rulesInForce($line, $now) : collect();
        if ($usable) {
            $this->checkRules($run, $item, $line, $rules, $today);
        }

        // 5. Exchange rate (checked before deciding the status so every data gap is listed together).
        $rate = $this->exchangeRate($run, $request->currency, $now);

        if ($run->hasProblems()) {
            return $this->save($run, EstimateStatus::Unsupported);
        }

        // 4. Inputs the rules need.
        $quantity = $this->checkInputs($run, $line, $rules);

        if ($run->hasMissingInputs()) {
            return $this->save($run, EstimateStatus::Incomplete);
        }

        // 6. Customs value (CIF) in ZMW, each part rounded once.
        $declared = $this->toZmw($request->declaredValue, $rate);
        $freight = $this->toZmw($request->freight ?? '0', $rate);
        $insurance = $this->toZmw($request->insurance ?? '0', $rate);
        $customsValue = $declared->plus($freight)->plus($insurance);

        $run->attributes += [
            'declared_value_zmw' => (string) $declared,
            'freight_zmw' => (string) $freight,
            'insurance_zmw' => (string) $insurance,
            'customs_value_zmw' => (string) $customsValue,
        ];
        $run->assume('Customs value is CIF: declared value + freight + insurance, converted to ZMW.');
        $run->assume('Charged at the standard rate: no trade preference (for example COMESA or SADC) is claimed.');
        $run->assume('Each charge is rounded once, to 2 decimals, half up (to be confirmed with ZRA).');

        // 7–8. Each rule in sequence; bases and order come from the data.
        $charged = [];   // tax type => rounded amount
        $total = BigDecimal::zero()->toScale(self::MONEY_SCALE);

        foreach ($rules as $rule) {
            $base = BigDecimal::zero()->toScale(self::MONEY_SCALE);
            foreach ($rule->base_components as $component) {
                $base = $base->plus($component === 'customs_value' ? $customsValue : $charged[$component]);
            }

            $usesQuantity = $rule->needsQuantity();
            $amount = $this->evaluator
                ->evaluate($rule, $base, $usesQuantity ? $quantity : null)
                ->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);

            $charged[$rule->tax_type->value] = $amount;
            $total = $total->plus($amount);

            $run->lineItems[] = new LineItem(
                taxType: $rule->tax_type,
                sequence: $rule->sequence,
                method: $rule->method,
                rate: $rule->rate,
                specificAmount: $rule->specific_amount,
                specificCurrency: $rule->specific_currency,
                combine: $rule->combine,
                quantity: $usesQuantity ? (string) $quantity : null,
                baseComponents: $rule->base_components,
                taxableBase: (string) $base,
                amount: (string) $amount,
                ruleId: $rule->id,
                ruleEffectiveFrom: $rule->effective_from->toDateString(),
                ruleEffectiveTo: $rule->effective_to?->toDateString(),
                sourceId: $rule->tariff_source_id,
                sourceName: $rule->source->name,
                sourcePage: $rule->source_page,
            );
        }

        $run->attributes['total_zmw'] = (string) $total;
        $run->attributes['tariff_effective_date'] = $rules->max(fn (TariffRule $r) => $r->effective_from->toDateString());

        // 9. Save.
        return $this->save($run, EstimateStatus::Estimated);
    }

    /** A saved estimate, exactly as it was calculated. Null if unknown or past its expiry. */
    public function find(string $id): ?EstimateResult
    {
        $calc = Calculation::whereKey($id)->where('expires_at', '>', now())->first();

        return $calc === null ? null : EstimateResult::fromCalculation($calc);
    }

    /**
     * Which trade inputs the item's rules need, so the form shows only those fields.
     *
     * @return array<string, string> e.g. ['quantity' => 'kg']
     */
    public function requiredInputs(CatalogueItem $item): array
    {
        $line = $item->tariffLine;
        $needsQuantity = $this->rulesInForce($line, CarbonImmutable::now(config('app.timezone')))
            ->contains(fn (TariffRule $r) => $r->needsQuantity());

        return $needsQuantity ? ['quantity' => $line->stat_unit->value] : [];
    }

    // ---------------------------------------------------------------------

    /** @return Collection<int, TariffRule> */
    private function rulesInForce(TariffLine $line, CarbonImmutable $now): Collection
    {
        return TariffRule::with('source')
            ->where('tariff_line_id', $line->id)
            ->forOrigin(null)            // trade preferences are out of scope
            ->inForce($now)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();
    }

    /** Step 3: refuse to estimate on incomplete or inconsistent tariff data. */
    private function checkRules(EstimateRun $run, CatalogueItem $item, TariffLine $line, Collection $rules, string $today): void
    {
        $hs = $line->hs_code;

        if ($rules->isEmpty()) {
            $run->unsupported('tariff_rule', "No tax rates are in force for {$item->display_name} (HS {$hs}) on {$today}.");

            return;
        }

        $byType = $rules->groupBy(fn (TariffRule $r) => $r->tax_type->value);
        $required = config('dutycalc.required_tax_types', []);

        foreach (TaxType::cases() as $type) {
            $count = count($byType->get($type->value, []));
            if ($count > 1) {
                $run->unsupported('tariff_rule', "More than one {$type->label()} rate is in force for HS {$hs}; the tariff data needs fixing.");
            }
            if ($count > 0) {
                continue;
            }

            // A charge that existed and stopped with no new rate is a gap, never a zero.
            $ended = TariffRule::where('tariff_line_id', $line->id)->forOrigin(null)
                ->where('tax_type', $type->value)->where('effective_to', '<', $today)
                ->max('effective_to');

            if ($ended !== null) {
                $run->unsupported('tariff_rule', "The {$type->label()} rate ended on {$ended} and no new rate is in force for HS {$hs}.");
            } elseif (in_array($type->value, $required, true)) {
                $run->unsupported('tariff_rule', "No {$type->label()} rule in force for HS {$hs} on {$today}.");
            }
        }

        $earlier = [];   // tax types charged before the current rule
        foreach ($rules as $rule) {
            if ($rule->specific_currency !== null && $rule->specific_currency !== ExchangeRate::BASE_CURRENCY) {
                $run->unsupported('tariff_rule', "The {$rule->tax_type->label()} rate for HS {$hs} is set in {$rule->specific_currency}; only ZMW amounts are supported.");
            }

            foreach ($rule->base_components as $component) {
                if ($component === 'customs_value' || in_array($component, $earlier, true)) {
                    continue;
                }
                $needed = TaxType::tryFrom($component)?->label() ?? $component;
                $run->unsupported('tariff_rule', "The {$rule->tax_type->label()} base needs {$needed}, but no {$needed} rate is charged before it for HS {$hs}.");
            }
            $earlier[] = $rule->tax_type->value;
        }
    }

    /** Step 4: inputs the trader must give. Returns the quantity, if the rules need one. */
    private function checkInputs(EstimateRun $run, TariffLine $line, Collection $rules): ?BigDecimal
    {
        $request = $run->request;
        $unit = $line->stat_unit;
        $quantity = $request->quantity === null ? null : BigDecimal::of($request->quantity);
        $perUnit = $rules->first(fn (TariffRule $r) => $r->needsQuantity());

        if ($perUnit !== null) {
            if ($quantity === null) {
                $run->missingInput('quantity', "Enter the quantity in {$unit->label()} ({$unit->value}): the {$perUnit->tax_type->label()} is charged per {$unit->value}.");
            } elseif ($quantity->isZero()) {
                $run->missingInput('quantity', "The quantity in {$unit->label()} ({$unit->value}) must be more than 0.");
            }
        }

        foreach (['freight' => 'Freight', 'insurance' => 'Insurance'] as $field => $label) {
            if ($request->{$field} !== null) {
                continue;
            }
            if (config('dutycalc.missing_freight_insurance') === 'incomplete') {
                $run->missingInput($field, "Enter the {$field} cost (enter 0 if there is none).");
            } else {
                $run->assume("{$label} not given: counted as ZMW 0.00.");
            }
        }

        return $quantity === null ? null : $quantity->toScale(4);
    }

    /** Step 5. Returns ZMW per unit of the input currency, or null when no rate is in force. */
    private function exchangeRate(EstimateRun $run, string $currency, CarbonImmutable $now): ?BigDecimal
    {
        $run->attributes['input_currency'] = $currency;

        if ($currency === ExchangeRate::BASE_CURRENCY) {
            $run->attributes['exchange_rate_value'] = '1';
            $run->assume('Values entered in ZMW: no conversion.');

            return BigDecimal::one();
        }

        $fx = ExchangeRate::where('currency', $currency)->inForce($now)->orderByDesc('effective_from')->first();
        if ($fx === null) {
            $run->unsupported('exchange_rate', "No exchange rate for {$currency} is in force on {$now->toDateString()}. Enter the values in ZMW instead.");

            return null;
        }

        $run->attributes += [
            'exchange_rate_id' => $fx->id,
            'exchange_rate_value' => $fx->rate_to_zmw,
            'exchange_rate_effective_from' => $fx->effective_from->toDateString(),
            'exchange_rate_source' => $fx->source,
        ];
        $run->assume("Converted at {$currency} 1 = ZMW {$fx->rate_to_zmw} ({$fx->source}, from {$fx->effective_from->toDateString()}).");

        return BigDecimal::of($fx->rate_to_zmw);
    }

    private function toZmw(string $amount, BigDecimal $rate): BigDecimal
    {
        return BigDecimal::of($amount)->multipliedBy($rate)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    private function save(EstimateRun $run, EstimateStatus $status): EstimateResult
    {
        $calc = DB::transaction(function () use ($run, $status) {
            $calc = Calculation::create($run->attributes + [
                'status' => $status,
                'inputs' => $run->request->toArray(),
                'quantity' => $run->request->quantity,
                'ai_product_analysis_id' => $run->request->aiProductAnalysisId,
                'missing' => [...$run->problems, ...$run->missingInputs],
                'assumptions' => $run->assumptions,
                'calculated_on' => $run->now->toDateString(),
                'engine_version' => config('dutycalc.engine_version'),
                'expires_at' => $run->now->addDays((int) config('dutycalc.estimate_retention_days')),
            ]);

            if ($status === EstimateStatus::Estimated) {
                $calc->breakdowns()->createMany(array_map(fn (LineItem $l) => $l->toBreakdownAttributes(), $run->lineItems));
            }

            return $calc;
        });

        return EstimateResult::fromCalculation($calc->fresh());
    }
}
