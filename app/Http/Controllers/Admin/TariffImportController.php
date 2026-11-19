<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportPreviewRequest;
use App\Services\Admin\AuditLog;
use App\Services\TariffData\RowError;
use App\Services\TariffData\TariffCsvImporter;
use App\Services\TariffData\TariffImportException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CSV import in two steps: preview (validate everything, save nothing), then confirm.
 * Confirm re-runs the full validation, because the data may have changed in between.
 */
class TariffImportController extends Controller
{
    private const DIR = 'admin-imports';

    public function __construct(private readonly TariffCsvImporter $importer) {}

    /** POST /api/admin/imports (multipart files[]) */
    public function preview(ImportPreviewRequest $request): JsonResponse
    {
        $token = (string) Str::uuid();
        foreach ($request->file('files') as $file) {
            Storage::disk('local')->putFileAs(self::DIR.'/'.$token, $file, $file->getClientOriginalName());
        }

        try {
            $counts = $this->importer->import($this->paths($token), dryRun: true);
        } catch (TariffImportException $e) {
            Storage::disk('local')->deleteDirectory(self::DIR.'/'.$token);

            return response()->json(['valid' => false, 'errors' => $this->errors($e)], 422);
        }

        $request->session()->put("admin.imports.{$token}", true);

        return response()->json(['valid' => true, 'token' => $token, 'counts' => $counts]);
    }

    /** POST /api/admin/imports/{token}/confirm */
    public function confirm(Request $request, string $token, AuditLog $audit): JsonResponse
    {
        abort_unless(Str::isUuid($token) && $request->session()->pull("admin.imports.{$token}"), 404);

        try {
            $counts = $audit->change(function (AuditLog $audit) use ($token) {
                $counts = $this->importer->import($this->paths($token));
                $audit->record('import', 'tariff_import', null, ['files' => $counts], $token);

                return $counts;
            });
        } catch (TariffImportException $e) {
            return response()->json(['valid' => false, 'errors' => $this->errors($e)], 422);
        } finally {
            Storage::disk('local')->deleteDirectory(self::DIR.'/'.$token);
        }

        return response()->json(['imported' => true, 'counts' => $counts]);
    }

    /** @return array<string, string> */
    private function paths(string $token): array
    {
        $paths = [];
        foreach (Storage::disk('local')->files(self::DIR.'/'.$token) as $file) {
            $paths[basename($file)] = Storage::disk('local')->path($file);
        }

        return $paths;
    }

    private function errors(TariffImportException $e): array
    {
        return array_map(fn (RowError $r) => $r->toArray(), $e->errors);
    }
}
