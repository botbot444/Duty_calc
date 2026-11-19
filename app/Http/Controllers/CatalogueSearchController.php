<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogueSearchRequest;
use App\Services\Catalogue\CatalogueSearchService;
use Illuminate\Http\JsonResponse;

class CatalogueSearchController extends Controller
{
    /** GET /api/catalogue/search?q=&lang= */
    public function __invoke(CatalogueSearchRequest $request, CatalogueSearchService $search): JsonResponse
    {
        $results = $search->search($request->validated('q'), $request->language());

        return response()->json([
            'query' => $request->validated('q'),
            'language' => $request->language()?->value,
            'count' => count($results),
            'results' => $results,
        ]);
    }
}
