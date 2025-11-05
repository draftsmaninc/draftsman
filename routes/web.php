<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ModelsController;
use Draftsman\Draftsman\Http\Controllers\ApiV1\RelationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('draftsman')->group(function () {
    Route::prefix('api')->group(function () {
        // API Routes...
        Route::get('models/presorted', [ModelsController::class, 'presorted']);
        Route::apiResource('models', ModelsController::class);
        Route::apiResource('relations', RelationsController::class);
    });

    // Catch-all Route...
    Route::view('/{view?}', 'draftsman::layout')->name('draftsman.index');
});
