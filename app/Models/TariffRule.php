<?php

namespace App\Models;

use App\Enums\CombineMode;
use App\Enums\RuleMethod;
use App\Enums\TaxType;
use App\Models\Concerns\HasEffectiveDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One charge on one tariff line. Never edited in place once used:
 * a change sets effective_to on this row and adds a new one.
 *
 * rate and specific_amount are cast to fixed-scale strings (never floats);
 * wrap them in Brick\Math\BigDecimal before doing any maths.
 */
class TariffRule extends Model
{
    use HasEffectiveDates;

    protected $fillable = [
        'tariff_line_id',
        'tax_type',
        'sequence',
        'method',
        'rate',
        'specific_amount',
        'specific_currency',
        'combine',
        'base_components',
        'origin_country',
        'effective_from',
        'effective_to',
        'tariff_source_id',
        'source_page',
    ];

    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'method' => RuleMethod::class,
            'combine' => CombineMode::class,
            'sequence' => 'integer',
            'rate' => 'decimal:6',
            'specific_amount' => 'decimal:4',
            'base_components' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'source_page' => 'integer',
        ];
    }

    public function tariffLine(): BelongsTo
    {
        return $this->belongsTo(TariffLine::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TariffSource::class, 'tariff_source_id');
    }

    /** Rules that need a quantity from the trader. */
    public function needsQuantity(): bool
    {
        return $this->method !== RuleMethod::AdValorem;
    }

    /**
     * Standard-rate rules plus any rule for this origin. Trade preferences are out
     * of scope for the MVP, so callers normally pass null and get standard rates only.
     */
    public function scopeForOrigin(Builder $query, ?string $originIso2): Builder
    {
        $column = $query->qualifyColumn('origin_country');

        return $query->where(function (Builder $q) use ($column, $originIso2) {
            $q->whereNull($column);
            if ($originIso2 !== null) {
                $q->orWhere($column, $originIso2);
            }
        });
    }
}
