<?php

declare(strict_types=1);

namespace App\Application\Produits;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreFederation\Federation;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;

/**
 * Cas d'usage du registre des produits (CAP-CORE-011).
 *
 * Le module Core décrit ce qu'est un produit gouverné ; cette couche l'insère
 * dans le parcours réel : CAP-CORE-004 décide, CAP-CORE-013 conserve la
 * preuve, et seule une décision permise et prouvée atteint l'écriture. Une
 * indisponibilité de la décision ou du journal ferme l'opération — un
 * changement d'état sans trace serait un mode dégradé silencieux.
 */
final class AccesProduits
{
    /**
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function resoudre(string $reference, string $acteur): array
    {
        try {
            $produit = $this->registre()->resoudreProduit($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($produit === null || !$this->visible($produit, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'PRODUIT_INTROUVABLE']];
        }

        try {
            $environnements = $this->registre()->resoudreEnvironnements($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['produit' => $produit, 'environnements' => $environnements]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(string $acteur, ?string $etat = null, ?string $type = null): array
    {
        try {
            $filtres = array_filter([
                'etat' => $etat,
                'type_produit' => $type,
            ], static fn (mixed $v): bool => $v !== null);
            $tous = $this->registre()->listerProduits($filtres);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        $visibles = array_values(array_filter(
            $tous,
            fn (array $p): bool => $this->visible($p, $acteur),
        ));

        return ['statut' => 200, 'corps' => ['produits' => $visibles]];
    }

    /**
     * @param array<string,mixed> $donnees
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function inscrire(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueProduits::ACTION_INSCRIRE,
            $donnees['reference'] ?? null,
            $acteur,
            $correlation,
            'PRODUIT_INSCRIT',
            fn (RegistreProduits $registre, array $dossier): array => $registre->inscrireProduit($dossier),
            $donnees,
            201,
        );
    }

    /**
     * @param array<string,mixed> $donnees
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function modifier(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueProduits::ACTION_MODIFIER,
            $reference,
            $acteur,
            $correlation,
            'PRODUIT_MODIFIE',
            fn (RegistreProduits $registre, array $dossier): array => $registre->modifierProduit($reference, $dossier),
            $donnees,
            200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueProduits::ACTION_ACTIVER,
            $reference,
            $acteur,
            $correlation,
            'PRODUIT_ACTIVE',
            fn (RegistreProduits $registre, array $dossier): array => $registre->activerProduit($reference, $dossier),
            $donnees,
            200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendre(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        $resultat = $this->executer(
            PolitiqueProduits::ACTION_SUSPENDRE,
            $reference,
            $acteur,
            $correlation,
            'PRODUIT_SUSPENDU',
            fn (RegistreProduits $registre, array $dossier): array => $registre->suspendreProduit($reference, $dossier),
            $donnees,
            200,
        );
        if ($resultat['statut'] === 200) {
            $this->fermerJetons($reference, 'produit suspendu');
        }

        return $resultat;
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        $resultat = $this->executer(
            PolitiqueProduits::ACTION_RETIRER,
            $reference,
            $acteur,
            $correlation,
            'PRODUIT_RETIRE',
            fn (RegistreProduits $registre, array $dossier): array => $registre->retirerProduit($reference, $dossier),
            $donnees,
            200,
        );
        if ($resultat['statut'] === 200) {
            $this->fermerJetons($reference, 'produit retiré');
        }

        return $resultat;
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerEnvironnement(
        string $reference,
        array $donnees,
        string $acteur,
        ?string $correlation = null,
    ): array {
        return $this->executer(
            PolitiqueProduits::ACTION_ENVIRONNEMENT_DECLARER,
            $reference,
            $acteur,
            $correlation,
            'ENVIRONNEMENT_PRODUIT_DECLARE',
            fn (RegistreProduits $registre, array $dossier): array => $registre->declarerEnvironnement($reference, $dossier),
            $donnees,
            201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerEnvironnement(
        string $reference,
        int $id,
        array $donnees,
        string $acteur,
        ?string $correlation = null,
    ): array {
        return $this->executer(
            PolitiqueProduits::ACTION_ENVIRONNEMENT_FERMER,
            $reference,
            $acteur,
            $correlation,
            'ENVIRONNEMENT_PRODUIT_FERME',
            fn (RegistreProduits $registre, array $dossier): array => $registre->fermerEnvironnement($reference, $id, $dossier),
            $donnees,
            200,
        );
    }

    // ------------------------------------------------------------------
    // Internes

    /**
     * Décide, journalise la décision, exécute, journalise le résultat. Toute
     * commande passe par ce même chemin : aucune écriture n'est possible sans
     * décision CAP-CORE-004 et sans preuve CAP-CORE-013.
     *
     * @param array<string,mixed> $donnees
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function executer(
        string $action,
        ?string $ressource,
        string $acteur,
        ?string $correlation,
        string $typeEvenementReussite,
        callable $operation,
        array $donnees,
        int $statutReussite,
    ): array {
        try {
            $registre = $this->registre();
            $decision = (new Ctr03(Db::connect()))->autoriser($acteur, $action, $ressource);
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'PRODUITS',
                'type' => 'DECISION_' . $typeEvenementReussite,
                'acteur' => $acteur,
                'action' => $action,
                'ressource' => $ressource,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'],
                'correlation_id' => $correlation,
                'donnees' => ['politique' => $decision['politique']],
            ]);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($decision['decision'] !== 'PERMIS') {
            $this->tracer($journal, [
                'categorie' => 'PRODUITS',
                'type' => 'OPERATION_PRODUIT_REFUSEE',
                'acteur' => $acteur,
                'action' => $action,
                'ressource' => $ressource,
                'decision' => 'REFUSEE',
                'motif' => 'autorisation refusée',
                'correlation_id' => $preuve['correlation_id'],
            ]);

            return [
                'statut' => 403,
                'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve],
            ];
        }

        // Les champs de gouvernance viennent exclusivement de la décision et
        // de la preuve établies ci-dessus : un client ne peut jamais en
        // fournir sa propre version dans le corps de la requête.
        $dossier = array_merge($donnees, [
            'politique' => $decision['politique'] ?? PolitiqueProduits::POLITIQUE,
            'source' => PolitiqueProduits::SOURCE,
            'producteur' => $acteur,
            'preuve' => $preuve['reference'],
            'correlation_id' => $preuve['correlation_id'],
        ]);

        try {
            $resultat = $operation($registre, $dossier);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'REGISTRE_PRODUITS_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'PRODUITS',
                'type' => 'OPERATION_PRODUIT_REFUSEE',
                'acteur' => $acteur,
                'action' => $action,
                'ressource' => $ressource,
                'decision' => 'REFUSEE',
                'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'],
                'donnees' => ['refus' => $resultat['refus']],
            ]);

            $statut = match ($resultat['refus']) {
                'PRODUIT_INCONNU', 'IDENTITE_INCONNUE' => 404,
                'REFERENCE_DEJA_UTILISEE', 'IDENTITE_DEJA_LIEE', 'AUDIENCE_DEJA_UTILISEE' => 409,
                default => 422,
            };

            return ['statut' => $statut, 'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve]];
        }

        $preuveOperation = $this->tracer($journal, [
            'categorie' => 'PRODUITS',
            'type' => $typeEvenementReussite,
            'acteur' => $acteur,
            'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? ''),
            'decision' => 'EXECUTEE',
            'correlation_id' => $preuve['correlation_id'],
            'donnees' => $resultat,
        ]) ?? $preuve;

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuveOperation]];
    }

    /** @param array<string,mixed> $produit */
    private function visible(array $produit, string $acteur): bool
    {
        if ($produit['etat'] === 'ACTIF') {
            return true;
        }

        return $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
            || $acteur === $produit['proprietaire_reference'];
    }

    private function fermerJetons(string $reference, string $motif): void
    {
        try {
            Federation::revoquerJetonsDuProduit(AccesMagasin::connecter(), $reference, $motif);
        } catch (\Throwable) {
            // La fermeture des jetons encore ouverts est une mesure
            // supplémentaire ; leur prochaine présentation sera de toute façon
            // refusée par `verifierUtilisablePourFederation()`.
        }
    }

    private function registre(): RegistreProduits
    {
        $index = Db::connect();
        $registreIdentites = IdentiteMagasin::connecter();

        return new RegistreProduits(
            $index,
            $registreIdentites,
            ProduitsMagasin::connecter(),
            new Ctr01($index, $registreIdentites),
        );
    }

    private function journal(): Journal
    {
        return new Journal(JournalMagasin::connecter());
    }

    /**
     * @param array<string,mixed> $evenement
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

    /** @return array{statut:int,corps:array<string,mixed>} */
    private function socleIndisponible(): array
    {
        return [
            'statut' => 503,
            'corps' => [
                'erreur' => 'SOCLE_INDISPONIBLE',
                'message' => 'Le registre des produits est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
