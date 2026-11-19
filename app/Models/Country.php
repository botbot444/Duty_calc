<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $primaryKey = 'iso2';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'iso2',
        'name',
        'is_origin',
        'is_destination',
    ];

    protected function casts(): array
    {
        return [
            'is_origin' => 'boolean',
            'is_destination' => 'boolean',
        ];
    }

    public function outboundRoutes(): HasMany
    {
        return $this->hasMany(TradeRoute::class, 'origin_iso2', 'iso2');
    }

    public function inboundRoutes(): HasMany
    {
        return $this->hasMany(TradeRoute::class, 'destination_iso2', 'iso2');
    }
}
