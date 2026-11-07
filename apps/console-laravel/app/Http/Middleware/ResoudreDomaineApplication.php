<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Produits\ApplicationsInstallees;
use Closure;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\ContratApplicationInterne;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frontière HTTP : le nom d'hôte effectif est validé avant toute sélection.
 * Ne déclare ni DNS ni application, et ne confère aucune permission.
 */
final class ResoudreDomaineApplication
{
    public function handle(Request $request, Closure $suivant): Response
    {
        // Déploiement explicite : les installations historiques restent inchangées
        // tant que l'hôte institutionnel n'est pas déclaré.
        $institutionnel = trim((string) config('gamad_domains.institutional_host', ''));
        if ($institutionnel === '') {
            if (app()->environment('production')) {
                abort(503, 'Hôte institutionnel non configuré');
            }
            return $suivant($request);
        }

        $brut = $request->headers->get('host', '');
        if (!is_string($brut) || $brut === '' || str_contains($brut, ',') || preg_match('/[\s\/\\\\@]/', $brut)) {
            abort(400, 'Hôte invalide');
        }
        // Le host transmis par Symfony est issu des proxys approuvés par Laravel.
        // Aucun X-Forwarded-Host non fiable n'est interprété directement ici.
        try {
            $hote = ContratApplicationInterne::domaine($request->getHost());
        } catch (\InvalidArgumentException) {
            abort(400, 'Hôte invalide');
        }
        if ($hote === $institutionnel) {
            $request->attributes->set('gamad_domaine_institutionnel', true);
            return $suivant($request);
        }

        try {
            $registre = new RegistreProduits(Db::connect(), IdentiteMagasin::connecter(), ProduitsMagasin::connecter());
            $application = $registre->resoudreApplicationParDomaine($hote);
        } catch (\Throwable) {
            abort(503, 'Registre applicatif indisponible');
        }
        if ($application === null || !ApplicationsInstallees::accepte($application)) {
            abort(404, 'Domaine applicatif inconnu');
        }

        $request->attributes->set('gamad_application', $application);
        $route = $request->route();
        $nom = $route?->getName();
        $accueil = $application['route_accueil'];
        // En l'absence de contrat explicite de route publique, ne jamais
        // rendre une route Core ou une route d'une autre application.
        // La route d'accueil est déjà résolue par Laravel : pas de redirection.
        if ($nom !== $accueil) {
            abort(404, 'Route indisponible sur ce domaine');
        }
        return $suivant($request);
    }
}
