<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(config('dynamic-workflows.route.middleware', ['web']))
    ->group(function () {
        Route::get(
            config('dynamic-workflows.route.prefix', 'dynamic-workflows'),
            fn () => view('dynamic-workflows::page')
        )->name(config('dynamic-workflows.route.name', 'dynamic-workflows.index'));
    });
