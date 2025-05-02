<?php

use Illuminate\Support\Facades\Route;

Route::prefix('draftsman')->group(function () {
    Route::prefix('api')->group(function () {
        // API Routes...

    });

    // Catch-all Route...
    Route::view('/{view?}', 'draftsman::layout');
    // Route::get('/{view?}', 'HomeController@index')->where('view', '(.*)')->name('horizon.index');
});
