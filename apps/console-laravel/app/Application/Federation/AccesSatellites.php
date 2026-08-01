<?php

declare(strict_types=1);

namespace App\Application\Federation;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreFederation\Federation;
use Gamad\RegistreFederation\PolitiqueFederation;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;

/**
 * Cas d'usage de la fédération des satellites (CAP-CORE-022).
 *
 * Le module Core décrit ce qu'est un accès fédéré ; cette couche l'insère dans
 * le parcours réel : CAP-CORE-004 décide, CAP-CORE-013 conserve la preuve, et
 * seule une décision permise et prouvée atteint l'écriture.
 *
 * Une indisponibilité de la décision ou du journal ferme l'ouverture. Un accès
 * ouvert sans trace serait un mode dégradé silencieux.
 */
final class AccesSatellites
{
    /**
     * @param  array<string,mixed>  $options  relation_type, sujet_local_opaque, duree.
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function ouvrir(
        string $identite,
        string $produit,
        string $acteur,
        string $session,
        array $options = [],
        ?string $correlation = null,
    ): array {
        try {
            $federation = $this->federation();
            $decision = (new Ctr03(Db::connect()))->autoriser(
                $acteur,
                PolitiqueFederation::ACTION_OUVERTURE,
                $produit,
            );
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'FEDERATION',
                'type' => 'DECISION_OUVERTURE_PRODUIT',
                'acteur' => $acteur,
                'action' => PolitiqueFederation::ACTION_OUVERTURE,
                'ressource' => $produit,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'],
                'correlation_id' => $correlation,
                'donnees' => [
                    'identite' => $identite,
                    'politique' => $decision['politique'],
                    'pour_autrui' => $identite !== $acteur,
                ],
            ]);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($decision['decision'] !== 'PERMIS') {
            return [
                'statut' => 403,
                'corps' => [
                    'erreur' => 'AUTORISATION_REFUSEE',
                    'decision' => $decision,
                    'preuve' => $preuve,
                ],
            ];
        }

        try {
            $resultat = $federation->ouvrir($identite, $produit, $acteur, [
                'politique' => $decision['politique'] ?? PolitiqueFederation::POLITIQUE,
                'source' => PolitiqueFederation::SOURCE,
                'preuve' => $preuve['reference'],
                'session_empreinte' => Federation::empreinteSession($session),
                'correlation_id' => $preuve['correlation_id'],
                'relation_type' => $options['relation_type'] ?? PolitiqueFederation::RELATION_PAR_DEFAUT,
                'sujet_local_opaque' => $options['sujet_local_opaque'] ?? null,
                'duree' => $options['duree'] ?? PolitiqueFederation::DUREE_JETON,
            ]);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'FEDERATION_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucun accès n’a été ouvert.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'FEDERATION',
                'type' => 'OUVERTURE_PRODUIT_REFUSEE',
                'acteur' => $acteur,
                'action' => PolitiqueFederation::ACTION_OUVERTURE,
                'ressource' => $produit,
                'decision' => 'REFUSEE',
                'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'],
                'donnees' => ['identite' => $identite, 'refus' => $resultat['refus']],
            ]);

            return [
                'statut' => 422,
                'corps' => [
                    'erreur' => 'OUVERTURE_REFUSEE',
                    'resultat' => $resultat,
                    'preuve' => $preuve,
                ],
            ];
        }

        // Le jeton n'entre jamais au journal : sa seule trace est sa référence.
        $preuveOuverture = $this->tracer($journal, [
            'categorie' => 'FEDERATION',
            'type' => 'OUVERTURE_PRODUIT',
            'acteur' => $acteur,
            'action' => PolitiqueFederation::ACTION_OUVERTURE,
            'ressource' => $produit,
            'decision' => 'EXECUTEE',
            'correlation_id' => $preuve['correlation_id'],
            'donnees' => [
                'identite' => $identite,
                'relation' => $resultat['relation'],
                'relation_type' => $resultat['relation_type'],
                'jeton_reference' => $resultat['reference'],
                'expire_le' => $resultat['expire_le'],
                'provisionne' => $resultat['provisionne'],
                'portees' => $resultat['portees'],
            ],
        ]) ?? $preuve;

        unset($resultat['provisionne']);

        return [
            'statut' => 201,
            'corps' => [
                'acces' => $resultat,
                'decision' => $decision,
                'preuve' => $preuveOuverture,
            ],
        ];
    }

    /**
     * Vérification présentée par le satellite lui-même. L'audience du jeton est
     * confrontée au produit appelant : c'est la borne qui empêche un jeton
     * GamaDrive d'ouvrir Wasplex.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function verifier(
        string $jeton,
        string $produit,
        string $acteur,
        ?string $correlation = null,
    ): array {
        if ($acteur !== $produit) {
            return [
                'statut' => 403,
                'corps' => [
                    'erreur' => 'APPELANT_INCOMPETENT',
                    'message' => 'Un jeton fédéré n’est vérifiable que par le satellite auquel il est destiné.',
                ],
            ];
        }

        try {
            $verdict = $this->federation()->verifierJeton($jeton, $produit);
            $preuve = $this->journal()->enregistrer([
                'categorie' => 'FEDERATION',
                'type' => 'VERIFICATION_JETON_FEDERE',
                'acteur' => $acteur,
                'action' => 'vérifier un jeton fédéré',
                'ressource' => $produit,
                'decision' => $verdict['valide'] === true ? 'ACCEPTEE' : 'REFUSEE',
                'motif' => $verdict['motif'] ?? null,
                'correlation_id' => $correlation,
                'donnees' => [
                    'identite' => $verdict['identite'] ?? null,
                    'jeton_reference' => $verdict['reference'] ?? null,
                ],
            ]);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($verdict['valide'] !== true) {
            return [
                'statut' => 401,
                'corps' => [
                    'erreur' => 'JETON_FEDERE_REFUSE',
                    'motif' => $verdict['motif'],
                    'detail' => $verdict['detail'] ?? null,
                    'preuve' => $preuve,
                ],
            ];
        }

        return ['statut' => 200, 'corps' => ['acces' => $verdict, 'preuve' => $preuve]];
    }

    /**
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function revoquer(
        string $identite,
        string $produit,
        string $acteur,
        ?string $correlation = null,
    ): array {
        try {
            $federation = $this->federation();
            $decision = (new Ctr03(Db::connect()))->autoriser(
                $acteur,
                PolitiqueFederation::ACTION_REVOCATION,
                $produit,
            );
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'FEDERATION',
                'type' => 'DECISION_REVOCATION_ACCES',
                'acteur' => $acteur,
                'action' => PolitiqueFederation::ACTION_REVOCATION,
                'ressource' => $produit,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'],
                'correlation_id' => $correlation,
                'donnees' => ['identite' => $identite, 'politique' => $decision['politique']],
            ]);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($decision['decision'] !== 'PERMIS') {
            return [
                'statut' => 403,
                'corps' => [
                    'erreur' => 'AUTORISATION_REFUSEE',
                    'decision' => $decision,
                    'preuve' => $preuve,
                ],
            ];
        }

        try {
            $resultat = $federation->revoquerAcces($identite, $produit, $acteur, [
                'politique' => $decision['politique'] ?? PolitiqueFederation::POLITIQUE,
                'source' => PolitiqueFederation::SOURCE,
                'preuve' => $preuve['reference'],
            ]);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'FEDERATION_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune révocation n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            return [
                'statut' => 422,
                'corps' => [
                    'erreur' => 'REVOCATION_REFUSEE',
                    'resultat' => $resultat,
                    'preuve' => $preuve,
                ],
            ];
        }

        $preuveRevocation = $this->tracer($journal, [
            'categorie' => 'FEDERATION',
            'type' => 'REVOCATION_ACCES_PRODUIT',
            'acteur' => $acteur,
            'action' => PolitiqueFederation::ACTION_REVOCATION,
            'ressource' => $produit,
            'decision' => 'EXECUTEE',
            'correlation_id' => $preuve['correlation_id'],
            'donnees' => $resultat,
        ]) ?? $preuve;

        return ['statut' => 200, 'corps' => ['revocation' => $resultat, 'preuve' => $preuveRevocation]];
    }

    /**
     * Catalogue des satellites et accès de l'identité appelante.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function inventaire(string $identite): array
    {
        try {
            $federation = $this->federation();

            return [
                'statut' => 200,
                'corps' => [
                    'identite' => $identite,
                    'produits' => $federation->catalogueProduits(),
                    'acces' => $federation->resoudreAcces($identite),
                ],
            ];
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
    }

    private function federation(): Federation
    {
        return new Federation(
            Db::connect(),
            IdentiteMagasin::connecter(),
            AccesMagasin::connecter(),
        );
    }

    private function journal(): Journal
    {
        return new Journal(JournalMagasin::connecter());
    }

    /**
     * Trace un fait déjà accompli. L'écriture est faite ; une panne du journal
     * ne peut plus l'annuler, elle est signalée en conservant la preuve amont.
     *
     * @param  array<string,mixed>  $evenement
     * @return array<string,mixed>|null
     */
    private function tracer(Journal $journal, array $evenement): ?array
    {
        try {
            return $journal->enregistrer($evenement);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function socleIndisponible(): array
    {
        return [
            'statut' => 503,
            'corps' => [
                'erreur' => 'SOCLE_INDISPONIBLE',
                'message' => 'La fédération est fermée car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
