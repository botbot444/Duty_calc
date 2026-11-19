<?php

namespace App\Http\Controllers;

use App\Models\TradeRoute;
use App\Services\Estimate\SupportedCurrencies;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    /** GET /api/routes – supported origins, destination and border posts. */
    public function routes(): JsonResponse
    {
        $routes = TradeRoute::supported()
            ->with('origin', 'destination')
            ->orderBy('origin_iso2')
            ->orderBy('border_post')
            ->get()
            ->map(fn (TradeRoute $r) => [
                'id' => $r->id,
                'origin' => ['iso2' => $r->origin_iso2, 'name' => $r->origin->name],
                'destination' => ['iso2' => $r->destination_iso2, 'name' => $r->destination->name],
                'border_post' => $r->border_post,
            ]);

        return response()->json(['routes' => $routes]);
    }

    /** GET /api/currencies – ZMW plus currencies with a rate in force today. */
    public function currencies(SupportedCurrencies $currencies): JsonResponse
    {
        return response()->json(['currencies' => $currencies->all()]);
    }
}
