<?php

use App\Http\Controllers\CatalogueSearchController;
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
});
