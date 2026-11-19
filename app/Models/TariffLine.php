<?php

namespace App\Models;

use App\Enums\StatUnit;
use App\Models\Concerns\HasEffectiveDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TariffLine extends Model
{
    use HasEffectiveDates;

    public const EFFECTIVE_FROM_COLUMN = 'valid_from';

    public const EFFECTIVE_TO_COLUMN = 'valid_to';

    protected $fillable = [
        'hs_code',
        'official_description',
        'stat_unit',
        'valid_from',
        'valid_to',
        'tariff_source_id',
        'source_page',
    ];

    protected function casts(): array
    {
        return [
            'stat_unit' => StatUnit::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'source_page' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TariffSource::class, 'tariff_source_id');
    }

    public function catalogueItems(): HasMany
    {
        return $this->hasMany(CatalogueItem::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TariffRule::class);
    }
}
