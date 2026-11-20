<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CatalogueSearchController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\IdentificationController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\SelectionController;
use App\Http\Controllers\TraderController;
use Illuminate\Support\Facades\Route;

// Trader screens (frontend). The result page is estimates/{estimate} at the end of this file.
Route::get('/', [TraderController::class, 'index'])->name('home');
Route::get('language/{locale}', [TraderController::class, 'language'])
    ->whereIn('locale', ['en', 'bem', 'sw'])
    ->name('language');

/*
 * JSON endpoints. They live here, not in routes/api.php, because the confirmation step
 * needs the session and every POST needs CSRF protection. /api/ is only part of the path;
 * bootstrap/app.php renders errors on these paths as JSON.
 */
Route::prefix('api')->name('api.')->group(function () {
    Route::get('catalogue/search', CatalogueSearchController::class)->name('catalogue.search');
    Route::post('selection', [SelectionController::class, 'store'])->name('selection.store');
    Route::get('routes', [ReferenceDataController::class, 'routes'])->name('routes');
    Route::get('currencies', [ReferenceDataController::class, 'currencies'])->name('currencies');
    Route::post('estimates', [EstimateController::class, 'store'])->name('estimates.store');

    Route::post('identify/text', [IdentificationController::class, 'text'])->name('identify.text');
    Route::post('identify/image', [IdentificationController::class, 'image'])->name('identify.image');
    Route::post('identify/clarify', [IdentificationController::class, 'clarify'])->name('identify.clarify');

    /*
     * Admin (JSON for now; plain Blade pages come in the frontend phase). Session login only,
     * admins are created with `php artisan make:admin`, and there is no sign-up route.
     * There is deliberately no route that edits a rate: rules are replaced or ended.
     */
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');

        Route::middleware('auth')->group(function () {
            Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
            Route::get('me', [Admin\AuthController::class, 'me'])->name('me');

            Route::get('tariff-sources', [Admin\TariffSourceController::class, 'index'])->name('sources.index');
            Route::post('tariff-sources', [Admin\TariffSourceController::class, 'store'])->name('sources.store');
            Route::patch('tariff-sources/{source}', [Admin\TariffSourceController::class, 'update'])->name('sources.update');
            Route::delete('tariff-sources/{source}', [Admin\TariffSourceController::class, 'destroy'])->name('sources.destroy');

            Route::get('exchange-rates', [Admin\ExchangeRateController::class, 'index'])->name('rates.index');
            Route::post('exchange-rates', [Admin\ExchangeRateController::class, 'store'])->name('rates.store');

            Route::post('imports', [Admin\TariffImportController::class, 'preview'])->name('imports.preview');
            Route::post('imports/{token}/confirm', [Admin\TariffImportController::class, 'confirm'])->name('imports.confirm');

            Route::get('tariff-lines', [Admin\TariffRuleController::class, 'lines'])->name('lines.index');
            Route::get('tariff-lines/{line}', [Admin\TariffRuleController::class, 'line'])->name('lines.show');
            Route::post('tariff-rules/{rule}/replace', [Admin\TariffRuleController::class, 'replace'])->name('rules.replace');
            Route::post('tariff-rules/{rule}/end', [Admin\TariffRuleController::class, 'end'])->name('rules.end');

            Route::get('catalogue-items', [Admin\CatalogueController::class, 'index'])->name('items.index');
            Route::post('catalogue-items', [Admin\CatalogueController::class, 'store'])->name('items.store');
            Route::patch('catalogue-items/{item}', [Admin\CatalogueController::class, 'update'])->name('items.update');
            Route::post('catalogue-items/{item}/aliases', [Admin\CatalogueController::class, 'storeAlias'])->name('aliases.store');
            Route::delete('catalogue-items/{item}/aliases/{alias}', [Admin\CatalogueController::class, 'destroyAlias'])->name('aliases.destroy');

            Route::get('trade-routes', [Admin\TradeRouteController::class, 'index'])->name('routes.index');
            Route::post('trade-routes/{tradeRoute}/toggle', [Admin\TradeRouteController::class, 'toggle'])->name('routes.toggle');

            Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        });
    });
});

// A saved estimate: the Blade result page in a browser, JSON for a JSON request.
Route::get('estimates/{estimate}', [EstimateController::class, 'show'])
    ->whereUuid('estimate')
    ->name('estimates.show');
