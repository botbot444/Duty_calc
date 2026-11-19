<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What traders pick. The string ID (e.g. CAT-0012) is the only ID the UI and AI pass around.
 */
class CatalogueItem extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tariff_line_id',
        'display_name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function tariffLine(): BelongsTo
    {
        return $this->belongsTo(TariffLine::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('active'), true);
    }
}
