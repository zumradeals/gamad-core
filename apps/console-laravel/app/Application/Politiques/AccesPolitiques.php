<?php

declare(strict_types=1);

namespace App\Application\Politiques;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;

/**
 * Cas d'usage du registre des politiques (CAP-CORE-007).
 *
 * Le module Core décrit ce qu'est une politique gouvernée ; cette couche
 * l'insère dans le parcours réel : CAP-CORE-004 décide, CAP-CORE-013 conserve
 * la preuve, et seule une décision permise et prouvée atteint l'écriture.
 *
 * Particularité de cette capacité : `CTR-03`, le moteur qui rend la décision,
 * lit lui-même ce registre. Administrer une politique passe donc par le même
 * chemin gouverné que n'importe quelle autre écriture — y compris la
 * politique `POL-POLITIQUES-V1` qui gouverne ce registre lui-même.
 */
final class AccesPolitiques
{
    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        try {
            $politique = $this->registre()->resoudrePolitique($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        if ($politique === null || !$this->visible($politique, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'POLITIQUE_INTROUVABLE']];
        }

        try {
            $versions = $this->registre()->listerVersions($reference);
            $historique = $this->registre()->resoudreHistorique($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return [
            'statut' => 200,
            'corps' => ['politique' => $politique, 'versions' => $versions, 'historique' => $historique],
        ];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(string $acteur): array
    {
        try {
            $tous = $this->registre()->listerPolitiques();
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        $visibles = array_values(array_filter($tous, fn (array $p): bool => $this->visible($p, $acteur)));

        return ['statut' => 200, 'corps' => ['politiques' => $visibles]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreVersion(string $reference, string $version, string $acteur): array
    {
        try {
            $politique = $this->registre()->resoudrePolitique($reference);
            if ($politique === null || !$this->visible($politique, $acteur)) {
                return ['statut' => 404, 'corps' => ['erreur' => 'POLITIQUE_INTROUVABLE']];
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

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function inscrire(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_INSCRIRE,
            $donnees['reference'] ?? null,
            $acteur, $correlation, 'POLITIQUE_INSCRITE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->inscrirePolitique($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function creerVersion(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_VERSION_CREER,
            $reference, $acteur, $correlation, 'VERSION_POLITIQUE_CREEE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->creerVersion($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function ajouterRegle(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_VERSION_MODIFIER,
            $reference, $acteur, $correlation, 'REGLE_POLITIQUE_AJOUTEE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->ajouterRegle($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function modifierRegle(string $reference, string $version, int $regleId, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_VERSION_MODIFIER,
            $reference, $acteur, $correlation, 'REGLE_POLITIQUE_MODIFIEE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->modifierRegle($reference, $version, $regleId, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function soumettreVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_VERSION_SOUMETTRE,
            $reference, $acteur, $correlation, 'VERSION_POLITIQUE_SOUMISE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->soumettreVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function simulerVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_VERSION_SIMULER,
            $reference, $acteur, $correlation, 'VERSION_POLITIQUE_SIMULEE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->simulerVersion($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activerVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_VERSION_ACTIVER,
            $reference, $acteur, $correlation, 'VERSION_POLITIQUE_ACTIVEE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->activerVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendreVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_VERSION_SUSPENDRE,
            $reference, $acteur, $correlation, 'VERSION_POLITIQUE_SUSPENDUE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->suspendreVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueAdministration::ACTION_RETIRER,
            $reference, $acteur, $correlation, 'POLITIQUE_RETIREE',
            fn (RegistrePolitiques $registre, array $dossier): array => $registre->retirerPolitique($reference, $dossier),
            $donnees, 200,
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
                'categorie' => 'POLITIQUES',
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
                'categorie' => 'POLITIQUES',
                'type' => 'OPERATION_POLITIQUE_REFUSEE',
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

        $dossier = array_merge($donnees, [
            'politique' => $decision['politique'] ?? PolitiqueAdministration::POLITIQUE,
            'source' => PolitiqueAdministration::SOURCE,
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
                    'erreur' => 'REGISTRE_POLITIQUES_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'POLITIQUES',
                'type' => 'OPERATION_POLITIQUE_REFUSEE',
                'acteur' => $acteur,
                'action' => $action,
                'ressource' => $ressource,
                'decision' => 'REFUSEE',
                'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'],
                'donnees' => ['refus' => $resultat['refus']],
            ]);

            $statut = match ($resultat['refus']) {
                'POLITIQUE_INCONNUE', 'VERSION_INCONNUE', 'PROPRIETAIRE_INCONNU', 'REGLE_INCONNUE' => 404,
                'REFERENCE_DEJA_UTILISEE', 'VERSION_DEJA_UTILISEE', 'ORDRE_DEJA_UTILISE' => 409,
                default => 422,
            };

            return ['statut' => $statut, 'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve]];
        }

        $preuveOperation = $this->tracer($journal, [
            'categorie' => 'POLITIQUES',
            'type' => $typeEvenementReussite,
            'acteur' => $acteur,
            'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? $resultat['politique_reference'] ?? ''),
            'decision' => 'EXECUTEE',
            'correlation_id' => $preuve['correlation_id'],
            'donnees' => $resultat,
        ]) ?? $preuve;

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuveOperation]];
    }

    /** @param array<string,mixed> $politique */
    private function visible(array $politique, string $acteur): bool
    {
        if ($politique['version_active'] !== null) {
            return true;
        }

        return $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
            || $acteur === $politique['proprietaire_reference'];
    }

    private function registre(): RegistrePolitiques
    {
        $index = Db::connect();
        $registreIdentites = IdentiteMagasin::connecter();

        return new RegistrePolitiques(
            $index,
            $registreIdentites,
            PolitiquesMagasin::connecter(),
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
                'message' => 'Le registre des politiques est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
