<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Livraison HTTP du contrat CTR-01 — identités (CAP-CORE-001).
 *
 * Lecture seule (INV-4) : créer, corriger, fusionner ou clore une identité
 * demeure un acte signé, jamais une opération de service.
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

        return new Ctr01($pdo);
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
}
