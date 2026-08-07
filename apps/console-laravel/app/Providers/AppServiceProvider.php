<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * La création d'un Compte GAMAD est derrière `gamad.api` : le sujet
         * authentifié est donc disponible. Chaque produit habilité possède son
         * propre quota et ne partage pas le compteur des sessions personnelles.
         */
        RateLimiter::for('gamad-account-create', static function (Request $request): Limit {
            $produit = trim((string) $request->attributes->get('gamad_entite', ''));
            $cle = $produit !== '' ? $produit : 'sans-sujet:' . (string) $request->ip();

            return Limit::perMinute(20)->by('gamad-account-create:' . $cle);
        });
    }
}
