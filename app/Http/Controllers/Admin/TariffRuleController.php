<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndRuleRequest;
use App\Http\Requests\Admin\ReplaceRuleRequest;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Services\Admin\RateChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tariff lines and their rules. There is deliberately no update route for a rule: a rate
 * is changed by "replace" (end + new row) or "end", never edited in place.
 */
class TariffRuleController extends Controller
{
    public function __construct(private readonly RateChangeService $rates) {}

    /** GET /api/admin/tariff-lines?hs= */
    public function lines(Request $request): JsonResponse
    {
        $lines = TariffLine::with(['source', 'rules.source', 'catalogueItems'])
            ->when($request->query('hs'), fn ($q, $hs) => $q->where('hs_code', 'like', preg_replace('/\D/', '', (string) $hs).'%'))
            ->orderBy('hs_code')
            ->orderByDesc('valid_from')
            ->paginate(50);

        $lines->getCollection()->transform(fn (TariffLine $line) => $this->line($line));

        return response()->json($lines);
    }

    /** GET /api/admin/tariff-lines/{line} */
    public function line(TariffLine $line): array
    {
        $line->loadMissing(['source', 'rules.source', 'catalogueItems']);

        return [
            'id' => $line->id,
            'hs_code' => $line->hs_code,
            'official_description' => $line->official_description,
            'stat_unit' => $line->stat_unit->value,
            'valid_from' => $line->valid_from->toDateString(),
            'valid_to' => $line->valid_to?->toDateString(),
            'in_force' => $line->isInForce(),
            'source' => ['id' => $line->tariff_source_id, 'name' => $line->source->name, 'page' => $line->source_page],
            'catalogue_items' => $line->catalogueItems->map->only(['id', 'display_name', 'active'])->values(),
            'rules' => $line->rules
                ->sortBy([
                    fn (TariffRule $a, TariffRule $b) => $a->sequence <=> $b->sequence,
                    fn (TariffRule $a, TariffRule $b) => $a->effective_from <=> $b->effective_from,
                ])
                ->map(fn (TariffRule $r) => $this->rule($r))->values(),
        ];
    }

    /** POST /api/admin/tariff-rules/{rule}/replace */
    public function replace(ReplaceRuleRequest $request, TariffRule $rule): JsonResponse
    {
        $new = $this->rates->replaceRule($rule, $request->validated());

        return response()->json(['ended' => $this->rule($rule->fresh()), 'created' => $this->rule($new->fresh())], 201);
    }

    /** POST /api/admin/tariff-rules/{rule}/end */
    public function end(EndRuleRequest $request, TariffRule $rule): JsonResponse
    {
        return response()->json(['ended' => $this->rule($this->rates->endRule($rule, $request->validated('effective_to'))->fresh())]);
    }

    private function rule(TariffRule $r): array
    {
        $r->loadMissing('source');

        return [
            'id' => $r->id,
            'tax_type' => $r->tax_type->value,
            'sequence' => $r->sequence,
            'method' => $r->method->value,
            'rate' => $r->rate,
            'specific_amount' => $r->specific_amount,
            'specific_currency' => $r->specific_currency,
            'combine' => $r->combine?->value,
            'base_components' => $r->base_components,
            'origin_country' => $r->origin_country,
            'effective_from' => $r->effective_from->toDateString(),
            'effective_to' => $r->effective_to?->toDateString(),
            'in_force' => $r->isInForce(),
            'source' => ['id' => $r->tariff_source_id, 'name' => $r->source->name, 'page' => $r->source_page],
        ];
    }
}
