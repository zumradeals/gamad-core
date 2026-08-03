<?php

declare(strict_types=1);

namespace App\Application\Organisations;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;

/**
 * Cas d'usage du registre des organisations (CAP-CORE-002).
 *
 * Même chemin gouverné que les autres registres persistants du Core :
 * `CAP-CORE-004` décide, `CAP-CORE-013` conserve la preuve, et seule une
 * décision permise et prouvée atteint l'écriture. `Ctr03` évalue
 * `POL-ORGANISATIONS-V1` en lisant le registre des politiques
 * (CAP-CORE-007), pas ce module.
 */
final class AccesOrganisations
{
    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(string $acteur): array
    {
        try {
            $tous = $this->registre()->listerOrganisations();
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        $visibles = array_values(array_filter($tous, fn (array $o): bool => $this->visible($o, $acteur)));

        return ['statut' => 200, 'corps' => ['organisations' => $visibles]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        try {
            $organisation = $this->registre()->resoudreOrganisation($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($organisation === null || !$this->visible($organisation, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'ORGANISATION_INTROUVABLE']];
        }

        try {
            $historique = $this->registre()->resoudreHistorique($reference);
            $identifiants = $this->registre()->resoudreIdentifiants($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['organisation' => $organisation, 'historique' => $historique, 'identifiants' => $identifiants]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreStructure(string $reference, string $acteur): array
    {
        $visible = $this->resoudre($reference, $acteur);
        if ($visible['statut'] !== 200) {
            return $visible;
        }
        try {
            $structure = $this->registre()->resoudreStructure($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['structure' => $structure]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreRelations(string $reference, string $acteur): array
    {
        $visible = $this->resoudre($reference, $acteur);
        if ($visible['statut'] !== 200) {
            return $visible;
        }
        try {
            $relations = $this->registre()->resoudreRelations($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['relations' => $relations]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreAffiliations(string $reference, string $acteur, array $filtres = []): array
    {
        $visible = $this->resoudre($reference, $acteur);
        if ($visible['statut'] !== 200) {
            return $visible;
        }
        if ($acteur !== PolitiqueInscription::AUTORITE_INSCRIPTION && $acteur !== ($visible['corps']['organisation']['proprietaire_reference'] ?? null)) {
            // Aucune liste complète d'affiliés sans contrat et autorisation (fiche §22).
            return ['statut' => 403, 'corps' => ['erreur' => 'ACCES_REFUSE', 'message' => 'la liste des affiliations n’est pas publique']];
        }
        try {
            $affiliations = $this->registre()->resoudreAffiliationsOrganisation($reference, $filtres);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['affiliations' => $affiliations]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreAffiliationsIdentite(string $identite, string $acteur, array $filtres = []): array
    {
        if ($acteur !== PolitiqueInscription::AUTORITE_INSCRIPTION && $acteur !== $identite) {
            return ['statut' => 403, 'corps' => ['erreur' => 'ACCES_REFUSE', 'message' => 'seule l’identité concernée ou l’autorité consulte ses affiliations']];
        }
        try {
            $affiliations = $this->registre()->resoudreAffiliationsIdentite($identite, $filtres);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['affiliations' => $affiliations]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreFonctions(string $reference, string $acteur): array
    {
        $visible = $this->resoudre($reference, $acteur);
        if ($visible['statut'] !== 200) {
            return $visible;
        }
        try {
            $fonctions = $this->registre()->resoudreFonctions($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => ['fonctions' => $fonctions]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifierAppartenance(string $reference, array $donnees, string $acteur): array
    {
        $identite = (string) ($donnees['identite_reference'] ?? '');
        if ($identite === '') {
            return ['statut' => 422, 'corps' => ['erreur' => 'IDENTITE_REQUISE']];
        }
        try {
            $resultat = $this->registre()->verifierAppartenance($identite, $reference, $donnees['type'] ?? null, $donnees['date'] ?? null);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => $resultat];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function verifierRepresentation(string $reference, array $donnees, string $acteur): array
    {
        $identite = (string) ($donnees['identite_reference'] ?? '');
        if ($identite === '') {
            return ['statut' => 422, 'corps' => ['erreur' => 'IDENTITE_REQUISE']];
        }
        try {
            $resultat = $this->registre()->verifierRepresentation($identite, $reference, $donnees['action'] ?? null, $donnees['date'] ?? null);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => $resultat];
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function inscrire(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_INSCRIRE, $donnees['identite_reference'] ?? null, $acteur, $correlation, 'ORGANISATION_INSCRITE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->inscrireOrganisation($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function modifier(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_MODIFIER, $reference, $acteur, $correlation, 'ORGANISATION_MODIFIEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->modifierOrganisation($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_ACTIVER, $reference, $acteur, $correlation, 'ORGANISATION_ACTIVEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->activerOrganisation($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendre(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_SUSPENDRE, $reference, $acteur, $correlation, 'ORGANISATION_SUSPENDUE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->suspendreOrganisation($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function dissoudre(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_DISSOUDRE, $reference, $acteur, $correlation, 'ORGANISATION_DISSOUTE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->dissoudreOrganisation($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_RETIRER, $reference, $acteur, $correlation, 'ORGANISATION_RETIREE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->retirerOrganisation($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerIdentifiant(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_IDENTIFIANT_DECLARER, $reference, $acteur, $correlation, 'IDENTIFIANT_ORGANISATION_DECLARE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->declarerIdentifiantExterne($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerIdentifiant(string $reference, int $id, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_IDENTIFIANT_FERMER, $reference, $acteur, $correlation, 'IDENTIFIANT_ORGANISATION_FERME',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->fermerIdentifiantExterne($id, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function creerUnite(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_UNITE_CREER, $reference, $acteur, $correlation, 'UNITE_ORGANISATION_CREEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->creerUnite($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function deplacerUnite(string $unite, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_UNITE_MODIFIER, $unite, $acteur, $correlation, 'UNITE_ORGANISATION_DEPLACEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->deplacerUnite($unite, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerUnite(string $unite, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_UNITE_FERMER, $unite, $acteur, $correlation, 'UNITE_ORGANISATION_FERMEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->fermerUnite($unite, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerRelation(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_RELATION_DECLARER, $donnees['organisation_source_reference'] ?? null, $acteur, $correlation, 'RELATION_ORGANISATION_DECLAREE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->declarerRelationOrganisationnelle($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerRelation(string $relation, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_RELATION_FERMER, $relation, $acteur, $correlation, 'RELATION_ORGANISATION_FERMEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->fermerRelationOrganisationnelle($relation, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function proposerAffiliation(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_AFFILIATION_PROPOSER, $donnees['organisation_reference'] ?? null, $acteur, $correlation, 'AFFILIATION_ORGANISATION_PROPOSEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->proposerAffiliation($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activerAffiliation(string $affiliation, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_AFFILIATION_ACTIVER, $affiliation, $acteur, $correlation, 'AFFILIATION_ORGANISATION_ACTIVEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->activerAffiliation($affiliation, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendreAffiliation(string $affiliation, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_AFFILIATION_SUSPENDRE, $affiliation, $acteur, $correlation, 'AFFILIATION_ORGANISATION_SUSPENDUE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->suspendreAffiliation($affiliation, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerAffiliation(string $affiliation, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_AFFILIATION_FERMER, $affiliation, $acteur, $correlation, 'AFFILIATION_ORGANISATION_FERMEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->fermerAffiliation($affiliation, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function creerFonction(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueOrganisations::ACTION_FONCTION_CREER, $reference, $acteur, $correlation, 'FONCTION_ORGANISATION_CREEE',
            fn (RegistreOrganisations $registre, array $dossier): array => $registre->creerFonctionInterne($reference, $dossier),
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
                'categorie' => 'ORGANISATIONS',
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
                'categorie' => 'ORGANISATIONS', 'type' => 'OPERATION_ORGANISATION_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => 'autorisation refusée',
                'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        $dossier = array_merge($donnees, [
            'politique' => $decision['politique'] ?? PolitiqueOrganisations::POLITIQUE,
            'source' => PolitiqueOrganisations::SOURCE,
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
                    'erreur' => 'REGISTRE_ORGANISATIONS_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'ORGANISATIONS', 'type' => 'OPERATION_ORGANISATION_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'], 'donnees' => ['refus' => $resultat['refus']],
            ]);

            $statut = match ($resultat['refus']) {
                'ORGANISATION_INCONNUE', 'IDENTITE_INCONNUE', 'UNITE_INCONNUE', 'AFFILIATION_INCONNUE',
                'RELATION_INCONNUE', 'IDENTIFIANT_INCONNU' => 404,
                'REFERENCE_DEJA_UTILISEE', 'IDENTITE_DEJA_LIEE', 'IDENTIFIANT_DEJA_DECLARE' => 409,
                default => 422,
            };

            return ['statut' => $statut, 'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve]];
        }

        $preuveOperation = $this->tracer($journal, [
            'categorie' => 'ORGANISATIONS', 'type' => $typeEvenementReussite,
            'acteur' => $acteur, 'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? ''),
            'decision' => 'EXECUTEE', 'correlation_id' => $preuve['correlation_id'], 'donnees' => $resultat,
        ]) ?? $preuve;

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuveOperation]];
    }

    /** @param array<string,mixed> $organisation */
    private function visible(array $organisation, string $acteur): bool
    {
        if (($organisation['etat'] ?? null) === 'ACTIVE') {
            return true;
        }

        return $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
            || $acteur === ($organisation['proprietaire_reference'] ?? null);
    }

    private function registre(): RegistreOrganisations
    {
        $index = Db::connect();
        $registreIdentites = IdentiteMagasin::connecter();
        $ctr01 = new Ctr01($index, $registreIdentites);
        // CAP-CORE-003 (core/registre-autorites) n'a pas de magasin persistant
        // distinct : Ctr02 lit directement l'index reconstructible partagé,
        // comme le fait sa propre garde (core/registre-autorites/tests/mandat_p3.php).
        try {
            $ctr02 = new Ctr02($index);
        } catch (\Throwable) {
            // CAP-CORE-003 indisponible : la représentation reste fermée
            // (verifierRepresentation renvoie MANDAT_INDISPONIBLE) plutôt que
            // de faire échouer toute la lecture du registre.
            $ctr02 = null;
        }

        return new RegistreOrganisations($index, $registreIdentites, OrganisationsMagasin::connecter(), $ctr01, $ctr02);
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
                'message' => 'Le registre des organisations est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
