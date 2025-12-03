<?php

use Draftsman\Draftsman\Http\Controllers\ApiV1\ModelsController;
use Draftsman\Draftsman\Http\Controllers\ApiV1\RelationsController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('draftsman')->group(function () {
    Route::prefix('_next')->group(function ($request) {
        $uri = Request::getRequestUri();
        $prefix = '/draftsman/_next/';
        if (substr($uri, 0, strlen($prefix)) === $prefix) {
            $file = '/../resources/views/_next/'.substr($uri, strlen($prefix), strlen($uri));
            $file = __DIR__.implode(DIRECTORY_SEPARATOR, explode('/', $file));
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $mime = mime_content_type($file);
                if ($mime === 'text/plain') {
                    switch ($ext) {
                        case 'css':
                            $mime = 'text/css';
                            break;
                        case 'html':
                            $mime = 'text/html';
                            break;
                        case 'js':
                            $mime = 'application/javascript';
                            break;
                        case 'json':
                            $mime = 'application/json';
                            break;
                    }
                }
                response($content)->header('Content-Type', $mime)->send();
            }
        }
    });
    Route::prefix('api')->group(function () {
        // API Routes...
        Route::get('models/presorted', [ModelsController::class, 'presorted']);
        Route::apiResource('models', ModelsController::class);
        Route::apiResource('relations', RelationsController::class);
    });

    // Catch-all Route...
    Route::view('/{view?}', 'draftsman::index')->name('draftsman.index');
});
