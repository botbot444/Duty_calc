<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TariffSource extends Model
{
    protected $fillable = [
        'name',
        'document_url',
        'edition',
        'published_on',
        'retrieved_on',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'retrieved_on' => 'date',
        ];
    }

    public function tariffLines(): HasMany
    {
        return $this->hasMany(TariffLine::class);
    }

    public function tariffRules(): HasMany
    {
        return $this->hasMany(TariffRule::class);
    }

    /** A source still referenced by a line or rule cannot be deleted. */
    public function isInUse(): bool
    {
        return $this->tariffLines()->exists() || $this->tariffRules()->exists();
    }
}
