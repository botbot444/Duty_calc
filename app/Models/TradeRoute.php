<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Named TradeRoute (table trade_routes) so it never clashes with the Route facade.
 */
class TradeRoute extends Model
{
    protected $fillable = [
        'origin_iso2',
        'destination_iso2',
        'border_post',
        'supported',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'supported' => 'boolean',
        ];
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'origin_iso2', 'iso2');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_iso2', 'iso2');
    }

    public function scopeSupported(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('supported'), true);
    }
}
