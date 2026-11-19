<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradeRoute;
use App\Services\Admin\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeRouteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'routes' => TradeRoute::with('origin', 'destination')->orderBy('origin_iso2')->orderBy('border_post')->get(),
        ]);
    }

    /** POST /api/admin/trade-routes/{tradeRoute}/toggle {supported: bool} */
    public function toggle(Request $request, TradeRoute $tradeRoute, AuditLog $audit): JsonResponse
    {
        $supported = $request->validate(['supported' => ['required', 'boolean']])['supported'];

        $audit->change(function (AuditLog $audit) use ($tradeRoute, $supported) {
            $before = AuditLog::snapshot($tradeRoute);
            $tradeRoute->update(['supported' => (bool) $supported]);
            $audit->record('toggle', $tradeRoute, $before, AuditLog::snapshot($tradeRoute));
        });

        return response()->json(['route' => $tradeRoute->fresh()]);
    }
}
