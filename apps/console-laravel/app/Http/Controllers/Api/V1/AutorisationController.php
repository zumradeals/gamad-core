<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AutorisationController
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'action' => ['required', 'string', 'max:256'],
            'ressource' => ['nullable', 'string', 'max:256'],
        ]);
        $acteur = (string) $request->attributes->get('gamad_entite');

        try {
            $index = Db::connect();
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser(
                $acteur,
                $donnees['action'],
                $donnees['ressource'] ?? null,
            );
            $mandat = (new Ctr02($index))->resoudreMandat(null, $acteur, gmdate('Y-m-d'));
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'AUTORISATION',
                'type' => 'DECISION_AUTORISATION',
                'acteur' => $acteur,
                'action' => $donnees['action'],
                'ressource' => $donnees['ressource'] ?? null,
                'decision' => $decision['decision'],
                'motif' => $decision['motif'],
                'correlation_id' => $request->attributes->get('gamad_correlation'),
                'donnees' => [
                    'politique' => $decision['politique'],
                    'version' => $decision['version'],
                    'mandat' => $mandat['mandat'] ?? null,
                    'etat_mandat' => $mandat['etat'] ?? null,
                ],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'SOCLE_INDISPONIBLE',
                'message' => 'La décision ne peut pas être établie et tracée.',
            ], 503);
        }

        return response()->json([
            'decision' => $decision,
            'mandat' => $mandat,
            'preuve' => $preuve,
        ]);
    }
}
