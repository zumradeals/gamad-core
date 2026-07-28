<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Livraison HTTP du contrat CTR-02 — autorités et mandats (CAP-CORE-003).
 *
 * Lecture et attestation seulement (INV-4). Ce contrôleur ne contient aucune
 * logique propre : il traduit la requête en appel à Gamad\RegistreAutorites\Ctr02
 * et la réponse en HTTP. Aucune route d'écriture n'est déclarée : nommer,
 * suspendre ou révoquer demeure un acte signé, jamais une opération de service.
 */
final class Ctr02Controller
{
    private function ctr02(): Ctr02
    {
        $pdo = Db::connect();
        $corpus = dirname(base_path(), 2);

        $vide = true;
        try {
            $vide = ((int) $pdo->query('SELECT count(*) FROM mandat')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            (new Ingestion($pdo, $corpus))->executer();
        }

        return new Ctr02($pdo);
    }

    public function resoudreMandat(Request $request, string $fonction): JsonResponse
    {
        $resultat = $this->ctr02()->resoudreMandat($fonction, null, $request->query('date'));

        if ($resultat === null) {
            return response()->json(
                ['erreur' => 'aucun mandat pour cette fonction à cette date', 'fonction' => $fonction],
                404,
            );
        }

        return response()->json($resultat);
    }

    public function verifierActe(string $reference): JsonResponse
    {
        $resultat = $this->ctr02()->verifierActe($reference);

        if ($resultat === null) {
            return response()->json(['erreur' => 'acte introuvable', 'reference' => $reference], 404);
        }

        return response()->json($resultat);
    }

    public function resoudreVacance(Request $request): JsonResponse
    {
        return response()->json($this->ctr02()->resoudreVacance($request->query('date')));
    }
}
