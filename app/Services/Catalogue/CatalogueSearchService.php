<?php

namespace App\Services\Catalogue;

use App\Enums\Language;
use App\Models\CatalogueItem;
use Illuminate\Support\Facades\DB;

/**
 * Finds catalogue items from what a trader types, with no AI involved, so the text route
 * always works. Three passes over the data, merged by catalogue item:
 *
 *   exact alias       "tv" = "tv"                                     score 100
 *   FULLTEXT alias    natural language mode, words of 3+ letters       score 50 + relevance
 *   alias prefix      "char" -> "charger", "tv" -> "tv stand"          score 30
 *   name/description  LIKE '%words%' on display name and official text score 10
 *
 * InnoDB FULLTEXT ignores words shorter than innodb_ft_min_token_size (3), so the exact and
 * prefix passes cover short aliases such as "tv". An alias in the trader's chosen language
 * gets a small boost, but other languages are still searched: traders mix languages.
 *
 * Only active items whose tariff line is valid today are returned. Nothing is auto-selected:
 * the trader confirms one of these results.
 */
class CatalogueSearchService
{
    public const LIMIT = 10;

    private const LANGUAGE_BOOST = 5;

    private const FULLTEXT_MIN_WORD = 3;

    /**
     * @return list<array{id: string, name: string, hs_code: string, official_description: string,
     *     stat_unit: string, matched_alias: ?string, matched_language: ?string, match_type: string}>
     */
    public function search(string $query, ?Language $language = null, int $limit = self::LIMIT): array
    {
        $query = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query)));
        if ($query === '') {
            return [];
        }

        /** @var array<string, array{score: float, alias: ?string, language: ?string, type: string}> $hits */
        $hits = [];
        $keep = function (string $itemId, float $score, string $type, ?string $alias, ?string $lang) use (&$hits, $language) {
            if ($language !== null && $lang === $language->value) {
                $score += self::LANGUAGE_BOOST;
            }
            if (! isset($hits[$itemId]) || $score > $hits[$itemId]['score']) {
                $hits[$itemId] = ['score' => $score, 'alias' => $alias, 'language' => $lang, 'type' => $type];
            }
        };

        $like = $this->escapeLike($query);

        // Exact and prefix alias matches (the collation is case-insensitive).
        $prefixRows = DB::table('product_aliases')
            ->where(fn ($q) => $q->where('alias', $query)
                ->orWhere('alias', 'like', "{$like}%")
                ->orWhere('alias', 'like', "% {$like}%"))
            ->get(['catalogue_item_id', 'alias', 'language']);
        foreach ($prefixRows as $row) {
            $exact = mb_strtolower($row->alias) === $query;
            $keep($row->catalogue_item_id, $exact ? 100 : 30, $exact ? 'exact' : 'prefix', $row->alias, $row->language);
        }

        // FULLTEXT, when at least one word is long enough for InnoDB to index.
        if ($this->hasFullTextWord($query)) {
            $textRows = DB::table('product_aliases')
                ->select('catalogue_item_id', 'alias', 'language')
                ->selectRaw('MATCH(alias) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance', [$query])
                ->whereRaw('MATCH(alias) AGAINST (? IN NATURAL LANGUAGE MODE)', [$query])
                ->get();
            foreach ($textRows as $row) {
                $keep($row->catalogue_item_id, 50 + min((float) $row->relevance, 40), 'text', $row->alias, $row->language);
            }
        }

        // Display names and official descriptions.
        if (mb_strlen($query) >= self::FULLTEXT_MIN_WORD) {
            $nameRows = DB::table('catalogue_items')
                ->join('tariff_lines', 'tariff_lines.id', '=', 'catalogue_items.tariff_line_id')
                ->where(fn ($q) => $q->where('catalogue_items.display_name', 'like', "%{$like}%")
                    ->orWhere('tariff_lines.official_description', 'like', "%{$like}%"))
                ->pluck('catalogue_items.id');
            foreach ($nameRows as $itemId) {
                $keep($itemId, 10, 'description', null, null);
            }
        }

        if ($hits === []) {
            return [];
        }

        $items = CatalogueItem::active()
            ->whereIn('id', array_keys($hits))
            ->whereHas('tariffLine', fn ($q) => $q->inForce())
            ->with('tariffLine')
            ->get()
            ->sortBy([
                fn ($a, $b) => $hits[$b->id]['score'] <=> $hits[$a->id]['score'],
                fn ($a, $b) => strcmp($a->display_name, $b->display_name),
            ])
            ->take($limit);

        return $items->map(fn (CatalogueItem $item) => [
            'id' => $item->id,
            'name' => $item->display_name,
            'hs_code' => $item->tariffLine->hs_code,
            'official_description' => $item->tariffLine->official_description,
            'stat_unit' => $item->tariffLine->stat_unit->value,
            'matched_alias' => $hits[$item->id]['alias'],
            'matched_language' => $hits[$item->id]['language'],
            'match_type' => $hits[$item->id]['type'],
        ])->values()->all();
    }

    private function hasFullTextWord(string $query): bool
    {
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (mb_strlen($word) >= self::FULLTEXT_MIN_WORD) {
                return true;
            }
        }

        return false;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
