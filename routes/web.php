<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Draftsman\Draftsman\Http\Controllers\ApiV1\GraphsController;
use Draftsman\Draftsman\Http\Controllers\ApiV1\ModelsController;
use Draftsman\Draftsman\Http\Controllers\ApiV1\RelationsController;
use Draftsman\Draftsman\Http\Controllers\DraftsmanController;
use Illuminate\Support\Facades\Route;

Route::prefix('draftsman')->group(function () {
    Route::get('', [DraftsmanController::class, 'index'])->name('draftsman.index');

    Route::prefix('api')->group(function () {
        Route::get('models/presorted', [ModelsController::class, 'presorted']);
        Route::get('models/changed', [ApiController::class, 'getModelChanges']);
        Route::apiResource('models', ModelsController::class);
        Route::apiResource('relations', RelationsController::class);
        Route::get('config', [ApiController::class, 'getConfig']);
        Route::post('config', [ApiController::class, 'updateConfig']);
        Route::get('graphs', [GraphsController::class, 'index']);
        Route::get('graphs/{slug}', [GraphsController::class, 'show']);
        Route::put('graphs/{slug}', [GraphsController::class, 'store']);
        Route::delete('graphs/{slug}', [GraphsController::class, 'destroy']);
        Route::get('render/{slug}', [GraphsController::class, 'render'])->name('draftsman.render');
    });

    Route::get(
        '/{slug0?}/{slug1?}/{slug2?}/{slug3?}/{slug4?}/{slug5?}/{slug6?}/{slug7?}/{slug8?}/{slug9?}',
        [DraftsmanController::class, 'front']
    )->name('draftsman.front');
});
