<?php

use DraftsmanInc\Draftsman\Http\Controllers\ModelsController;
use DraftsmanInc\Draftsman\Http\Controllers\RelationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // API Routes...
    Route::apiResource('models', ModelsController::class);
    Route::apiResource('relations', RelationsController::class);
});

// Catch-all Route...
Route::view('/{view?}', 'draftsman::layout');
// Route::get('/{view?}', 'HomeController@index')->where('view', '(.*)')->name('horizon.index');
