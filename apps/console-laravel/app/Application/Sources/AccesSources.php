<?php

declare(strict_types=1);

namespace App\Application\Sources;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\PolitiqueSources;
use Gamad\RegistreSources\RegistreSources;

/**
 * Cas d'usage du registre des sources (CAP-CORE-006).
 *
 * Le module Core décrit ce qu'est une source gouvernée ; cette couche l'insère
 * dans le parcours réel : CAP-CORE-004 décide, CAP-CORE-013 conserve la
 * preuve, et seule une décision permise et prouvée atteint l'écriture. Une
 * indisponibilité de la décision ou du journal ferme l'opération — un
 * changement d'état sans trace serait un mode dégradé silencieux.
 */
final class AccesSources
{
    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        try {
            $source = $this->registre()->resoudreSource($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($source === null || !$this->visible($source, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'SOURCE_INTROUVABLE']];
        }

        try {
            $verification = $this->registre()->resoudreVerificationCourante($reference);
            $finalites = $this->registre()->resoudreFinalites($reference);
            $lignee = $this->registre()->resoudreLignee($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return [
            'statut' => 200,
            'corps' => [
                'source' => $source,
                'verification' => $verification,
                'finalites' => $finalites,
                'lignee' => $lignee,
            ],
        ];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(string $acteur, ?string $etat = null, ?string $type = null): array
    {
        try {
            $filtres = array_filter([
                'etat' => $etat,
                'type_source' => $type,
            ], static fn (mixed $v): bool => $v !== null);
            $tous = $this->registre()->listerSources($filtres);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        $visibles = array_values(array_filter(
            $tous,
            fn (array $s): bool => $this->visible($s, $acteur),
        ));

        return ['statut' => 200, 'corps' => ['sources' => $visibles]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifierUtilisabilite(
        string $reference,
        ?string $consommateur,
        string $finalite,
        string $acteur,
    ): array {
        try {
            $source = $this->registre()->resoudreSource($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($source === null || !$this->visible($source, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'SOURCE_INTROUVABLE']];
        }

        try {
            $resultat = $this->registre()->verifierUtilisable($reference, $consommateur, $finalite);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => $resultat];
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function inscrire(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_INSCRIRE,
            $donnees['reference'] ?? null,
            $acteur,
            $correlation,
            'SOURCE_INSCRITE',
            fn (RegistreSources $registre, array $dossier): array => $registre->inscrireSource($dossier),
            $donnees,
            201,
        );
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function modifier(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_MODIFIER,
            $reference,
            $acteur,
            $correlation,
            'SOURCE_MODIFIEE',
            fn (RegistreSources $registre, array $dossier): array => $registre->modifierSource($reference, $dossier),
            $donnees,
            200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_ACTIVER,
            $reference,
            $acteur,
            $correlation,
            'SOURCE_ACTIVEE',
            fn (RegistreSources $registre, array $dossier): array => $registre->activerSource($reference, $dossier),
            $donnees,
            200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendre(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_SUSPENDRE,
            $reference,
            $acteur,
            $correlation,
            'SOURCE_SUSPENDUE',
            fn (RegistreSources $registre, array $dossier): array => $registre->suspendreSource($reference, $dossier),
            $donnees,
            200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_RETIRER,
            $reference,
            $acteur,
            $correlation,
            'SOURCE_RETIREE',
            fn (RegistreSources $registre, array $dossier): array => $registre->retirerSource($reference, $dossier),
            $donnees,
            200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerFinalite(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_FINALITE_DECLARER,
            $reference,
            $acteur,
            $correlation,
            'FINALITE_SOURCE_DECLAREE',
            fn (RegistreSources $registre, array $dossier): array => $registre->declarerFinalite($reference, $dossier),
            $donnees,
            201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerFinalite(string $reference, int $id, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_FINALITE_FERMER,
            $reference,
            $acteur,
            $correlation,
            'FINALITE_SOURCE_FERMEE',
            fn (RegistreSources $registre, array $dossier): array => $registre->fermerFinalite($reference, $id, $dossier),
            $donnees,
            200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function enregistrerVerification(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_VERIFICATION_ENREGISTRER,
            $reference,
            $acteur,
            $correlation,
            'VERIFICATION_SOURCE_ENREGISTREE',
            fn (RegistreSources $registre, array $dossier): array => $registre->enregistrerVerification($reference, $dossier),
            $donnees,
            201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerLignee(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueSources::ACTION_LIGNEE_DECLARER,
            $reference,
            $acteur,
            $correlation,
            'LIGNEE_SOURCE_DECLAREE',
            fn (RegistreSources $registre, array $dossier): array => $registre->declarerLignee($reference, $dossier),
            $donnees,
            201,
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
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, $action, $ressource);
            $journal = $this->journal();
            $preuve = $journal->enregistrer([
                'categorie' => 'SOURCES',
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
                'categorie' => 'SOURCES',
                'type' => 'OPERATION_SOURCE_REFUSEE',
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
            'politique' => $decision['politique'] ?? PolitiqueSources::POLITIQUE,
            'source' => PolitiqueSources::SOURCE,
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
                    'erreur' => 'REGISTRE_SOURCES_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'SOURCES',
                'type' => 'OPERATION_SOURCE_REFUSEE',
                'acteur' => $acteur,
                'action' => $action,
                'ressource' => $ressource,
                'decision' => 'REFUSEE',
                'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'],
                'donnees' => ['refus' => $resultat['refus']],
            ]);

            $statut = match ($resultat['refus']) {
                'SOURCE_INCONNUE', 'SOURCE_PARENTE_INCONNUE', 'FINALITE_INCONNUE',
                'PROPRIETAIRE_INCONNU', 'PRODUIT_PRODUCTEUR_INCONNU', 'PRODUIT_CONSOMMATEUR_INCONNU' => 404,
                'REFERENCE_DEJA_UTILISEE', 'CONFLIT_DATES' => 409,
                default => 422,
            };

            return ['statut' => $statut, 'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve]];
        }

        $preuveOperation = $this->tracer($journal, [
            'categorie' => 'SOURCES',
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

    /** @param array<string,mixed> $source */
    private function visible(array $source, string $acteur): bool
    {
        if ($source['etat'] === 'ACTIVE') {
            return true;
        }

        return $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
            || $acteur === $source['proprietaire_reference'];
    }

    private function registre(): RegistreSources
    {
        $index = Db::connect();
        $registreIdentites = IdentiteMagasin::connecter();

        return new RegistreSources(
            $index,
            $registreIdentites,
            SourcesMagasin::connecter(),
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
                'message' => 'Le registre des sources est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
