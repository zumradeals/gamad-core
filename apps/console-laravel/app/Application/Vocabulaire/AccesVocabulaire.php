<?php

declare(strict_types=1);

namespace App\Application\Vocabulaire;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Gamad\RegistreVocabulaire\PolitiqueVocabulaire;
use Gamad\RegistreVocabulaire\RegistreVocabulaire;

/**
 * Cas d'usage du registre du vocabulaire canonique (CAP-CORE-010).
 *
 * Même chemin gouverné que les autres registres persistants : CAP-CORE-004
 * décide, CAP-CORE-013 conserve la preuve, et seule une décision permise et
 * prouvée atteint l'écriture. `Ctr03` évalue `POL-VOCABULAIRE-V1` en lisant
 * le registre des politiques (CAP-CORE-007), pas ce module.
 */
final class AccesVocabulaire
{
    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        try {
            $vocabulaire = $this->registre()->resoudreVocabulaire($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($vocabulaire === null || !$this->visible($vocabulaire, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'VOCABULAIRE_INTROUVABLE']];
        }
        try {
            $versions = $this->registre()->listerVersions($reference);
            $historique = $this->registre()->resoudreHistorique($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['vocabulaire' => $vocabulaire, 'versions' => $versions, 'historique' => $historique]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(string $acteur): array
    {
        try {
            $tous = $this->registre()->listerVocabulaires();
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        $visibles = array_values(array_filter($tous, fn (array $v): bool => $this->visible($v, $acteur)));

        return ['statut' => 200, 'corps' => ['vocabulaires' => $visibles]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreVersion(string $reference, string $version, string $acteur): array
    {
        try {
            $vocabulaire = $this->registre()->resoudreVocabulaire($reference);
            if ($vocabulaire === null || !$this->visible($vocabulaire, $acteur)) {
                return ['statut' => 404, 'corps' => ['erreur' => 'VOCABULAIRE_INTROUVABLE']];
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
    public function resoudreVersionActive(string $reference, string $acteur): array
    {
        try {
            $vocabulaire = $this->registre()->resoudreVocabulaire($reference);
            if ($vocabulaire === null || !$this->visible($vocabulaire, $acteur)) {
                return ['statut' => 404, 'corps' => ['erreur' => 'VOCABULAIRE_INTROUVABLE']];
            }
            $v = $this->registre()->resoudreVersionActive($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($v === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'AUCUNE_VERSION_ACTIVE']];
        }

        return ['statut' => 200, 'corps' => ['version' => $v]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreTerme(string $reference, string $acteur): array
    {
        try {
            $terme = $this->registre()->resoudreTerme($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($terme === null) {
            return ['statut' => 404, 'corps' => ['erreur' => 'TERME_INTROUVABLE']];
        }

        return ['statut' => 200, 'corps' => ['terme' => $terme]];
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

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function inscrire(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_INSCRIRE, $donnees['reference'] ?? null, $acteur, $correlation, 'VOCABULAIRE_INSCRIT',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->inscrireVocabulaire($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function creerVersion(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_VERSION_CREER, $reference, $acteur, $correlation, 'VERSION_VOCABULAIRE_CREEE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->creerVersion($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function ajouterTerme(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_TERME_AJOUTER, $reference, $acteur, $correlation, 'TERME_AJOUTE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->ajouterTerme($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function evoluerTerme(string $ancienneReference, string $nouvelleVersion, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_TERME_EVOLUER, $ancienneReference, $acteur, $correlation, 'TERME_EVOLUE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->evoluerTerme($ancienneReference, $nouvelleVersion, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function ajouterLibelle(string $termeReference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_TERME_MODIFIER, $termeReference, $acteur, $correlation, 'LIBELLE_TERME_AJOUTE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->ajouterLibelle($termeReference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function ajouterAlias(string $termeReference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_ALIAS_AJOUTER, $termeReference, $acteur, $correlation, 'ALIAS_TERME_AJOUTE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->ajouterAlias($termeReference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerRelation(string $termeSource, string $termeCible, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_TERME_MODIFIER, $termeSource, $acteur, $correlation, 'RELATION_TERME_DECLAREE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->declarerRelation($termeSource, $termeCible, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerMappingExterne(string $termeReference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_MAPPING_AJOUTER, $termeReference, $acteur, $correlation, 'MAPPING_TERME_DECLARE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->declarerMappingExterne($termeReference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerUsage(string $termeReference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_USAGE_DECLARER, $termeReference, $acteur, $correlation, 'USAGE_TERME_DECLARE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->declarerUsage($termeReference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function soumettreVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_VERSION_SOUMETTRE, $reference, $acteur, $correlation, 'VERSION_VOCABULAIRE_SOUMISE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->soumettreVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function analyserCompatibilite(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_VERSION_ANALYSER, $reference, $acteur, $correlation, 'COMPATIBILITE_VOCABULAIRE_ANALYSEE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->analyserCompatibilite($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function genererProjection(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_PROJECTION_GENERER, $reference, $acteur, $correlation, 'PROJECTION_VOCABULAIRE_GENEREE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->genererProjection($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function enregistrerConformite(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_CONFORMITE_ENREGISTRER, $reference, $acteur, $correlation, 'CONFORMITE_VOCABULAIRE_ENREGISTREE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->enregistrerConformite($reference, $version, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activerVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_VERSION_ACTIVER, $reference, $acteur, $correlation, 'VERSION_VOCABULAIRE_ACTIVEE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->activerVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function deprecierVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_VERSION_DEPRECIER, $reference, $acteur, $correlation, 'VERSION_VOCABULAIRE_DEPRECIEE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->deprecierVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirerVersion(string $reference, string $version, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_VERSION_RETIRER, $reference, $acteur, $correlation, 'VERSION_VOCABULAIRE_RETIREE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->retirerVersion($reference, $version, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function deprecierTerme(string $termeReference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_TERME_MODIFIER, $termeReference, $acteur, $correlation, 'TERME_DEPRECIE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->deprecierTerme($termeReference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirerTerme(string $termeReference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueVocabulaire::ACTION_VERSION_RETIRER, $termeReference, $acteur, $correlation, 'TERME_RETIRE',
            fn (RegistreVocabulaire $registre, array $dossier): array => $registre->retirerTerme($termeReference, $dossier),
            $donnees, 200,
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
                'categorie' => 'VOCABULAIRE',
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
                'categorie' => 'VOCABULAIRE', 'type' => 'OPERATION_VOCABULAIRE_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => 'autorisation refusée', 'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        $dossier = array_merge($donnees, [
            'politique' => $decision['politique'] ?? PolitiqueVocabulaire::POLITIQUE,
            'source' => PolitiqueVocabulaire::SOURCE,
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
                    'erreur' => 'REGISTRE_VOCABULAIRE_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'VOCABULAIRE', 'type' => 'OPERATION_VOCABULAIRE_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'], 'donnees' => ['refus' => $resultat['refus']],
            ]);

            $statut = match ($resultat['refus']) {
                'VOCABULAIRE_INCONNU', 'VERSION_INCONNUE', 'PROPRIETAIRE_INCONNU', 'TERME_INCONNU', 'TERME_SOURCE_INCONNU', 'TERME_CIBLE_INCONNU' => 404,
                'REFERENCE_DEJA_UTILISEE', 'NAMESPACE_DEJA_UTILISE', 'VERSION_DEJA_UTILISEE', 'TERME_REFERENCE_DEJA_UTILISEE' => 409,
                default => 422,
            };

            return ['statut' => $statut, 'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve]];
        }

        $preuveOperation = $this->tracer($journal, [
            'categorie' => 'VOCABULAIRE', 'type' => $typeEvenementReussite,
            'acteur' => $acteur, 'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? $resultat['vocabulaire_reference'] ?? ''),
            'decision' => 'EXECUTEE', 'correlation_id' => $preuve['correlation_id'], 'donnees' => $resultat,
        ]) ?? $preuve;

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuveOperation]];
    }

    /** @param array<string,mixed> $vocabulaire */
    private function visible(array $vocabulaire, string $acteur): bool
    {
        if ($vocabulaire['version_active'] !== null) {
            return true;
        }

        return $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
            || $acteur === $vocabulaire['proprietaire_reference'];
    }

    private function registre(): RegistreVocabulaire
    {
        $index = Db::connect();
        $registreIdentites = IdentiteMagasin::connecter();

        return new RegistreVocabulaire($index, $registreIdentites, VocabulaireMagasin::connecter(), new Ctr01($index, $registreIdentites));
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
                'message' => 'Le registre du vocabulaire est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
