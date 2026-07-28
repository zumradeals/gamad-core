<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Livraison HTTP du contrat CTR-03 — autorisation (CAP-CORE-004).
 *
 * Le moteur dit ce qui est permis ; il n'empêche rien physiquement (M-29).
 * Aucune route d'écriture : une politique se change par acte, non par requête.
 */
final class Ctr03Controller
{
    private function ctr03(): Ctr03
    {
        $pdo = Db::connect();
        $vide = true;
        try {
            $vide = ((int) $pdo->query('SELECT count(*) FROM regle')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            (new Ingestion($pdo, dirname(base_path(), 2)))->executer();
        }

        return new Ctr03($pdo);
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
