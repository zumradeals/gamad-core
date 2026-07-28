<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\RegistreNormes\Ctr04;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Livraison HTTP du contrat CTR-04 — lecture et attestation seulement (INV-4).
 *
 * Ce contrôleur ne contient aucune logique de résolution propre : il traduit
 * la requête en appel à Gamad\RegistreNormes\Ctr04 (cœur adopté, ADOPTION-0028
 * et ADOPTION-0029, non modifié par la présente couche) et la réponse de la
 * méthode en HTTP. Aucune méthode d'écriture n'est déclarée ici ni routée.
 */
final class Ctr04Controller
{
    private function ctr04(): Ctr04
    {
        $pdo = Db::connect();
        $corpus = dirname(base_path(), 2);

        $vide = true;
        try {
            $vide = ((int) $pdo->query('SELECT count(*) FROM adoption')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            (new Ingestion($pdo, $corpus))->executer();
        }

        return new Ctr04($pdo, $corpus);
    }

    public function tableauDeBord(): View
    {
        $ctr04 = $this->ctr04();
        $pdo = Db::connect();

        $adoptions = $pdo->query(
            'SELECT reference, autorite, date_adoption, signature_presente FROM adoption ORDER BY reference'
        )->fetchAll();
        $integrite = $ctr04->verifierIntegrite();
        $index = $ctr04->resoudreIndex();

        $concordants = array_filter($integrite, fn ($l) => $l['concorde']);
        $divergents = array_filter($integrite, fn ($l) => !$l['concorde']);

        $p3 = [];
        foreach ([['2026-07-26', 'EN CONCEPTION'], ['2026-07-27', 'CONÇUE'], ['2026-08-01', 'CONÇUE']] as [$d, $attendu]) {
            $r = $ctr04->resoudreNorme('CAP-CORE-007', null, $d);
            $p3[] = [
                'date' => $d,
                'attendu' => $attendu,
                'obtenu' => $r['statut'] ?? '(aucun)',
                'ok' => ($r['statut'] ?? null) === $attendu,
            ];
        }
        $p3Ok = count(array_filter($p3, fn ($c) => $c['ok'])) === count($p3);

        return view('tableau-de-bord', [
            'adoptions' => $adoptions,
            'concordants' => $concordants,
            'divergents' => $divergents,
            'index' => $index,
            'p3' => $p3,
            'p3Ok' => $p3Ok,
        ]);
    }

    public function resoudreNorme(Request $request, string $reference): JsonResponse
    {
        $resultat = $this->ctr04()->resoudreNorme(
            $reference,
            $request->query('version'),
            $request->query('date'),
        );

        if ($resultat === null) {
            return response()->json(['erreur' => 'norme introuvable', 'reference' => $reference], 404);
        }

        return response()->json($resultat);
    }

    public function verifierIntegrite(?string $reference = null): JsonResponse
    {
        return response()->json($this->ctr04()->verifierIntegrite($reference));
    }

    public function resoudreIndex(): JsonResponse
    {
        return response()->json($this->ctr04()->resoudreIndex());
    }
}
