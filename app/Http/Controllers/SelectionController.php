<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectProductRequest;
use App\Services\Catalogue\ProductSelection;
use App\Services\Estimate\EstimateService;
use Illuminate\Http\JsonResponse;

class SelectionController extends Controller
{
    /**
     * POST /api/selection – the trader confirms one catalogue item. Returns the item and the
     * inputs its rules need, so the trade-details form shows only those fields.
     */
    public function store(SelectProductRequest $request, ProductSelection $selection, EstimateService $estimates): JsonResponse
    {
        $item = $request->item();
        $selection->confirm($item);

        $required = [];
        foreach ($estimates->requiredInputs($item) as $field => $unit) {
            $required[$field] = ['unit' => $unit, 'unit_label' => $item->tariffLine->stat_unit->label()];
        }

        return response()->json([
            'selected' => [
                'id' => $item->id,
                'name' => $item->display_name,
                'hs_code' => $item->tariffLine->hs_code,
                'official_description' => $item->tariffLine->official_description,
                'stat_unit' => $item->tariffLine->stat_unit->value,
            ],
            'required_inputs' => (object) $required,
            'optional_inputs' => ['freight', 'insurance'],
        ]);
    }
}
