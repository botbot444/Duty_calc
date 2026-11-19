<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExchangeRateRequest;
use App\Models\ExchangeRate;
use App\Services\Admin\RateChangeService;
use Illuminate\Http\JsonResponse;

class ExchangeRateController extends Controller
{
    /** GET /api/admin/exchange-rates – newest first, with who entered each. */
    public function index(): JsonResponse
    {
        return response()->json(
            ExchangeRate::with('enteredBy:id,name')
                ->orderBy('currency')
                ->orderByDesc('effective_from')
                ->paginate(50)
                ->through(fn (ExchangeRate $r) => $r->attributesToArray() + [
                    'in_force' => $r->isInForce(),
                    'entered_by_name' => $r->enteredBy?->name,
                ])
        );
    }

    /** POST /api/admin/exchange-rates – saving ends the currency's previous rate. */
    public function store(StoreExchangeRateRequest $request, RateChangeService $rates): JsonResponse
    {
        $rate = $rates->addExchangeRate($request->validated());

        return response()->json(['created' => $rate->fresh()->attributesToArray()], 201);
    }
}
