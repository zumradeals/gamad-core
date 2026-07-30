<?php

declare(strict_types=1);

namespace App\Application\Identites;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;

/**
 * Cas d'usage partagé par l'API et la console web.
 *
 * @phpstan-type Execution array{statut:int,corps:array<string,mixed>}
 */
final class InscrireIdentite
{
    /**
     * @param  array<string,mixed>  $donnees
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function executer(array $donnees, string $acteur, ?string $correlation = null): array
    {
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
            $permis = $decision['decision'] === 'PERMIS' && (! $canalReserve || $mandatActif);
            $motif = $decision['motif'];
            if ($decision['decision'] === 'PERMIS' && $canalReserve && ! $mandatActif) {
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
                'correlation_id' => $correlation,
                'donnees' => [
                    'canal' => $donnees['canal'],
                    'classification' => $donnees['classification'] ?? 'INTERNE',
                    'politique' => $decision['politique'],
                    'mandat' => $mandat['mandat'] ?? null,
                    'etat_mandat' => $mandat['etat'] ?? null,
                ],
            ]);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'SOCLE_INDISPONIBLE',
                    'message' => 'L’inscription est fermée car sa décision et sa preuve ne peuvent pas être établies.',
                ],
            ];
        }

        if (! $permis) {
            return [
                'statut' => 403,
                'corps' => [
                    'erreur' => 'AUTORISATION_REFUSEE',
                    'decision' => $decision,
                    'mandat' => $mandat,
                    'preuve' => $preuveDecision,
                ],
            ];
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
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'REGISTRE_IDENTITES_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune inscription n’a été confirmée.',
                    'preuve' => $preuveDecision,
                ],
            ];
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

            return [
                'statut' => 422,
                'corps' => [
                    'erreur' => 'INSCRIPTION_REFUSEE',
                    'resultat' => $resultat,
                    'preuve' => $preuveRefus,
                ],
            ];
        }

        return [
            'statut' => 201,
            'corps' => [
                'identite' => $resultat,
                'decision' => $decision,
                'mandat' => $mandat,
                'preuve' => $preuveDecision,
            ],
        ];
    }
}
