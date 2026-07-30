<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AutorisationController;
use App\Http\Controllers\Api\V1\FondationController;
use App\Http\Controllers\Api\V1\IdentiteController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Ctr01Controller;
use App\Http\Controllers\Ctr02Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('gamad.https')->group(function (): void {
    Route::get('/health/live', [FondationController::class, 'live']);
    Route::get('/health/ready', [FondationController::class, 'ready']);

    Route::post('/sessions', [SessionController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::middleware('gamad.api')->group(function (): void {
        Route::delete('/sessions/current', [SessionController::class, 'destroy']);

        Route::get('/identites', [Ctr01Controller::class, 'resoudreInventaire']);
        Route::get('/identites/{reference}', [Ctr01Controller::class, 'resoudreIdentite']);
        Route::get('/identites/{reference}/regime', [Ctr01Controller::class, 'resoudreRegime']);
        Route::get('/identites/{reference}/assurance', [Ctr01Controller::class, 'resoudreAssurance']);
        Route::post('/identites', [IdentiteController::class, 'store'])
            ->middleware('throttle:20,1');

        Route::get('/mandats/{fonction}', [Ctr02Controller::class, 'resoudreMandat']);
        Route::post('/autorisation/decisions', [AutorisationController::class, 'store'])
            ->middleware('throttle:60,1');
    });
});
