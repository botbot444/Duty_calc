<?php

namespace App\Services\Catalogue;

use App\Models\CatalogueItem;
use Illuminate\Contracts\Session\Session;

/**
 * The one selection step shared by the text and photo routes: the catalogue item the
 * trader confirmed, kept in the session. POST /api/estimates only accepts this item.
 *
 * Nothing is ever selected automatically, not even a "High" AI match: only an explicit
 * POST /api/selection from the trader calls confirm().
 */
class ProductSelection
{
    private const KEY = 'dutycalc.selection';

    public function __construct(private readonly Session $session) {}

    public function confirm(CatalogueItem $item, ?int $aiProductAnalysisId = null): void
    {
        $this->session->put(self::KEY, [
            'catalogue_item_id' => $item->id,
            'ai_product_analysis_id' => $aiProductAnalysisId,
            'confirmed_at' => now()->toIso8601String(),
        ]);
    }

    public function confirmedItemId(): ?string
    {
        return $this->session->get(self::KEY.'.catalogue_item_id');
    }

    public function isConfirmed(string $catalogueItemId): bool
    {
        return $this->confirmedItemId() === $catalogueItemId;
    }

    public function aiProductAnalysisId(): ?int
    {
        return $this->session->get(self::KEY.'.ai_product_analysis_id');
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }
}
