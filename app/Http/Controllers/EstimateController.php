<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstimateRequest;
use App\Services\Estimate\EstimateService;
use Illuminate\Http\JsonResponse;

class EstimateController extends Controller
{
    /**
     * POST /api/estimates – always saves and returns a result: `estimated`, `unsupported`
     * or `incomplete`, read from the body's `status`.
     */
    public function store(StoreEstimateRequest $request, EstimateService $estimates): JsonResponse
    {
        $result = $estimates->estimate($request->toEstimateRequest());

        return response()
            ->json($result->toArray(), 201)
            ->header('Location', route('estimates.show', $result->estimateId));
    }

    /** GET /estimates/{uuid} – a saved estimate, exactly as calculated. JSON for now; a Blade page later. */
    public function show(string $estimate, EstimateService $estimates): JsonResponse
    {
        $result = $estimates->find($estimate);
        abort_if($result === null, 404, __('This estimate has expired or does not exist.'));

        return response()->json($result->toArray());
    }
}
