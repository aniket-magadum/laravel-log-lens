<?php

use AniketMagadum\LogLens\Http\Controllers\LogLensController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('log-lens.route_prefix', 'log-lens'))
    ->middleware(config('log-lens.middleware', ['web']))
    ->group(function () {
        Route::get('/', LogLensController::class.'@index')->name('log-lens.index');
    });
