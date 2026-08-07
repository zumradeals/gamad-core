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

        /*
         * Un portail ou satellite peut être un proxy partagé par beaucoup
         * d'utilisateurs. Le renvoi est donc borné par RID et non par IP.
         * La couche registre applique en plus 60 s minimum et 5 défis/heure.
         */
        RateLimiter::for('gamad-account-verification-resend', static function (Request $request): Limit {
            $rid = trim((string) $request->input('identifiant_reference', ''));
            $cle = $rid !== ''
                ? hash('sha256', $rid)
                : hash('sha256', 'sans-rid:' . (string) $request->ip());

            return Limit::perMinute(5)->by('gamad-account-verification-resend:' . $cle);
        });
    }
}
