<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProductAnalysis extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_attributes' => 'array',
            'candidates_sent' => 'array',
            'ranked' => 'array',
            'clarifications' => 'array',
            'dropped_ids' => 'array',
            'rounds' => 'integer',
            'manual_review' => 'boolean',
        ];
    }

    public function confirmedItem(): BelongsTo
    {
        return $this->belongsTo(CatalogueItem::class, 'confirmed_catalogue_item_id');
    }
}
