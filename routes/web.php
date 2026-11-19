<?php

use App\Http\Controllers\CatalogueSearchController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\IdentificationController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\SelectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
});

// A saved estimate. JSON for now; the Blade result page replaces it in the frontend phase.
Route::get('estimates/{estimate}', [EstimateController::class, 'show'])
    ->whereUuid('estimate')
    ->name('estimates.show');
