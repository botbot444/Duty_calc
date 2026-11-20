<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstimateRequest;
use App\Services\Estimate\EstimateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    /**
     * GET /estimates/{uuid} – a saved estimate, exactly as calculated: the result page in a
     * browser, JSON when the request asks for JSON.
     */
    public function show(Request $request, string $estimate, EstimateService $estimates): JsonResponse|Response
    {
        $result = $estimates->find($estimate);

        if ($request->expectsJson()) {
            abort_if($result === null, 404, __('This estimate has expired or does not exist.'));

            return response()->json($result->toArray());
        }

        if ($result === null) {
            return response()->view('estimate-missing', [], 404);
        }

        return response()->view('estimate', ['e' => $result->toArray()]);
    }
}
