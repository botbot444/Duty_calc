<?php

namespace App\Models;

use App\Enums\EstimateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A saved estimate. Written once by EstimateService and never updated.
 */
class Calculation extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EstimateStatus::class,
            'inputs' => 'array',
            'missing' => 'array',
            'assumptions' => 'array',
            'exchange_rate_value' => 'decimal:8',
            'exchange_rate_effective_from' => 'date',
            'declared_value_zmw' => 'decimal:2',
            'freight_zmw' => 'decimal:2',
            'insurance_zmw' => 'decimal:2',
            'customs_value_zmw' => 'decimal:2',
            'quantity' => 'decimal:4',
            'total_zmw' => 'decimal:2',
            'tariff_effective_date' => 'date',
            'calculated_on' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(CalculationBreakdown::class)->orderBy('sequence');
    }

    public function catalogueItem(): BelongsTo
    {
        return $this->belongsTo(CatalogueItem::class);
    }

    public function tradeRoute(): BelongsTo
    {
        return $this->belongsTo(TradeRoute::class);
    }
}
