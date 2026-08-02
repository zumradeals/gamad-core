<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Livraison HTTP du contrat CTR-03 — autorisation (CAP-CORE-004).
 *
 * Le moteur dit ce qui est permis ; il n'empêche rien physiquement (M-29).
 * Aucune route d'écriture : une politique se change par une version soumise,
 * simulée et activée (CAP-CORE-007), non par cette requête.
 *
 * Le magasin des politiques est un registre persistant, jamais reconstruit
 * silencieusement : un magasin vide est un problème de readiness réel, pas un
 * état transitoire à combler ici.
 */
final class Ctr03Controller
{
    private function ctr03(): Ctr03
    {
        return new Ctr03(PolitiquesMagasin::connecter());
    }

    public function autoriser(Request $request): JsonResponse
    {
        $d = $request->validate([
            'sujet'     => ['required', 'string', 'max:64'],
            'action'    => ['required', 'string', 'max:256'],
            'ressource' => ['nullable', 'string', 'max:256'],
        ]);

        return response()->json(
            $this->ctr03()->simuler($d['sujet'], $d['action'], $d['ressource'] ?? null)
        );
    }

    public function interdits(Request $request): JsonResponse
    {
        return response()->json($this->ctr03()->resoudreInterdits($request->query('sujet')));
    }
}
