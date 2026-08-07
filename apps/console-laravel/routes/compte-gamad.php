<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CompteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['gamad.https', 'gamad.api'])
    ->group(function (): void {
        Route::post('/comptes', [CompteController::class, 'store'])
            ->middleware('throttle:10,1');
    });
