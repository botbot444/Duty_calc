<?php

namespace App\Models;

use App\Enums\CombineMode;
use App\Enums\RuleMethod;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge in a saved estimate, with the rule's values copied in.
 */
class CalculationBreakdown extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'method' => RuleMethod::class,
            'combine' => CombineMode::class,
            'sequence' => 'integer',
            'base_components' => 'array',
            'taxable_base_zmw' => 'decimal:2',
            'rate' => 'decimal:6',
            'specific_amount' => 'decimal:4',
            'quantity' => 'decimal:4',
            'amount_zmw' => 'decimal:2',
            'rule_effective_from' => 'date',
            'rule_effective_to' => 'date',
            'source_page' => 'integer',
        ];
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }
}
