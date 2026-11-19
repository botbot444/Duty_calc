<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TariffSourceRequest;
use App\Models\TariffSource;
use App\Services\Admin\AuditLog;
use App\Services\TariffData\RowError;
use App\Services\TariffData\TariffDataValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Where rates come from. A source's details can be corrected (estimates copy the source name
 * they used), but a source still used by a tariff line or rule cannot be deleted.
 */
class TariffSourceController extends Controller
{
    public function __construct(private readonly AuditLog $audit) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'sources' => TariffSource::withCount(['tariffLines', 'tariffRules'])->orderBy('name')->get(),
        ]);
    }

    public function store(TariffSourceRequest $request, TariffDataValidator $validator): JsonResponse
    {
        $row = array_merge(array_fill_keys(['name', 'document_url', 'edition', 'published_on', 'retrieved_on', 'verified_by'], null), $request->validated());
        $result = $validator->sources([['row' => 1, 'data' => $row]], 'admin');
        if (! $result->passes()) {
            throw ValidationException::withMessages(['name' => array_map(fn (RowError $e) => $e->message, $result->errors)]);
        }

        $source = $this->audit->change(function (AuditLog $audit) use ($result) {
            $source = TariffSource::create($result->records[0]);
            $audit->record('create', $source, null, AuditLog::snapshot($source));

            return $source;
        });

        return response()->json(['source' => $source->fresh()], 201);
    }

    public function update(TariffSourceRequest $request, TariffSource $source): JsonResponse
    {
        $this->audit->change(function (AuditLog $audit) use ($request, $source) {
            $before = AuditLog::snapshot($source);
            $source->update($request->validated());
            $audit->record('update', $source, $before, AuditLog::snapshot($source));
        });

        return response()->json(['source' => $source->fresh()]);
    }

    public function destroy(TariffSource $source): JsonResponse
    {
        if ($source->isInUse()) {
            return response()->json([
                'message' => 'This source is used by tariff lines or rules and cannot be deleted.',
                'tariff_lines' => $source->tariffLines()->count(),
                'tariff_rules' => $source->tariffRules()->count(),
            ], 409);
        }

        $this->audit->change(function (AuditLog $audit) use ($source) {
            $before = AuditLog::snapshot($source);
            $source->delete();
            $audit->record('delete', 'tariff_sources', $before, null, (string) $before['id']);
        });

        return response()->json(['deleted' => true]);
    }
}
