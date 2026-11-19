<?php

namespace App\Services\Estimate;

use App\Models\CatalogueItem;
use App\Models\TariffLine;
use Carbon\CarbonImmutable;

/**
 * Working state for one EstimateService::estimate() call: the problems, missing inputs
 * and assumptions found so far, and the calculation columns collected along the way.
 *
 * @internal used only by EstimateService
 */
final class EstimateRun
{
    /** @var array<string, mixed> calculations columns */
    public array $attributes = [];

    /** @var list<array{item: string, message: string}> data gaps on our side -> unsupported */
    public array $problems = [];

    /** @var list<array{item: string, message: string}> inputs the trader left out -> incomplete */
    public array $missingInputs = [];

    /** @var list<string> */
    public array $assumptions = [];

    /** @var list<LineItem> */
    public array $lineItems = [];

    public function __construct(
        public readonly EstimateRequest $request,
        public readonly CarbonImmutable $now,
    ) {}

    public function recordItem(CatalogueItem $item, TariffLine $line): void
    {
        $this->attributes += [
            'catalogue_item_id' => $item->id,
            'catalogue_item_name' => $item->display_name,
            'tariff_line_id' => $line->id,
            'hs_code' => $line->hs_code,
            'official_description' => $line->official_description,
            'stat_unit' => $line->stat_unit->value,
            'quantity_unit' => $line->stat_unit->value,
        ];
    }

    public function unsupported(string $item, string $message): void
    {
        $this->problems[] = ['item' => $item, 'message' => $message];
    }

    public function missingInput(string $item, string $message): void
    {
        $this->missingInputs[] = ['item' => $item, 'message' => $message];
    }

    public function assume(string $assumption): void
    {
        $this->assumptions[] = $assumption;
    }

    public function hasProblems(): bool
    {
        return $this->problems !== [];
    }

    public function hasMissingInputs(): bool
    {
        return $this->missingInputs !== [];
    }
}
