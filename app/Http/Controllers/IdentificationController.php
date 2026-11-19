<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClarifyRequest;
use App\Http\Requests\IdentifyImageRequest;
use App\Http\Requests\IdentifyTextRequest;
use App\Services\Identification\ProductIdentificationService;
use Illuminate\Http\JsonResponse;

/**
 * Suggestions only. None of these selects an item: the trader confirms one through
 * POST /api/selection, the same step the plain search route uses.
 */
class IdentificationController extends Controller
{
    public function __construct(private readonly ProductIdentificationService $identification) {}

    /** POST /api/identify/text */
    public function text(IdentifyTextRequest $request): JsonResponse
    {
        return response()->json(
            $this->identification->identifyText($request->validated('description'), $request->language())->toArray()
        );
    }

    /** POST /api/identify/image (multipart: photo, optional description and lang) */
    public function image(IdentifyImageRequest $request): JsonResponse
    {
        return response()->json(
            $this->identification->identifyImage($request->file('photo'), $request->validated('description'), $request->language())->toArray()
        );
    }

    /** POST /api/identify/clarify */
    public function clarify(ClarifyRequest $request): JsonResponse
    {
        return response()->json(
            $this->identification->clarify((int) $request->validated('analysis_id'), $request->answers())->toArray()
        );
    }
}
