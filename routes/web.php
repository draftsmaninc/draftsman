<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // API Routes...

});

// Catch-all Route...
Route::view('/{view?}', 'layout');
// Route::get('/{view?}', 'HomeController@index')->where('view', '(.*)')->name('horizon.index');
