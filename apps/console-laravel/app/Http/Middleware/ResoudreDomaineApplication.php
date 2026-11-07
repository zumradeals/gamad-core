<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Produits\ApplicationsInstallees;
use Closure;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Après TrustProxies, avant le routeur, la session et les contrôleurs. */
final class ResoudreDomaineApplication
{
    public function handle(Request $request, Closure $suivant): Response
    {
        if (!config('gamad_domains.enabled')) {
            return $suivant($request);
        }
        $hote = $request->attributes->get('gamad_hote_valide');
        if (!is_string($hote)) {
            abort(503);
        }
        if ($hote === $request->attributes->get('gamad_hote_institutionnel')) {
            $request->attributes->set('gamad_domaine_institutionnel', true);
        } else {
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
        }
        $response = $suivant($request);
        // Aucun cache partagé ne peut conserver une sélection après suspension.
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
