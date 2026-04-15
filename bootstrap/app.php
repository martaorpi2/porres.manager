<?php

use App\Http\Controllers\Admin\MarketRateCrudController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            $prefix = trim((string) config('backpack.base.route_prefix', 'admin'), '/');
            Route::middleware(['web', 'admin'])->group(function () use ($prefix) {
                Route::get("{$prefix}/market-rate/{id}/uploaded-file/{index?}", [MarketRateCrudController::class, 'showUploadedFile'])
                    ->name('market-rate.uploaded-file');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
