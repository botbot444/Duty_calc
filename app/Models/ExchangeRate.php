<?php

namespace App\Models;

use App\Models\Concerns\HasEffectiveDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 unit of `currency` = `rate_to_zmw` ZMW. ZMW itself has no row (rate 1, no conversion).
 * Saving a new rate ends the previous one (effective_to) rather than editing it.
 */
class ExchangeRate extends Model
{
    use HasEffectiveDates;

    public const BASE_CURRENCY = 'ZMW';

    protected $fillable = [
        'currency',
        'rate_to_zmw',
        'effective_from',
        'effective_to',
        'source',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_to_zmw' => 'decimal:8',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
