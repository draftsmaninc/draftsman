<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ModelsController;
use Draftsman\Draftsman\Http\Controllers\ApiV1\RelationsController;
use Draftsman\Draftsman\Http\Controllers\ApiV1\ApiController;
use Draftsman\Draftsman\Http\Controllers\DraftsmanController;
use Illuminate\Support\Facades\Route;

Route::prefix('draftsman')->group(function () {
    Route::get('', [DraftsmanController::class, 'index'])->name('draftsman.index');

    Route::prefix('_next')->group(function ($request) {
        Route::get(
            '/{slug0?}/{slug1?}/{slug2?}/{slug3?}/{slug4?}/{slug5?}/{slug6?}/{slug7?}/{slug8?}/{slug9?}',
            [DraftsmanController::class, 'next']
        )->name('draftsman.next');
    });
    Route::prefix('api')->group(function () {
        Route::get('models/presorted', [ModelsController::class, 'presorted']);
        Route::apiResource('models', ModelsController::class);
        Route::apiResource('relations', RelationsController::class);
        Route::get('config', [ApiController::class, 'getConfig']);
        Route::post('config', [ApiController::class, 'updateConfig']);
    });
});
