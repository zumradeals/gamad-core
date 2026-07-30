<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inscription verticale protégée.
 *
 * Le sujet vient exclusivement de la session. CAP-CORE-004 est interrogée
 * avant toute écriture et son refus par défaut est appliqué physiquement.
 */
final class IdentiteController
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'canal' => ['required', 'string', 'in:PRODUIT_RECONNU,ORGANISATION_RECONNUE,AUTORITE,CREATION_TECHNIQUE'],
            'type' => ['required', 'string', 'in:personne,organisation,produit,realm,agent,service'],
            'libelle' => ['required', 'string', 'max:256'],
            'classification' => ['nullable', 'string', 'in:PUBLIC_ECOSYSTEME,INTERNE,CONFIDENTIEL,RESTREINT,SECRET_CORE'],
            'provisoire' => ['nullable', 'boolean'],
        ]);

        $acteur = (string) $request->attributes->get('gamad_entite');
        try {
            $index = Db::connect();
            $decision = (new Ctr03($index))->autoriser(
                $acteur,
                'inscrire une identité',
                (string) $donnees['type'],
            );
            $mandat = (new Ctr02($index))->resoudreMandat(null, $acteur, gmdate('Y-m-d'));
            $canalReserve = in_array($donnees['canal'], ['AUTORITE', 'CREATION_TECHNIQUE'], true);
            $mandatActif = is_array($mandat)
                && str_starts_with((string) ($mandat['etat'] ?? ''), 'ACTIF');
            $permis = $decision['decision'] === 'PERMIS' && (!$canalReserve || $mandatActif);
            $motif = $decision['motif'];
            if ($decision['decision'] === 'PERMIS' && $canalReserve && !$mandatActif) {
                $motif = 'canal réservé sans mandat actif vérifié';
            }

            $journal = new Journal(JournalMagasin::connecter());
            $preuveDecision = $journal->enregistrer([
                'categorie' => 'AUTORISATION',
                'type' => 'DECISION_INSCRIPTION_IDENTITE',
                'acteur' => $acteur,
                'action' => 'inscrire une identité',
                'ressource' => (string) $donnees['type'],
                'decision' => $permis ? 'PERMIS' : 'REFUSE',
                'motif' => $motif,
                'correlation_id' => $request->attributes->get('gamad_correlation'),
                'donnees' => [
                    'canal' => $donnees['canal'],
                    'classification' => $donnees['classification'] ?? 'INTERNE',
                    'politique' => $decision['politique'],
                    'mandat' => $mandat['mandat'] ?? null,
                    'etat_mandat' => $mandat['etat'] ?? null,
                ],
            ]);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'SOCLE_INDISPONIBLE',
                'message' => 'L’inscription est fermée car sa décision et sa preuve ne peuvent pas être établies.',
            ], 503);
        }

        if (!$permis) {
            return response()->json([
                'erreur' => 'AUTORISATION_REFUSEE',
                'decision' => $decision,
                'mandat' => $mandat,
                'preuve' => $preuveDecision,
            ], 403);
        }

        try {
            $resultat = (new Ctr01($index, IdentiteMagasin::connecter()))->inscrireIdentite([
                'canal' => $donnees['canal'],
                'type' => $donnees['type'],
                'libelle' => $donnees['libelle'],
                'producteur' => $acteur,
                'politique' => $decision['politique'],
                'source' => $decision['source'],
                'preuve' => $preuveDecision['reference'],
                'classification' => $donnees['classification'] ?? 'INTERNE',
                'provisoire' => $donnees['provisoire'] ?? false,
                'date' => gmdate('Y-m-d'),
            ]);
        } catch (\Throwable) {
            return response()->json([
                'erreur' => 'REGISTRE_IDENTITES_INDISPONIBLE',
                'message' => 'L’intention est tracée, mais aucune inscription n’a été confirmée.',
                'preuve' => $preuveDecision,
            ], 503);
        }

        if (isset($resultat['refus'])) {
            try {
                $preuveRefus = $journal->enregistrer([
                    'categorie' => 'IDENTITE',
                    'type' => 'INSCRIPTION_IDENTITE_REFUSEE',
                    'acteur' => $acteur,
                    'action' => 'inscrire une identité',
                    'decision' => 'REFUSEE',
                    'motif' => $resultat['detail'] ?? $resultat['refus'],
                    'correlation_id' => $preuveDecision['correlation_id'],
                    'donnees' => ['preuve_decision' => $preuveDecision['reference']],
                ]);
            } catch (\Throwable) {
                $preuveRefus = $preuveDecision;
            }

            return response()->json([
                'erreur' => 'INSCRIPTION_REFUSEE',
                'resultat' => $resultat,
                'preuve' => $preuveRefus,
            ], 422);
        }

        return response()->json([
            'identite' => $resultat,
            'decision' => $decision,
            'mandat' => $mandat,
            // L'écriture persistante référence déjà cette preuve d'intention
            // et d'autorisation. Aucun second commit inter-base n'est requis.
            'preuve' => $preuveDecision,
        ], 201);
    }
}
