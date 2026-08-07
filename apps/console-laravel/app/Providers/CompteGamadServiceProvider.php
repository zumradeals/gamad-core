<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CompteGamadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api'])
            ->prefix('api')
            ->group(base_path('routes/compte-gamad.php'));
    }
}
