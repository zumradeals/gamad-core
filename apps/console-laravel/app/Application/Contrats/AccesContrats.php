<?php

declare(strict_types=1);

namespace App\Application\Contrats;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;

/**
 * Cas d'usage du registre des contrats (CAP-CORE-009).
 *
 * Même chemin gouverné que les autres registres persistants : CAP-CORE-004
 * décide, CAP-CORE-013 conserve la preuve, et seule une décision permise et
 * prouvée atteint l'écriture. `Ctr03` évalue `POL-CONTRATS-V1` en lisant le
 * registre des politiques (CAP-CORE-007), pas ce module.
 */
final class AccesContrats
{
    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        try {
            $contrat = $this->registre()->resoudreContrat($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($contrat === null || !$this->visible($contrat, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'CONTRAT_INTROUVABLE']];
        }

        try {
            $versions = $this->registre()->listerVersions($reference);
            $historique = $this->registre()->resoudreHistorique($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['contrat' => $contrat, 'versions' => $versions, 'historique' => $historique]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(string $acteur): array
    {
        try {
            $tous = $this->registre()->listerContrats();
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        $visibles = array_values(array_filter($tous, fn (array $c): bool => $this->visible($c, $acteur)));

        return ['statut' => 200, 'corps' => ['contrats' => $visibles]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreVersion(string $reference, string $version, string $acteur): array
    {
        try {
            $contrat = $this->registre()->resoudreContrat($reference);
            if ($contrat === null || !$this->visible($contrat, $acteur)) {
                return ['statut' => 404, 'corps' => ['erreur' => 'CONTRAT_INTROUVABLE']];
            }
            $v = $this->registre()->resoudreVersion($reference, $version);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($v === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'VERSION_INTROUVABLE']];
        }

        return ['statut' => 200, 'corps' => ['version' => $v]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreCompatibilite(string $reference, string $version, string $acteur): array
    {
        $r = $this->resoudreVersion($reference, $version, $acteur);
        if ($r['statut'] !== 200) {
            return $r;
        }
        try {
            $analyses = $this->registre()->resoudreCompatibilite($reference, $version);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['analyses' => $analyses]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreConformite(string $reference, string $version, string $acteur): array
    {
        $r = $this->resoudreVersion($reference, $version, $acteur);
        if ($r['statut'] !== 200) {
            return $r;
        }
        try {
            $conformites = $this->registre()->resoudreConformite($reference, $version);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['conformites' => $conformites]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreConsommateurs(string $reference, ?string $version, string $acteur): array
    {
        try {
            $contrat = $this->registre()->resoudreContrat($reference);
            if ($contrat === null || !$this->visible($contrat, $acteur)) {
                return ['statut' => 404, 'corps' => ['erreur' => 'CONTRAT_INTROUVABLE']];
            }
            $consommateurs = $this->registre()->resoudreConsommateurs($reference, $version);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['consommateurs' => $consommateurs]];
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function inscrire(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_INSCRIRE, $donnees['reference'] ?? null, $acteur, $correlation, 'CONTRAT_INSCRIT',
            fn (RegistreContrats $registre, array $dossier): array => $registre->inscrireContrat($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function creerVersion(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_CREER, $reference, $acteur, $correlation, 'VERSION_CONTRAT_CREEE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->creerVersion($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerPartie(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_CONSOMMATEUR_RATTACHER, $reference, $acteur, $correlation, 'PARTIE_CONTRAT_DECLAREE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->declarerPartie($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerOperation(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_MODIFIER, $reference, $acteur, $correlation, 'OPERATION_CONTRAT_DECLAREE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->declarerOperation($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerSchema(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_MODIFIER, $reference, $acteur, $correlation, 'SCHEMA_CONTRAT_DECLARE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->declarerSchema($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerErreur(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_MODIFIER, $reference, $acteur, $correlation, 'ERREUR_CONTRAT_DECLAREE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->declarerErreur($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerObligation(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_MODIFIER, $reference, $acteur, $correlation, 'OBLIGATION_CONTRAT_DECLAREE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->declarerObligation($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function soumettreVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_SOUMETTRE, $reference, $acteur, $correlation, 'VERSION_CONTRAT_SOUMISE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->soumettreVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function analyserCompatibilite(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_ANALYSER, $reference, $acteur, $correlation, 'COMPATIBILITE_CONTRAT_ANALYSEE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->analyserCompatibilite($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activerVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_ACTIVER, $reference, $acteur, $correlation, 'VERSION_CONTRAT_ACTIVEE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->activerVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function deprecierVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_DEPRECIER, $reference, $acteur, $correlation, 'VERSION_CONTRAT_DEPRECIEE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->deprecierVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendreVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_SUSPENDRE, $reference, $acteur, $correlation, 'VERSION_CONTRAT_SUSPENDUE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->suspendreVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirerVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_VERSION_RETIRER, $reference, $acteur, $correlation, 'VERSION_CONTRAT_RETIREE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->retirerVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function enregistrerConformite(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_CONFORMITE_ENREGISTRER, $reference, $acteur, $correlation, 'CONFORMITE_CONTRAT_ENREGISTREE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->enregistrerConformite($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function genererProjection(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueContrats::ACTION_PROJECTION_GENERER, $reference, $acteur, $correlation, 'PROJECTION_CONTRAT_GENEREE',
            fn (RegistreContrats $registre, array $dossier): array => $registre->genererProjection($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    // ------------------------------------------------------------------
    // Internes

    /**
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
                'categorie' => 'CONTRATS',
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
                'categorie' => 'CONTRATS', 'type' => 'OPERATION_CONTRAT_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => 'autorisation refusée',
                'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        $dossier = array_merge($donnees, [
            'politique' => $decision['politique'] ?? PolitiqueContrats::POLITIQUE,
            'source' => PolitiqueContrats::SOURCE,
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
                    'erreur' => 'REGISTRE_CONTRATS_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'CONTRATS', 'type' => 'OPERATION_CONTRAT_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'], 'donnees' => ['refus' => $resultat['refus']],
            ]);

            $statut = match ($resultat['refus']) {
                'CONTRAT_INCONNU', 'VERSION_INCONNUE', 'PROPRIETAIRE_INCONNU', 'OPERATION_INCONNUE' => 404,
                'REFERENCE_DEJA_UTILISEE', 'VERSION_DEJA_UTILISEE', 'OPERATION_DEJA_DECLAREE', 'ERREUR_DEJA_DECLAREE' => 409,
                default => 422,
            };

            return ['statut' => $statut, 'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve]];
        }

        $preuveOperation = $this->tracer($journal, [
            'categorie' => 'CONTRATS', 'type' => $typeEvenementReussite,
            'acteur' => $acteur, 'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? $resultat['contrat_reference'] ?? ''),
            'decision' => 'EXECUTEE', 'correlation_id' => $preuve['correlation_id'], 'donnees' => $resultat,
        ]) ?? $preuve;

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuveOperation]];
    }

    /** @param array<string,mixed> $contrat */
    private function visible(array $contrat, string $acteur): bool
    {
        if ($contrat['version_active'] !== null) {
            return true;
        }

        return $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
            || $acteur === $contrat['proprietaire_reference'];
    }

    private function registre(): RegistreContrats
    {
        $index = Db::connect();
        $registreIdentites = IdentiteMagasin::connecter();

        return new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), new Ctr01($index, $registreIdentites));
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
                'message' => 'Le registre des contrats est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
