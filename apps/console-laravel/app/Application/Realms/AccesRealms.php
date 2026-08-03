<?php

declare(strict_types=1);

namespace App\Application\Realms;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\PolitiqueRealms;
use Gamad\RegistreRealms\RegistreRealms;

/**
 * Cas d'usage du registre des realms (CAP-CORE-012).
 *
 * Même chemin gouverné que les autres registres persistants du Core :
 * `CAP-CORE-004` décide, `CAP-CORE-013` conserve la preuve, et seule une
 * décision permise et prouvée atteint l'écriture. `Ctr03` évalue
 * `POL-REALMS-V1` en lisant le registre des politiques (CAP-CORE-007), pas
 * ce module. Cette classe ne recopie jamais une donnée d'organisation, de
 * produit ou de contrat : elle référence seulement leurs identifiants.
 */
final class AccesRealms
{
    /** @return array{statut:int,corps:array<string,mixed>} */
    public function lister(string $acteur, array $filtres = []): array
    {
        try {
            $tous = $this->registre()->listerRealms($filtres);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        $visibles = array_values(array_filter($tous, fn (array $r): bool => $this->visible($r, $acteur)));

        return ['statut' => 200, 'corps' => ['realms' => $visibles]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudre(string $reference, string $acteur): array
    {
        try {
            $realm = $this->registre()->resoudreRealm($reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }
        if ($realm === null || !$this->visible($realm, $acteur)) {
            return ['statut' => 404, 'corps' => ['erreur' => 'REALM_INTROUVABLE']];
        }

        return ['statut' => 200, 'corps' => ['realm' => $realm]];
    }

    /**
     * @param callable(RegistreRealms,string):array $lecture
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function sousRessource(string $reference, string $acteur, string $cle, callable $lecture): array
    {
        $visible = $this->resoudre($reference, $acteur);
        if ($visible['statut'] !== 200) {
            return $visible;
        }
        try {
            $valeur = $lecture($this->registre(), $reference);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => [$cle => $valeur]];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreHistorique(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'historique', fn (RegistreRealms $r, string $ref): array => $r->resoudreHistorique($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreRelations(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'relations', fn (RegistreRealms $r, string $ref): array => $r->resoudreRelations($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreParents(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'parents', fn (RegistreRealms $r, string $ref): array => $r->resoudreParents($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreEnfants(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'enfants', fn (RegistreRealms $r, string $ref): array => $r->resoudreEnfants($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudrePerimetres(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'perimetres', fn (RegistreRealms $r, string $ref): array => $r->resoudrePerimetres($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreIdentifiantsExternes(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'identifiants_externes', fn (RegistreRealms $r, string $ref): array => $r->resoudreIdentifiantsExternes($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreOrganisations(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'organisations', fn (RegistreRealms $r, string $ref): array => $r->resoudreOrganisations($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreProduits(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'produits', fn (RegistreRealms $r, string $ref): array => $r->resoudreProduits($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreContrats(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'contrats', fn (RegistreRealms $r, string $ref): array => $r->resoudreContrats($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreFranchissements(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'franchissements', fn (RegistreRealms $r, string $ref): array => $r->resoudreFranchissements($ref));
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function resoudreVerification(string $reference, string $acteur): array
    {
        return $this->sousRessource($reference, $acteur, 'verification', fn (RegistreRealms $r, string $ref): mixed => $r->resoudreVerificationCourante($ref));
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function verifierPortee(string $reference, array $donnees, string $acteur): array
    {
        $donnees['realm'] = $reference;
        // La vérification de portée est toujours ancrée sur le realm de la
        // ressource appelée : lorsqu'un `realm_cible` est fourni sans
        // `realm_source` explicite, la frontière vérifiée part de ce realm.
        if (($donnees['realm_cible'] ?? null) !== null && ($donnees['realm_source'] ?? null) === null) {
            $donnees['realm_source'] = $reference;
        }
        try {
            $resultat = $this->registre()->verifierPortee($donnees);
        } catch (\Throwable) {
            return $this->socleIndisponible();
        }

        return ['statut' => 200, 'corps' => $resultat];
    }

    /** @param array<string,mixed> $donnees @return array{statut:int,corps:array<string,mixed>} */
    public function inscrire(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_INSCRIRE, $donnees['identite_reference'] ?? null, $acteur, $correlation, 'REALM_INSCRIT',
            fn (RegistreRealms $registre, array $dossier): array => $registre->inscrireRealm($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function modifier(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_MODIFIER, $reference, $acteur, $correlation, 'REALM_MODIFIE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->modifierRealm($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function activer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_ACTIVER, $reference, $acteur, $correlation, 'REALM_ACTIVE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->activerRealm($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function suspendre(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_SUSPENDRE, $reference, $acteur, $correlation, 'REALM_SUSPENDU',
            fn (RegistreRealms $registre, array $dossier): array => $registre->suspendreRealm($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_FERMER, $reference, $acteur, $correlation, 'REALM_FERME',
            fn (RegistreRealms $registre, array $dossier): array => $registre->fermerRealm($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function retirer(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_RETIRER, $reference, $acteur, $correlation, 'REALM_RETIRE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->retirerRealm($reference, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerRelation(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_RELATION_DECLARER, $donnees['realm_source_reference'] ?? null, $acteur, $correlation, 'RELATION_REALM_DECLAREE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->declarerRelation($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerRelation(string $relation, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_RELATION_FERMER, $relation, $acteur, $correlation, 'RELATION_REALM_FERMEE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->fermerRelation($relation, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerPerimetre(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_PERIMETRE_DECLARER, $reference, $acteur, $correlation, 'PERIMETRE_REALM_DECLARE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->declarerPerimetre($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerPerimetre(string $reference, int $id, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_PERIMETRE_FERMER, $reference, $acteur, $correlation, 'PERIMETRE_REALM_FERME',
            fn (RegistreRealms $registre, array $dossier): array => $registre->fermerPerimetre($id, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerIdentifiant(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_IDENTIFIANT_DECLARER, $reference, $acteur, $correlation, 'IDENTIFIANT_REALM_DECLARE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->declarerIdentifiantExterne($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function rattacherOrganisation(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_ORGANISATION_RATTACHER, $reference, $acteur, $correlation, 'ORGANISATION_REALM_RATTACHEE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->rattacherOrganisation($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function detacherOrganisation(string $rattachement, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_ORGANISATION_DETACHER, $rattachement, $acteur, $correlation, 'ORGANISATION_REALM_DETACHEE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->detacherOrganisation($rattachement, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function rattacherProduit(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_PRODUIT_RATTACHER, $reference, $acteur, $correlation, 'PRODUIT_REALM_RATTACHE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->rattacherProduit($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function detacherProduit(string $rattachement, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_PRODUIT_DETACHER, $rattachement, $acteur, $correlation, 'PRODUIT_REALM_DETACHE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->detacherProduit($rattachement, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function rattacherContrat(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_CONTRAT_RATTACHER, $reference, $acteur, $correlation, 'CONTRAT_REALM_RATTACHE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->rattacherContrat($reference, $dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function detacherContrat(int $id, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_CONTRAT_DETACHER, (string) $id, $acteur, $correlation, 'CONTRAT_REALM_DETACHE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->detacherContrat($id, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function declarerFranchissement(array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_FRANCHISSEMENT_DECLARER, $donnees['realm_source_reference'] ?? null, $acteur, $correlation, 'FRANCHISSEMENT_REALM_DECLARE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->declarerFranchissement($dossier),
            $donnees, 201,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function fermerFranchissement(int $id, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_FRANCHISSEMENT_FERMER, (string) $id, $acteur, $correlation, 'FRANCHISSEMENT_REALM_FERME',
            fn (RegistreRealms $registre, array $dossier): array => $registre->fermerFranchissement($id, $dossier),
            $donnees, 200,
        );
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    public function enregistrerVerification(string $reference, array $donnees, string $acteur, ?string $correlation = null): array
    {
        return $this->executer(
            PolitiqueRealms::ACTION_VERIFICATION_ENREGISTRER, $reference, $acteur, $correlation, 'VERIFICATION_REALM_ENREGISTREE',
            fn (RegistreRealms $registre, array $dossier): array => $registre->enregistrerVerification($reference, $dossier),
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
                'categorie' => 'REALMS',
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
                'categorie' => 'REALMS', 'type' => 'OPERATION_REALM_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => 'autorisation refusée',
                'correlation_id' => $preuve['correlation_id'],
            ]);

            return ['statut' => 403, 'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve]];
        }

        $dossier = array_merge($donnees, [
            'politique' => $decision['politique'] ?? PolitiqueRealms::POLITIQUE,
            'source' => PolitiqueRealms::SOURCE,
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
                    'erreur' => 'REGISTRE_REALMS_INDISPONIBLE',
                    'message' => 'L’intention est tracée, mais aucune écriture n’a été confirmée.',
                    'preuve' => $preuve,
                ],
            ];
        }

        if (isset($resultat['refus'])) {
            $this->tracer($journal, [
                'categorie' => 'REALMS', 'type' => 'OPERATION_REALM_REFUSEE',
                'acteur' => $acteur, 'action' => $action, 'ressource' => $ressource,
                'decision' => 'REFUSEE', 'motif' => $resultat['detail'] ?? $resultat['refus'],
                'correlation_id' => $preuve['correlation_id'], 'donnees' => ['refus' => $resultat['refus']],
            ]);

            $statut = match ($resultat['refus']) {
                'REALM_INCONNU', 'IDENTITE_INCONNUE', 'ORGANISATION_INCONNUE', 'PRODUIT_INCONNU',
                'CONTRAT_INCONNU', 'RELATION_INCONNUE', 'PERIMETRE_INCONNU', 'IDENTIFIANT_INCONNU',
                'RATTACHEMENT_INCONNU', 'FRANCHISSEMENT_INCONNU' => 404,
                'REFERENCE_DEJA_UTILISEE', 'IDENTITE_DEJA_LIEE', 'CODE_DEJA_UTILISE',
                'IDENTIFIANT_DEJA_DECLARE', 'CYCLE_HIERARCHIQUE_DETECTE', 'ETAT_INCOMPATIBLE' => 409,
                'DEPENDANCE_INDISPONIBLE' => 503,
                default => 422,
            };

            return ['statut' => $statut, 'corps' => ['erreur' => 'OPERATION_REFUSEE', 'resultat' => $resultat, 'preuve' => $preuve]];
        }

        $preuveOperation = $this->tracer($journal, [
            'categorie' => 'REALMS', 'type' => $typeEvenementReussite,
            'acteur' => $acteur, 'action' => $action,
            'ressource' => $ressource ?? (string) ($resultat['reference'] ?? $resultat['id'] ?? ''),
            'decision' => 'EXECUTEE', 'correlation_id' => $preuve['correlation_id'], 'donnees' => $resultat,
        ]) ?? $preuve;

        return ['statut' => $statutReussite, 'corps' => ['resultat' => $resultat, 'decision' => $decision, 'preuve' => $preuveOperation]];
    }

    /** @param array<string,mixed> $realm */
    private function visible(array $realm, string $acteur): bool
    {
        if (($realm['etat'] ?? null) === 'ACTIF') {
            return true;
        }

        return $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
    }

    private function registre(): RegistreRealms
    {
        $index = Db::connect();
        $registreIdentites = IdentiteMagasin::connecter();
        $ctr01 = new Ctr01($index, $registreIdentites);
        $organisations = new RegistreOrganisations($index, $registreIdentites, OrganisationsMagasin::connecter(), $ctr01);
        $produits = new RegistreProduits($index, $registreIdentites, ProduitsMagasin::connecter(), $ctr01);
        $contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);

        return new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01, $organisations, $produits, $contrats);
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
                'message' => 'Le registre des realms est fermé car sa décision et sa preuve ne peuvent pas être établies.',
            ],
        ];
    }
}
