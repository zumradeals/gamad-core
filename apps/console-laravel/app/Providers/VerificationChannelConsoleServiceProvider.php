<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\VerificationChannelConsoleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class VerificationChannelConsoleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'gamad.session'])
            ->group(function (): void {
                Route::get('/parametres/verification-comptes', [VerificationChannelConsoleController::class, 'index'])
                    ->name('console.parametres.verification.index');
                Route::post('/parametres/verification-comptes', [VerificationChannelConsoleController::class, 'update'])
                    ->middleware('throttle:10,1')
                    ->name('console.parametres.verification.update');
                Route::post('/parametres/verification-comptes/test-email', [VerificationChannelConsoleController::class, 'testEmail'])
                    ->middleware('throttle:5,1')
                    ->name('console.parametres.verification.test-email');
                Route::post('/parametres/verification-comptes/test-sms', [VerificationChannelConsoleController::class, 'testSms'])
                    ->middleware('throttle:5,1')
                    ->name('console.parametres.verification.test-sms');
            });
    }
}
