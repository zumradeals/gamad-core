<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Livraison HTTP du contrat CTR-01 — identités (CAP-CORE-001).
 *
 * Cette livraison HTTP demeure en lecture. Les commandes d'inscription sont
 * des services internes gouvernés ; aucune route d'écriture anonyme ou simple
 * CRUD n'est exposée.
 */
final class Ctr01Controller
{
    private function ctr01(): Ctr01
    {
        $pdo = Db::connect();

        $vide = true;
        try {
            $vide = ((int) $pdo->query('SELECT count(*) FROM entite')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            (new Ingestion($pdo, dirname(base_path(), 2)))->executer();
        }

        return new Ctr01($pdo, Magasin::connecter());
    }

    public function resoudreIdentite(Request $request, string $reference): JsonResponse
    {
        $resultat = $this->ctr01()->resoudreIdentite($reference, $request->query('date'));

        if ($resultat === null) {
            return response()->json(['erreur' => 'entité introuvable', 'reference' => $reference], 404);
        }

        return response()->json($resultat);
    }

    public function resoudreInventaire(Request $request): JsonResponse
    {
        return response()->json($this->ctr01()->resoudreInventaire($request->query('type')));
    }

    public function resoudreDenominations(Request $request): JsonResponse
    {
        return response()->json($this->ctr01()->resoudreDenominations($request->query('reference')));
    }

    public function resoudreRegime(string $reference): JsonResponse
    {
        $resultat = $this->ctr01()->resoudreRegimeVerite($reference);

        return $resultat === null
            ? response()->json(['erreur' => 'entité introuvable', 'reference' => $reference], 404)
            : response()->json($resultat);
    }

    /**
     * L'assurance n'est lisible que par l'identité concernée ou par l'autorité
     * institutionnelle déjà canonique. Cette vérification locale ne remplace
     * pas CAP-CORE-004 ; elle ferme l'exposition tant qu'une politique plus
     * fine n'est pas déployée.
     */
    public function resoudreAssurance(Request $request, string $reference): JsonResponse
    {
        $appelant = (string) $request->attributes->get('gamad_entite', '');
        if (!in_array($appelant, [$reference, PolitiqueInscription::AUTORITE_INSCRIPTION], true)) {
            return response()->json(['erreur' => 'accès refusé'], 403);
        }
        if ($this->ctr01()->resoudreIdentite($reference) === null) {
            return response()->json(['erreur' => 'entité introuvable', 'reference' => $reference], 404);
        }

        return response()->json($this->ctr01()->resoudreAssurance($reference));
    }
}
