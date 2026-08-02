<?php

declare(strict_types=1);

namespace App\Application\Federation;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreFederation\Federation;
use Gamad\RegistreFederation\PolitiqueFederation;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;

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
     * Délivre au satellite l'identifiant avec lequel il s'authentifiera auprès
     * du Core.
     *
     * Le secret est engendré par le Core, remis une seule fois à l'appelant, et
     * conservé sous forme d'empreinte non réversible par CAP-CORE-005. Il
     * n'entre jamais au journal : la preuve ne porte que la référence de
     * l'authentificateur.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function delivrerIdentifiant(
        string $produit,
        string $acteur,
        ?string $correlation = null,
    ): array {
        try {
            $federation = $this->federation();
            $decision = (new Ctr03(Db::connect()))->autoriser(
                $acteur,
                PolitiqueFederation::ACTION_IDENTIFIANT,
                $produit,
            );
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'FEDERATION',
                'type' => 'DECISION_IDENTIFIANT_SATELLITE',
                'acteur' => $acteur,
                'action' => PolitiqueFederation::ACTION_IDENTIFIANT,
                'ressource' => $produit,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'],
                'correlation_id' => $correlation,
                'donnees' => ['politique' => $decision['politique']],
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

        // Un satellite non entériné ne reçoit pas d'identifiant : le Core ne
        // délivre pas la clé d'une porte qu'il refuse d'ouvrir.
        $catalogue = array_column($federation->catalogueProduits(), null, 'reference');
        $satellite = $catalogue[$produit] ?? null;
        if ($satellite === null || $satellite['federable'] !== true) {
            return [
                'statut' => 422,
                'corps' => [
                    'erreur' => 'IDENTIFIANT_REFUSE',
                    'resultat' => [
                        'refus' => $satellite === null ? 'PRODUIT_INCONNU' : 'PRODUIT_NON_RECONNU',
                        'detail' => $satellite === null
                            ? "produit `{$produit}` inconnu du Core"
                            : 'l’état du produit ne vaut pas reconnaissance',
                    ],
                    'preuve' => $preuve,
                ],
            ];
        }

        try {
            $ctr16 = new Ctr16(AccesMagasin::connecter());
            $actifs = $this->identifiantsActifs($ctr16, $produit);
            if (count($actifs) >= PolitiqueFederation::MAX_IDENTIFIANTS) {
                return [
                    'statut' => 422,
                    'corps' => [
                        'erreur' => 'IDENTIFIANT_REFUSE',
                        'resultat' => [
                            'refus' => 'MAXIMUM_ATTEINT',
                            'detail' => sprintf(
                                'ce satellite a déjà %d identifiants actifs ; retirez-en un avant d’en délivrer un nouveau',
                                count($actifs),
                            ),
                        ],
                        'preuve' => $preuve,
                    ],
                ];
            }

            $secret = PolitiqueFederation::engendrerSecret();
            $reference = $ctr16->inscrireAuthentificateur(
                $produit,
                $secret,
                PolitiqueFederation::TYPE_IDENTIFIANT,
            );
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'MAGASIN_ACCES_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucun identifiant n’a été délivré.',
                    'preuve' => $preuve,
                ],
            ];
        }

        $preuveDelivrance = $this->tracer($journal, [
            'categorie' => 'FEDERATION',
            'type' => 'IDENTIFIANT_SATELLITE_DELIVRE',
            'acteur' => $acteur,
            'action' => PolitiqueFederation::ACTION_IDENTIFIANT,
            'ressource' => $produit,
            'decision' => 'EXECUTEE',
            'correlation_id' => $preuve['correlation_id'],
            'donnees' => [
                'authentificateur' => $reference,
                'type' => PolitiqueFederation::TYPE_IDENTIFIANT,
                'identifiants_actifs' => count($actifs) + 1,
            ],
        ]) ?? $preuve;

        return [
            'statut' => 201,
            'corps' => [
                'identifiant' => [
                    'reference' => $reference,
                    'produit' => $produit,
                    'secret' => $secret,
                    'type' => PolitiqueFederation::TYPE_IDENTIFIANT,
                ],
                'preuve' => $preuveDelivrance,
            ],
        ];
    }

    /**
     * Retire un identifiant de raccordement. Les sessions Core ouvertes avec
     * lui cessent d'être valides, et les jetons fédérés qui en dépendaient
     * tombent avec elles.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function retirerIdentifiant(
        string $produit,
        string $authentificateur,
        string $acteur,
        ?string $correlation = null,
    ): array {
        try {
            $decision = (new Ctr03(Db::connect()))->autoriser(
                $acteur,
                PolitiqueFederation::ACTION_RETRAIT_IDENTIFIANT,
                $produit,
            );
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'FEDERATION',
                'type' => 'DECISION_RETRAIT_IDENTIFIANT',
                'acteur' => $acteur,
                'action' => PolitiqueFederation::ACTION_RETRAIT_IDENTIFIANT,
                'ressource' => $produit,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'],
                'correlation_id' => $correlation,
                'donnees' => ['authentificateur' => $authentificateur],
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
            $ctr16 = new Ctr16(AccesMagasin::connecter());
            // La référence doit appartenir à ce satellite : une référence
            // devinée ne doit pas permettre de révoquer l'accès d'un autre.
            $sien = array_filter(
                $this->identifiantsActifs($ctr16, $produit),
                static fn (array $a): bool => $a['reference'] === $authentificateur,
            );
            $retire = $sien !== [] && $ctr16->revoquerAuthentificateur($authentificateur);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'MAGASIN_ACCES_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucun retrait n’a été confirmé.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (! $retire) {
            return [
                'statut' => 422,
                'corps' => [
                    'erreur' => 'RETRAIT_REFUSE',
                    'resultat' => [
                        'refus' => 'IDENTIFIANT_INTROUVABLE',
                        'detail' => 'aucun identifiant actif de ce satellite ne porte cette référence',
                    ],
                    'preuve' => $preuve,
                ],
            ];
        }

        $preuveRetrait = $this->tracer($journal, [
            'categorie' => 'FEDERATION',
            'type' => 'IDENTIFIANT_SATELLITE_RETIRE',
            'acteur' => $acteur,
            'action' => PolitiqueFederation::ACTION_RETRAIT_IDENTIFIANT,
            'ressource' => $produit,
            'decision' => 'EXECUTEE',
            'correlation_id' => $preuve['correlation_id'],
            'donnees' => ['authentificateur' => $authentificateur],
        ]) ?? $preuve;

        return [
            'statut' => 200,
            'corps' => [
                'retrait' => ['authentificateur' => $authentificateur, 'produit' => $produit],
                'preuve' => $preuveRetrait,
            ],
        ];
    }

    /**
     * Identifiants de raccordement actifs d'un satellite. Aucune empreinte
     * n'est restituée : seules la référence et la date servent l'exploitation.
     *
     * @return list<array<string,mixed>>
     */
    public function identifiants(string $produit): array
    {
        try {
            return $this->identifiantsActifs(new Ctr16(AccesMagasin::connecter()), $produit);
        } catch (\Throwable) {
            return [];
        }
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

    /**
     * Tout authentificateur actif d'un produit est un identifiant de
     * raccordement : un produit n'est pas une personne, il n'a pas d'autre
     * usage d'un secret. Le type est restitué pour distinguer ceux que la
     * console a délivrés de ceux créés en ligne de commande, mais il ne filtre
     * pas la liste — un secret hérité doit rester retirable ici.
     *
     * Aucune empreinte n'est restituée.
     *
     * @return list<array<string,mixed>>
     */
    private function identifiantsActifs(Ctr16 $ctr16, string $produit): array
    {
        return array_values(array_map(
            static fn (array $a): array => [
                'reference' => $a['reference'],
                'type' => $a['type'],
                'assurance' => $a['niveau_assurance'],
                'cree_le' => $a['cree_le'],
                'delivre_par_console' => $a['type'] === PolitiqueFederation::TYPE_IDENTIFIANT,
            ],
            array_filter(
                $ctr16->attester($produit)['authentificateurs'],
                static fn (array $a): bool => $a['etat'] === 'ACTIF',
            ),
        ));
    }

    private function federation(): Federation
    {
        return new Federation(
            Db::connect(),
            IdentiteMagasin::connecter(),
            AccesMagasin::connecter(),
            ProduitsMagasin::connecter(),
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
