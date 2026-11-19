<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AliasRequest;
use App\Http\Requests\Admin\CatalogueItemRequest;
use App\Models\CatalogueItem;
use App\Models\ProductAlias;
use App\Services\Admin\AuditLog;
use App\Services\TariffData\RowError;
use App\Services\TariffData\TariffDataValidator;
use App\Services\TariffData\ValidationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Catalogue items and their search aliases. New items and aliases go through the same
 * TariffDataValidator checks as the CSV import. Items are switched off, never deleted,
 * because saved estimates point to them.
 */
class CatalogueController extends Controller
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly TariffDataValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            CatalogueItem::with(['tariffLine:id,hs_code,official_description,stat_unit', 'aliases'])
                ->when($request->query('q'), fn ($q, $term) => $q->where('display_name', 'like', '%'.addcslashes((string) $term, '%_\\').'%'))
                ->orderBy('id')
                ->paginate(50)
        );
    }

    public function store(CatalogueItemRequest $request): JsonResponse
    {
        $v = $request->validated();
        $result = $this->validator->catalogueItems([['row' => 1, 'data' => [
            'id' => $v['id'], 'hs_code' => $v['hs_code'], 'display_name' => $v['display_name'],
            'active' => ($v['active'] ?? true) ? '1' : '0',
        ]]], 'admin');
        $this->passOrFail($result);

        $item = $this->audit->change(function (AuditLog $audit) use ($result) {
            $item = CatalogueItem::create($result->records[0]);
            $audit->record('create', $item, null, AuditLog::snapshot($item));

            return $item;
        });

        return response()->json(['item' => $item->fresh('tariffLine')], 201);
    }

    /** PATCH: rename, or switch on/off (logged as a toggle). */
    public function update(CatalogueItemRequest $request, CatalogueItem $item): JsonResponse
    {
        $changes = $request->validated();
        $action = array_keys($changes) === ['active'] ? 'toggle' : 'update';

        $this->audit->change(function (AuditLog $audit) use ($item, $changes, $action) {
            $before = AuditLog::snapshot($item);
            $item->update($changes);
            $audit->record($action, $item, $before, AuditLog::snapshot($item));
        });

        return response()->json(['item' => $item->fresh('tariffLine')]);
    }

    public function storeAlias(AliasRequest $request, CatalogueItem $item): JsonResponse
    {
        $result = $this->validator->productAliases([['row' => 1, 'data' => [
            'catalogue_item_id' => $item->id,
            'alias' => trim($request->validated('alias')),
            'language' => $request->validated('language'),
        ]]], 'admin');
        $this->passOrFail($result);

        $alias = $this->audit->change(function (AuditLog $audit) use ($result) {
            $alias = ProductAlias::create($result->records[0]);
            $audit->record('create', $alias, null, AuditLog::snapshot($alias));

            return $alias;
        });

        return response()->json(['alias' => $alias], 201);
    }

    public function destroyAlias(CatalogueItem $item, ProductAlias $alias): JsonResponse
    {
        abort_unless($alias->catalogue_item_id === $item->id, 404);

        $this->audit->change(function (AuditLog $audit) use ($alias) {
            $before = AuditLog::snapshot($alias);
            $alias->delete();
            $audit->record('delete', 'product_aliases', $before, null, (string) $before['id']);
        });

        return response()->json(['deleted' => true]);
    }

    private function passOrFail(ValidationResult $result): void
    {
        if (! $result->passes()) {
            throw ValidationException::withMessages(['row' => array_map(fn (RowError $e) => $e->message, $result->errors)]);
        }
    }
}
