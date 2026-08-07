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
         * Une connexion ne doit jamais être limitée par la seule IP du proxy :
         * derrière un portail ou un satellite, des milliers de personnes
         * peuvent partager la même adresse de sortie. Le compteur principal
         * suit donc l'identifiant présenté (IDN, email, téléphone, username)
         * sous forme d'empreinte non réversible.
         *
         * Le nom de limiteur est distinct de celui de la création de compte :
         * une inscription ne doit jamais consommer le quota de connexion.
         */
        RateLimiter::for('gamad-session', static function (Request $request): Limit {
            $presente = trim((string) ($request->input('identifiant') ?? $request->input('entite') ?? ''));
            $type = strtoupper(trim((string) ($request->input('type_identifiant') ?? 'ENTITE')));
            $cle = $presente === ''
                ? 'absent:' . (string) $request->ip()
                : hash('sha256', $type . "\0" . mb_strtolower($presente, 'UTF-8'));

            return Limit::perMinute(5)->by('gamad-session:' . $cle);
        });

        /*
         * La création d'un Compte GAMAD est déjà derrière `gamad.api` : le
         * sujet authentifié est donc disponible. Chaque produit habilité a son
         * propre quota et ne partage pas le compteur des sessions personnelles.
         */
        RateLimiter::for('gamad-account-create', static function (Request $request): Limit {
            $produit = trim((string) $request->attributes->get('gamad_entite', ''));
            $cle = $produit !== '' ? $produit : 'sans-sujet:' . (string) $request->ip();

            return Limit::perMinute(20)->by('gamad-account-create:' . $cle);
        });
    }
}
