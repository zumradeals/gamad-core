<?php

declare(strict_types=1);

namespace Gamad\MoteurMatching;

use Gamad\EvenementsSortants\OutboxProducteur;

/**
 * Orchestration persistante de CAP-CORE-021 : relie les classes pures du
 * moteur (`Apparieur`, `Classement`, `Segments`, `Activation`, `Explication`,
 * `Mesure`, `Contestations`, `ResolutionSources`, `EvaluateurDeterministe`,
 * `CompilateurPolitique`) au magasin (doc de chantier 02 et 03).
 *
 * Réserve explicite, faute d'intégration câblée dans ce chantier (à
 * documenter dans le rapport d'admission, jamais dissimulée) : cette classe
 * ne contacte jamais elle-même CAP-CORE-004, CAP-CORE-006, CAP-CORE-008,
 * CAP-CORE-011, CAP-CORE-012, CAP-CORE-017 ou CAP-CORE-018. Les décisions qui
 * en dépendent (autorisation CTR-03, statut de source, statut de produit,
 * statut de contrat, risque ou incident bloquant, décision formelle) sont
 * des « faits » fournis explicitement par l'appelant (la façade HTTP ou la
 * commande d'exploitation), jamais devinés ni inventés ici — même précédent
 * que les docblocs de `Activation`, `Mesure`, `ResolutionSources` et
 * `Contestations`, qui documentent cette même frontière côté fonctions
 * pures. Câbler ces appels réels est un chantier ultérieur non bloquant.
 *
 * Une exécution terminée est immuable ; un réexamen ne réécrit jamais un
 * ancien résultat, il produit un nouveau constat (doc 02 §15, §24).
 *
 * Câblage partiel introduit après le premier commit de cette classe : le
 * statut réel d'un produit (CAP-CORE-011), d'une version de contrat
 * (CAP-CORE-009) et d'une source (CAP-CORE-006) peut être vérifié via des
 * résolveurs injectés (fonctions pures `fn(...): bool`), sans que cette
 * classe importe elle-même ces registres — l'appelant (`AccesMatching`)
 * reste seul responsable de construire un résolveur véridique. Sans
 * résolveur fourni, le comportement par défaut est permissif (`true`) pour
 * préserver la compatibilité des tests existants (`matching_p4.php`) qui ne
 * câblent aucun autre magasin — **ce défaut n'est pas sûr pour la
 * production** et ne doit jamais être utilisé hors test.
 */
final class RegistreMatching
{
    public function __construct(
        private \PDO $magasin,
        private ?\Closure $resolveurProduitActif = null,
        private ?\Closure $resolveurContratActif = null,
        private ?\Closure $resolveurSourceActive = null,
    ) {
    }

    private function produitActif(string $reference): bool
    {
        return $this->resolveurProduitActif !== null ? (bool) ($this->resolveurProduitActif)($reference) : true;
    }

    private function contratActif(string $reference, string $version): bool
    {
        return $this->resolveurContratActif !== null ? (bool) ($this->resolveurContratActif)($reference, $version) : true;
    }

    private function sourceActive(string $reference): bool
    {
        return $this->resolveurSourceActive !== null ? (bool) ($this->resolveurSourceActive)($reference) : true;
    }

    // ==================================================================
    // Contextes (doc 02 §3)

    /** @param array<string,mixed> $donnees @return array{refus:string,detail:string}|array<string,mixed> */
    public function inscrireContexte(array $donnees, string $acteur): array
    {
        $code = (string) ($donnees['code_canonique'] ?? '');
        if (!in_array($code, PolitiqueMatching::CONTEXTES_INITIAUX, true)) {
            return $this->refus('MATCHING_CONTEXT_UNKNOWN', "code_canonique hors liste close initiale : {$code}");
        }
        foreach (['nom', 'finalite', 'politique_reference', 'politique_version', 'classification', 'supervision_humaine', 'source_reference'] as $champ) {
            if (trim((string) ($donnees[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ obligatoire absent : {$champ}");
            }
        }
        $existant = $this->ligne('SELECT reference FROM matching_contexte WHERE code_canonique = ?', [$code]);
        if ($existant !== null) {
            return $this->refus('MATCHING_CONTEXT_UNKNOWN', "contexte déjà inscrit pour {$code} : {$existant['reference']}");
        }

        return $this->transaction(function () use ($donnees, $code): array {
            $reference = $this->allouerReference('matching_contexte');
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO matching_contexte
                 (reference,code_canonique,nom,finalite,politique_reference,politique_version,classification,
                  supervision_humaine,score_autorise,segment_autorise,activation_autorisee,mesure_autorisee,
                  etat,valide_depuis,valide_jusqua,source_reference,preuve_reference,created_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $code, (string) $donnees['nom'], (string) $donnees['finalite'],
                (string) $donnees['politique_reference'], (string) $donnees['politique_version'],
                (string) $donnees['classification'], (string) $donnees['supervision_humaine'],
                (int) (bool) ($donnees['score_autorise'] ?? false), (int) (bool) ($donnees['segment_autorise'] ?? false),
                (int) (bool) ($donnees['activation_autorisee'] ?? false), (int) (bool) ($donnees['mesure_autorisee'] ?? false),
                'PREPARATION', $maintenant, $donnees['valide_jusqua'] ?? null,
                (string) $donnees['source_reference'], null, $maintenant,
            ]);

            return ['reference' => $reference, 'etat' => 'PREPARATION'];
        });
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function activerContexte(string $reference, string $acteur): array
    {
        $contexte = $this->resoudreContexte($reference);
        if ($contexte === null) {
            return $this->refus('MATCHING_CONTEXT_UNKNOWN', "contexte `{$reference}` inconnu");
        }
        if ($contexte['etat'] !== 'PREPARATION') {
            return $this->refus('TRANSITION_INVALIDE', "contexte `{$reference}` en état {$contexte['etat']}, attendu PREPARATION");
        }

        return $this->transaction(function () use ($reference): array {
            $this->magasin->prepare('UPDATE matching_contexte SET etat = ? WHERE reference = ?')
                ->execute(['ACTIF', $reference]);

            return ['reference' => $reference, 'etat' => 'ACTIF'];
        });
    }

    public function resoudreContexte(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_contexte WHERE reference = ?', [$reference]);
    }

    /** @return list<array<string,mixed>> */
    public function listerContextes(): array
    {
        return $this->lignes('SELECT * FROM matching_contexte ORDER BY created_at', []);
    }

    // ==================================================================
    // Profils d'exécution (doc 02 §4-5)

    /** @param array<string,mixed> $specification @return array{refus:string,detail:string}|array<string,mixed> */
    public function compilerProfil(array $specification, string $acteur): array
    {
        $compilation = CompilateurPolitique::compiler($specification);
        if (isset($compilation['refus'])) {
            return $compilation;
        }
        $contexte = $this->resoudreContexte((string) $compilation['contexte_reference']);
        if ($contexte === null) {
            return $this->refus('MATCHING_CONTEXT_UNKNOWN', "contexte `{$compilation['contexte_reference']}` inconnu");
        }

        return $this->transaction(function () use ($compilation): array {
            $reference = $this->allouerReference('matching_profil_execution');
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO matching_profil_execution
                 (reference,contexte_reference,politique_reference,politique_version,contrat_reference,contrat_version,
                  algorithme_code,algorithme_version,plan_canonique_json,plan_hash,preuve_reference,compile_le,
                  active_le,retire_le,etat)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $compilation['contexte_reference'], $compilation['politique_reference'], $compilation['politique_version'],
                $compilation['contrat_reference'], $compilation['contrat_version'], $compilation['algorithme_code'], $compilation['algorithme_version'],
                $compilation['plan_canonique_json'], $compilation['plan_hash'], null, $maintenant, null, null, 'COMPILE',
            ]);

            $plan = json_decode($compilation['plan_canonique_json'], true, flags: JSON_THROW_ON_ERROR);
            $ordre = 0;
            foreach ($plan['criteres'] as $critere) {
                $ordre++;
                $this->magasin->prepare(
                    'INSERT INTO matching_profil_critere
                     (profil_reference,critere_reference,ordre,operateur,valeur_type,obligatoire,poids,seuil,
                      traitement_inconnu,traitement_contradictoire,fraicheur_max_secondes,sources_autorisees_json,
                      explication_code,facteur_public_autorise,exclusion_dure)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $reference, $critere['critere_reference'], $ordre, $critere['operateur'], $critere['valeur_type'],
                    (int) $critere['obligatoire'], $critere['poids'], $critere['seuil'], $critere['traitement_inconnu'],
                    $critere['traitement_contradictoire'], $critere['fraicheur_max_secondes'],
                    json_encode($critere['sources_autorisees'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $critere['explication_code'], (int) $critere['facteur_public_autorise'], (int) $critere['exclusion_dure'],
                ]);
            }

            return ['reference' => $reference, 'etat' => 'COMPILE', 'plan_hash' => $compilation['plan_hash']];
        });
    }

    /**
     * L'activation exige la preuve d'une simulation déjà exécutée par
     * l'appelant (doc 02 §4 : « aucune activation sans simulation
     * réussie ») — cette classe ne simule jamais elle-même faute de moteur
     * de simulation livré dans ce chantier ; réserve documentée.
     *
     * @return array{refus:string,detail:string}|array<string,mixed>
     */
    public function activerProfil(string $reference, string $preuveSimulationReference, string $acteur): array
    {
        if (trim($preuveSimulationReference) === '') {
            return $this->refus('SIMULATION_ABSENTE', 'aucune preuve de simulation fournie : activation refusée');
        }
        $profil = $this->ligne('SELECT * FROM matching_profil_execution WHERE reference = ?', [$reference]);
        if ($profil === null) {
            return $this->refus('MATCHING_POLICY_UNKNOWN', "profil `{$reference}` inconnu");
        }
        if ($profil['etat'] !== 'COMPILE') {
            return $this->refus('TRANSITION_INVALIDE', "profil `{$reference}` en état {$profil['etat']}, attendu COMPILE");
        }

        return $this->transaction(function () use ($profil, $reference, $preuveSimulationReference): array {
            $maintenant = gmdate('c');
            $ancien = $this->ligne(
                'SELECT reference FROM matching_profil_execution WHERE contexte_reference = ? AND etat = ?',
                [$profil['contexte_reference'], 'ACTIF']
            );
            if ($ancien !== null) {
                $this->magasin->prepare('UPDATE matching_profil_execution SET etat = ?, retire_le = ? WHERE reference = ?')
                    ->execute(['RETIRE', $maintenant, $ancien['reference']]);
            }
            $this->magasin->prepare('UPDATE matching_profil_execution SET etat = ?, active_le = ?, preuve_reference = ? WHERE reference = ?')
                ->execute(['ACTIF', $maintenant, $preuveSimulationReference, $reference]);

            return ['reference' => $reference, 'etat' => 'ACTIF', 'profil_retire' => $ancien['reference'] ?? null];
        });
    }

    public function resoudreProfil(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_profil_execution WHERE reference = ?', [$reference]);
    }

    public function resoudreProfilActif(string $contexteReference): ?array
    {
        return $this->ligne(
            'SELECT * FROM matching_profil_execution WHERE contexte_reference = ? AND etat = ?',
            [$contexteReference, 'ACTIF']
        );
    }

    /** @return list<array<string,mixed>> */
    private function criteresDuProfil(string $profilReference): array
    {
        return $this->lignes(
            'SELECT * FROM matching_profil_critere WHERE profil_reference = ? ORDER BY ordre',
            [$profilReference]
        );
    }

    // ==================================================================
    // Demandes (doc 02 §6-9)

    /** @param array<string,mixed> $donnees @return array{refus:string,detail:string}|array<string,mixed> */
    public function soumettreDemande(array $donnees, string $acteur): array
    {
        $idempotencyKey = (string) ($donnees['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            return $this->refus('DOSSIER_INCOMPLET', 'idempotency_key obligatoire');
        }
        $existante = $this->ligne('SELECT reference FROM matching_demande WHERE idempotency_key = ?', [$idempotencyKey]);
        if ($existante !== null) {
            return ['reference' => $existante['reference'], 'idempotent' => true];
        }

        foreach (['consommateur_produit', 'contexte_reference', 'finalite_reference', 'realm_reference', 'environnement',
            'mode_resultat', 'classification', 'contrat_reference', 'contrat_version', 'correlation_id'] as $champ) {
            if (trim((string) ($donnees[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ obligatoire absent : {$champ}");
            }
        }
        if (!in_array($donnees['mode_resultat'], PolitiqueMatching::MODES_RESULTAT, true)) {
            return $this->refus('MODE_RESULTAT_INCONNU', 'mode_resultat hors liste close');
        }
        if (!$this->produitActif((string) $donnees['consommateur_produit'])) {
            return $this->refus('MATCHING_PRODUCT_INACTIVE', "produit consommateur `{$donnees['consommateur_produit']}` inactif ou inconnu");
        }
        if (!$this->contratActif((string) $donnees['contrat_reference'], (string) $donnees['contrat_version'])) {
            return $this->refus('MATCHING_CONTRACT_INACTIVE', "contrat `{$donnees['contrat_reference']}` version `{$donnees['contrat_version']}` non actif");
        }

        $contexte = $this->resoudreContexte((string) $donnees['contexte_reference']);
        if ($contexte === null) {
            return $this->refus('MATCHING_CONTEXT_UNKNOWN', "contexte `{$donnees['contexte_reference']}` inconnu");
        }
        if ($contexte['etat'] !== 'ACTIF') {
            return $this->refus('MATCHING_CONTEXT_INACTIVE', "contexte `{$contexte['reference']}` en état {$contexte['etat']}");
        }
        $modeScore = in_array($donnees['mode_resultat'], ['CORRESPONDANCE', 'CLASSEMENT', 'ESTIMATION_AGREGEE'], true);
        if ($modeScore && !((bool) $contexte['score_autorise'])) {
            return $this->refus('MATCHING_CONTEXT_INACTIVE', 'ce contexte n’autorise pas la production d’un score');
        }
        if ($donnees['mode_resultat'] === 'SEGMENT_PROTEGE' && !((bool) $contexte['segment_autorise'])) {
            return $this->refus('MATCHING_CONTEXT_INACTIVE', 'ce contexte n’autorise pas la construction de segment');
        }

        $profil = $this->resoudreProfilActif((string) $contexte['reference']);
        if ($profil === null) {
            return $this->refus('MATCHING_POLICY_INACTIVE', "aucun profil actif pour le contexte `{$contexte['reference']}`");
        }
        $criteresProfil = $this->criteresDuProfil((string) $profil['reference']);
        $referencesProfil = array_column($criteresProfil, null, 'critere_reference');

        $criteresDemande = $donnees['criteres'] ?? [];
        if (!is_array($criteresDemande) || count($criteresDemande) > PolitiqueMatching::MATCHING_MAX_CRITERIA) {
            return $this->refus('MATCHING_LIMIT_EXCEEDED', 'nombre de critères hors bornes');
        }
        foreach ($criteresDemande as $critere) {
            $ref = (string) ($critere['critere_reference'] ?? '');
            if (!isset($referencesProfil[$ref])) {
                return $this->refus('MATCHING_CRITERION_NOT_ALLOWED', "critère `{$ref}` absent du profil actif");
            }
        }

        $objets = $donnees['objets'] ?? [];
        if (!is_array($objets) || $objets === []) {
            return $this->refus('MATCHING_REQUIRED_DATA_UNKNOWN', 'au moins un objet (candidat ou entité) est requis');
        }
        if (count($objets) > PolitiqueMatching::MATCHING_MAX_CANDIDATES) {
            return $this->refus('MATCHING_LIMIT_EXCEEDED', 'population soumise au-delà de la limite autorisée');
        }
        foreach ($objets as $objet) {
            $role = (string) ($objet['role_objet'] ?? '');
            if (!in_array($role, PolitiqueMatching::ROLES_OBJET, true)) {
                return $this->refus('DOSSIER_INCOMPLET', "role_objet hors liste close : {$role}");
            }
            foreach (['objet_type', 'source_reference', 'contrat_reference', 'valide_depuis', 'classification'] as $champ) {
                if (trim((string) ($objet[$champ] ?? '')) === '') {
                    return $this->refus('DOSSIER_INCOMPLET', "objet : champ obligatoire absent : {$champ}");
                }
            }
        }

        return $this->transaction(function () use ($donnees, $contexte, $profil, $criteresDemande, $objets, $idempotencyKey, $acteur): array {
            $reference = $this->allouerReference('matching_demande');
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO matching_demande
                 (reference,idempotency_key,consommateur_produit,consommateur_organisation,contexte_reference,
                  finalite_reference,realm_reference,environnement,politique_reference,politique_version,
                  profil_execution_reference,objet_principal_type,objet_principal_reference,population_reference,
                  mode_resultat,limite_resultats,classification,etat,soumise_par,mandat_reference,autorisation_reference,
                  contrat_reference,contrat_version,correlation_id,expire_le,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $idempotencyKey, (string) $donnees['consommateur_produit'], $donnees['consommateur_organisation'] ?? null,
                $contexte['reference'], (string) $donnees['finalite_reference'], (string) $donnees['realm_reference'],
                (string) $donnees['environnement'], $profil['politique_reference'], $profil['politique_version'], $profil['reference'],
                $donnees['objet_principal_type'] ?? null, $donnees['objet_principal_reference'] ?? null,
                $donnees['population_reference'] ?? null, (string) $donnees['mode_resultat'], $donnees['limite_resultats'] ?? null,
                (string) $donnees['classification'], 'SOUMISE', $acteur, $donnees['mandat_reference'] ?? null,
                $donnees['autorisation_reference'] ?? null, (string) $donnees['contrat_reference'], (string) $donnees['contrat_version'],
                (string) $donnees['correlation_id'], $donnees['expire_le'] ?? null, $maintenant, $maintenant,
            ]);

            $referencesObjets = [];
            foreach ($objets as $objet) {
                $refInterne = $this->allouerReference('matching_objet');
                $referencesObjets[] = $refInterne;
                $this->magasin->prepare(
                    'INSERT INTO matching_objet
                     (reference_interne,demande_reference,role_objet,objet_type,objet_reference_externe,source_reference,
                      contrat_reference,version_objet,empreinte_objet,valide_depuis,valide_jusqua,classification,snapshot_minimal_json)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $refInterne, $reference, (string) $objet['role_objet'], (string) $objet['objet_type'],
                    $objet['objet_reference_externe'] ?? null, (string) $objet['source_reference'], (string) $objet['contrat_reference'],
                    $objet['version_objet'] ?? null, hash('sha256', json_encode($objet, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                    (string) $objet['valide_depuis'], $objet['valide_jusqua'] ?? null, (string) $objet['classification'],
                    json_encode($objet['snapshot_minimal'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }

            $ordre = 0;
            foreach ($criteresDemande as $critere) {
                $ordre++;
                $this->magasin->prepare(
                    'INSERT INTO matching_critere_demande
                     (demande_reference,critere_reference,operateur,valeur_normalisee_json,obligatoire,poids_effectif,
                      origine,source_exigee,ordre)
                     VALUES(?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $reference, (string) $critere['critere_reference'], (string) $critere['operateur'],
                    json_encode($critere['valeur_normalisee'] ?? null, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    (int) (bool) ($critere['obligatoire'] ?? false), $critere['poids_effectif'] ?? null,
                    (string) ($critere['origine'] ?? 'POLITIQUE'), $critere['source_exigee'] ?? null, $ordre,
                ]);
            }

            $this->inscrireCycle($reference, 'SOUMISE', $maintenant, null, null, $acteur);

            return ['reference' => $reference, 'etat' => 'SOUMISE', 'objets' => $referencesObjets];
        });
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function validerDemande(string $reference, string $acteur): array
    {
        $demande = $this->resoudreDemande($reference);
        if ($demande === null) {
            return $this->refus('DEMANDE_INCONNUE', "demande `{$reference}` inconnue");
        }
        if ($demande['etat'] !== 'SOUMISE') {
            return $this->refus('TRANSITION_INVALIDE', "demande `{$reference}` en état {$demande['etat']}, attendu SOUMISE");
        }

        return $this->transaction(function () use ($reference, $acteur): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare('UPDATE matching_demande SET etat = ?, updated_at = ? WHERE reference = ?')
                ->execute(['VALIDEE', $maintenant, $reference]);
            $this->inscrireCycle($reference, 'VALIDEE', $maintenant, null, null, $acteur);

            return ['reference' => $reference, 'etat' => 'VALIDEE'];
        });
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function annulerDemande(string $reference, string $motif, string $acteur): array
    {
        $demande = $this->resoudreDemande($reference);
        if ($demande === null) {
            return $this->refus('DEMANDE_INCONNUE', "demande `{$reference}` inconnue");
        }
        if (in_array($demande['etat'], PolitiqueMatching::ETATS_DEMANDE_TERMINAUX, true)) {
            return $this->refus('TRANSITION_INVALIDE', "demande `{$reference}` déjà dans un état terminal ({$demande['etat']})");
        }

        return $this->transaction(function () use ($reference, $motif, $acteur): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare('UPDATE matching_demande SET etat = ?, updated_at = ? WHERE reference = ?')
                ->execute(['ANNULEE', $maintenant, $reference]);
            $this->inscrireCycle($reference, 'ANNULEE', $maintenant, 'ANNULATION_DEMANDEUR', $motif, $acteur);

            return ['reference' => $reference, 'etat' => 'ANNULEE'];
        });
    }

    public function resoudreDemande(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_demande WHERE reference = ?', [$reference]);
    }

    /**
     * @param array{contexte_reference?:string,consommateur_produit?:string,etat?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public function listerDemandes(array $filtres = [], int $limite = 100): array
    {
        $conditions = [];
        $params = [];
        foreach (['contexte_reference', 'consommateur_produit', 'etat'] as $champ) {
            if (isset($filtres[$champ]) && $filtres[$champ] !== '') {
                $conditions[] = "{$champ} = ?";
                $params[] = $filtres[$champ];
            }
        }
        $ou = $conditions === [] ? '' : ('WHERE ' . implode(' AND ', $conditions));
        $params[] = max(1, min(500, $limite));

        return $this->lignes("SELECT * FROM matching_demande {$ou} ORDER BY created_at DESC LIMIT ?", $params);
    }

    /** Historique en ajout seul des transitions d'une demande (doc 02 §6). @return list<array<string,mixed>> */
    public function historiqueDemande(string $demandeReference): array
    {
        return $this->lignes('SELECT * FROM matching_cycle WHERE demande_reference = ? ORDER BY cree_le', [$demandeReference]);
    }

    /** @return list<array<string,mixed>> */
    public function listerExecutions(string $demandeReference): array
    {
        return $this->lignes('SELECT * FROM matching_execution WHERE demande_reference = ? ORDER BY demarre_le DESC', [$demandeReference]);
    }

    /** Dernière exécution, quel que soit son état (l'appelant filtre TERMINEE si nécessaire). */
    public function derniereExecution(string $demandeReference): ?array
    {
        return $this->ligne('SELECT * FROM matching_execution WHERE demande_reference = ? ORDER BY demarre_le DESC LIMIT 1', [$demandeReference]);
    }

    /** @return list<array<string,mixed>> */
    public function segmentsDeDemande(string $demandeReference): array
    {
        return $this->lignes('SELECT * FROM matching_segment WHERE demande_reference = ? ORDER BY cree_le DESC', [$demandeReference]);
    }

    private function inscrireCycle(string $demandeReference, string $etat, string $date, ?string $motifRef, ?string $motifDetail, string $acteur): void
    {
        $this->magasin->prepare(
            'INSERT INTO matching_cycle
             (demande_reference,etat_reference,date_effet,motif_reference,motif_detail,acteur_reference,preuve_reference,cree_le)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([$demandeReference, $etat, $date, $motifRef, $motifDetail, $acteur, null, $date]);
    }

    // ==================================================================
    // Signaux (doc 02 §10) — matérialisation minimale pour ce périmètre ;
    // l'acquisition réelle depuis CAP-CORE-014 est un chantier ultérieur.

    /** @param array<string,mixed> $donnees @return array<string,mixed> */
    public function enregistrerSignal(array $donnees): array
    {
        foreach (['sujet_type', 'sujet_reference', 'signal_code', 'valeur_type', 'source_reference',
            'finalite_reference', 'realm_reference', 'contrat_reference', 'contrat_version',
            'observation_le', 'valide_jusqua', 'classification'] as $champ) {
            if (trim((string) ($donnees[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ obligatoire absent : {$champ}");
            }
        }

        return $this->transaction(function () use ($donnees): array {
            $reference = $this->allouerReference('matching_signal');
            $this->magasin->prepare(
                'INSERT INTO matching_signal
                 (reference,sujet_type,sujet_reference,signal_code,valeur_type,valeur_normalisee_json,source_reference,
                  source_revision,finalite_reference,realm_reference,contrat_reference,contrat_version,observation_le,
                  valide_jusqua,confiance_source,preuve_reference,classification,statut,recu_le,expire_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, (string) $donnees['sujet_type'], (string) $donnees['sujet_reference'], (string) $donnees['signal_code'],
                (string) $donnees['valeur_type'], json_encode($donnees['valeur_normalisee'] ?? null, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                (string) $donnees['source_reference'], $donnees['source_revision'] ?? null, (string) $donnees['finalite_reference'],
                (string) $donnees['realm_reference'], (string) $donnees['contrat_reference'], (string) $donnees['contrat_version'],
                (string) $donnees['observation_le'], (string) $donnees['valide_jusqua'], $donnees['confiance_source'] ?? null,
                $donnees['preuve_reference'] ?? null, (string) $donnees['classification'], $donnees['statut'] ?? 'VALIDE',
                gmdate('c'), $donnees['valide_jusqua'],
            ]);

            return ['reference' => $reference, 'statut' => $donnees['statut'] ?? 'VALIDE'];
        });
    }

    /** Dernier signal utilisable pour un sujet et un code, quel que soit son statut (le refus est décidé par ResolutionSources). */
    private function dernierSignal(string $sujetReference, string $signalCode): ?array
    {
        return $this->ligne(
            'SELECT * FROM matching_signal WHERE sujet_reference = ? AND signal_code = ?
             ORDER BY observation_le DESC LIMIT 1',
            [$sujetReference, $signalCode]
        );
    }

    // ==================================================================
    // Exécution (doc 02 §12-16, doc 03 §1-11)

    /**
     * @param array{candidats_exclus?:list<string>,source_active?:bool} $faits réserve documentée en tête de classe
     * @return array{refus:string,detail:string}|array<string,mixed>
     */
    public function executer(string $demandeReference, array $faits, string $acteur, ?string $instantReference = null): array
    {
        $instant = $instantReference ?? gmdate('c');
        $demande = $this->resoudreDemande($demandeReference);
        if ($demande === null) {
            return $this->refus('DEMANDE_INCONNUE', "demande `{$demandeReference}` inconnue");
        }
        // TERMINEE/PARTIELLE/EN_ECHEC restent exécutables : une reprise crée
        // une nouvelle exécution liée (doc 02 §12), par exemple pour un
        // réexamen après correction de source — l'ancienne exécution reste
        // immuable, celle-ci est un nouvel enregistrement distinct.
        if (!in_array($demande['etat'], ['VALIDEE', 'SOUMISE', 'TERMINEE', 'PARTIELLE', 'EN_ECHEC'], true)) {
            return $this->refus('TRANSITION_INVALIDE', "demande `{$demandeReference}` en état {$demande['etat']}, exécution impossible");
        }
        $profil = $this->ligne('SELECT * FROM matching_profil_execution WHERE reference = ?', [$demande['profil_execution_reference']]);
        if ($profil === null || $profil['etat'] !== 'ACTIF') {
            return $this->refus('MATCHING_POLICY_INACTIVE', 'le profil d’exécution de cette demande n’est plus actif');
        }
        $criteresProfil = $this->criteresDuProfil((string) $profil['reference']);
        $criteresProfilParRef = array_column($criteresProfil, null, 'critere_reference');
        $criteresDemande = $this->lignes('SELECT * FROM matching_critere_demande WHERE demande_reference = ? ORDER BY ordre', [$demandeReference]);
        $criteresDemandeParRef = array_column($criteresDemande, null, 'critere_reference');
        $objets = $this->lignes(
            "SELECT * FROM matching_objet WHERE demande_reference = ? AND role_objet IN ('CANDIDAT','ENTITE') ORDER BY reference_interne",
            [$demandeReference]
        );
        if ($objets === []) {
            return $this->refus('MATCHING_REQUIRED_DATA_UNKNOWN', 'aucun candidat à évaluer pour cette demande');
        }

        $plan = json_decode((string) $profil['plan_canonique_json'], true, flags: JSON_THROW_ON_ERROR);
        $parametres = [
            'seuils_classe' => $plan['seuils_classe'] ?? null,
            'precision_arrondi' => $plan['precision_arrondi'] ?? null,
        ];
        $parametres = array_filter($parametres, static fn ($v) => $v !== null);
        $exclus = array_flip($faits['candidats_exclus'] ?? []);

        return $this->transaction(function () use (
            $demande, $profil, $criteresProfil, $criteresProfilParRef, $criteresDemandeParRef, $objets,
            $parametres, $exclus, $acteur, $instant,
        ): array {
            $maintenant = gmdate('c');
            $executionReference = $this->allouerReference('matching_execution');
            $this->magasin->prepare(
                'INSERT INTO matching_execution
                 (reference,demande_reference,profil_execution_reference,algorithme_code,algorithme_version,jeu_donnees_hash,
                  plan_hash,demarre_le,termine_le,etat,candidats_total,candidats_evalues,candidats_refuses,resultats_total,
                  signaux_utilises,signaux_inconnus,signaux_contradictoires,preuve_reference,correlation_id,erreur_code)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $executionReference, $demande['reference'], $profil['reference'], $profil['algorithme_code'], $profil['algorithme_version'],
                null, $profil['plan_hash'], $maintenant, null, 'EN_COURS', count($objets), 0, 0, 0, 0, 0, 0, null,
                $demande['correlation_id'], null,
            ]);

            $signauxUtilises = 0;
            $signauxInconnus = 0;
            $signauxContradictoires = 0;
            $candidatsRefuses = 0;
            $resultatsBruts = [];

            foreach ($objets as $objet) {
                $candidatRef = $objet['reference_interne'];
                $sujetExterne = $objet['objet_reference_externe'] ?? $candidatRef;
                $admisPrefiltrage = !isset($exclus[$candidatRef]) && !isset($exclus[$sujetExterne]);
                if (!$admisPrefiltrage) {
                    $candidatsRefuses++;
                }
                $this->magasin->prepare(
                    'INSERT INTO matching_candidat
                     (execution_reference,candidat_reference,sujet_type,sujet_reference,realm_reference,admis_evaluation,
                      motif_refus_code,donnees_snapshot_hash)
                     VALUES(?,?,?,?,?,?,?,?)'
                )->execute([
                    $executionReference, $candidatRef, $objet['objet_type'], $sujetExterne, $demande['realm_reference'],
                    (int) $admisPrefiltrage, $admisPrefiltrage ? null : 'EXCLUSION_MANUELLE', $objet['empreinte_objet'],
                ]);

                $evaluationsParCritere = [];
                foreach ($criteresProfil as $critereProfil) {
                    $ref = $critereProfil['critere_reference'];
                    $critereDemande = $criteresDemandeParRef[$ref] ?? null;
                    $etatEvaluation = 'NON_ETABLI';
                    $sourceUtilisee = null;
                    $observationLe = null;
                    $confianceSource = null;
                    $motifCode = null;
                    $valeurObserveeHash = null;

                    if ($critereDemande !== null) {
                        $signal = $this->dernierSignal($sujetExterne, $ref);
                        if ($signal !== null) {
                            $verif = ResolutionSources::verifierSignal(
                                ['sources_autorisees' => json_decode((string) $critereProfil['sources_autorisees_json'], true), 'fraicheur_max_secondes' => $critereProfil['fraicheur_max_secondes']],
                                ['source_reference' => $signal['source_reference'], 'source_active' => $this->sourceActive((string) $signal['source_reference']), 'finalite_reference' => $signal['finalite_reference'], 'statut' => $signal['statut'], 'observation_le' => $signal['observation_le']],
                                $demande['finalite_reference'],
                                $instant,
                            );
                            $sourceUtilisee = $signal['source_reference'];
                            $observationLe = $signal['observation_le'];
                            $confianceSource = $signal['confiance_source'] !== null ? (float) $signal['confiance_source'] : null;
                            $valeurObserveeHash = hash('sha256', (string) $signal['valeur_normalisee_json']);
                            if (!$verif['utilisable']) {
                                $etatEvaluation = 'NON_ETABLI';
                                $motifCode = $verif['motif_code'];
                                $signauxInconnus++;
                            } elseif ($verif['etat_signal'] === 'CONTRADICTOIRE') {
                                $etatEvaluation = 'CONTRADICTOIRE';
                                $signauxContradictoires++;
                            } else {
                                $observee = json_decode((string) $signal['valeur_normalisee_json'], true);
                                $attendue = json_decode((string) $critereDemande['valeur_normalisee_json'], true);
                                $bilan = Apparieur::evaluer($critereProfil['operateur'], $observee, $attendue, $instant);
                                $etatEvaluation = $bilan === null ? 'NON_ETABLI' : ($bilan ? 'SATISFAIT' : 'DEFAVORABLE');
                                $signauxUtilises++;
                            }
                        } else {
                            $signauxInconnus++;
                        }
                    } else {
                        $etatEvaluation = 'NON_APPLICABLE';
                    }

                    $evaluationsParCritere[$ref] = ['etat' => $etatEvaluation, 'confiance_source' => $confianceSource];
                    $this->magasin->prepare(
                        'INSERT INTO matching_evaluation_critere
                         (execution_reference,candidat_reference,critere_reference,etat_evaluation,valeur_observee_hash,
                          source_reference,observation_le,fraicheur,confiance_source,contribution_score,motif_code,preuve_reference)
                         VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $executionReference, $candidatRef, $ref, $etatEvaluation, $valeurObserveeHash, $sourceUtilisee,
                        $observationLe, null, $confianceSource, null, $motifCode, null,
                    ]);
                }

                $criteresPourEvaluateur = array_map(static fn (array $c): array => [
                    'critere_reference' => $c['critere_reference'],
                    'obligatoire' => (bool) $c['obligatoire'],
                    'exclusion_dure' => (bool) $c['exclusion_dure'],
                    'poids' => $c['poids'] !== null ? (float) $c['poids'] : null,
                    'traitement_inconnu' => $c['traitement_inconnu'],
                    'traitement_contradictoire' => $c['traitement_contradictoire'],
                    'facteur_public_autorise' => (bool) $c['facteur_public_autorise'],
                ], $criteresProfil);

                $bilan = EvaluateurDeterministe::evaluer($criteresPourEvaluateur, $evaluationsParCritere, $parametres);
                $resultatsBruts[] = [
                    'candidat_reference' => $candidatRef,
                    'admissible' => $admisPrefiltrage,
                    'classe' => $bilan['classe'],
                    'pertinence' => $bilan['pertinence'],
                    'confiance' => $bilan['confiance'],
                    'regles_secondaires' => [],
                    'facteurs' => $bilan['facteurs'],
                ];
            }

            $classes = Classement::classer($resultatsBruts);

            $expireLe = gmdate('c', strtotime($instant) + PolitiqueMatching::RESULTAT_TTL_SECONDES_DEFAUT);
            $resultatsReferences = [];
            foreach ($classes as $ligne) {
                $refResultat = $this->allouerReference('matching_resultat');
                $resultatsReferences[] = $refResultat;
                $facteursFav = 0;
                $facteursDef = 0;
                $facteursInc = 0;
                foreach ($ligne['facteurs'] as $f) {
                    match ($f['nature']) {
                        'FAVORABLE' => $facteursFav++,
                        'DEFAVORABLE' => $facteursDef++,
                        'NON_ETABLI', 'CONTRADICTOIRE' => $facteursInc++,
                        default => null,
                    };
                }
                $obligations = PolitiqueMatching::OBLIGATIONS_MINIMALES;
                $this->magasin->prepare(
                    'INSERT INTO matching_resultat
                     (reference,execution_reference,candidat_reference,resultat_type,classe_resultat,pertinence,confiance,
                      rang,facteurs_favorables_nombre,facteurs_defavorables_nombre,facteurs_inconnus_nombre,obligations_json,
                      non_decisionnel,politique_reference,politique_version,source_set_hash,preuve_reference,expire_le,created_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $refResultat, $executionReference, $ligne['candidat_reference'], $demande['mode_resultat'], $ligne['classe'],
                    $ligne['pertinence'], $ligne['confiance'], $ligne['rang'], $facteursFav, $facteursDef, $facteursInc,
                    json_encode($obligations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 1, $demande['politique_reference'],
                    $demande['politique_version'], null, null, $expireLe, gmdate('c'),
                ]);
                $ordreFacteur = 0;
                foreach ($ligne['facteurs'] as $f) {
                    $ordreFacteur++;
                    $critereProfil = $criteresProfilParRef[$f['critere_reference']] ?? null;
                    $this->magasin->prepare(
                        'INSERT INTO matching_facteur
                         (resultat_reference,critere_reference,nature,code_explication,importance,source_reference,public_autorise,ordre)
                         VALUES(?,?,?,?,?,?,?,?)'
                    )->execute([
                        $refResultat, $f['critere_reference'], $f['nature'],
                        $critereProfil['explication_code'] ?? $f['critere_reference'], $critereProfil['poids'] ?? null,
                        null, (int) $f['public_autorise'], $ordreFacteur,
                    ]);
                }
            }

            $this->magasin->prepare(
                'UPDATE matching_execution
                 SET termine_le = ?, etat = ?, candidats_evalues = ?, candidats_refuses = ?, resultats_total = ?,
                     signaux_utilises = ?, signaux_inconnus = ?, signaux_contradictoires = ?
                 WHERE reference = ?'
            )->execute([
                gmdate('c'), 'TERMINEE', count($objets) - $candidatsRefuses, $candidatsRefuses, count($resultatsReferences),
                $signauxUtilises, $signauxInconnus, $signauxContradictoires, $executionReference,
            ]);
            $this->magasin->prepare('UPDATE matching_demande SET etat = ?, updated_at = ? WHERE reference = ?')
                ->execute(['TERMINEE', gmdate('c'), $demande['reference']]);
            $this->inscrireCycle($demande['reference'], 'TERMINEE', gmdate('c'), null, null, $acteur);

            $this->publier($this->magasin, [
                'type_evenement' => 'CAP-CORE-021.MATCHING_EXECUTION_TERMINEE',
                'contrat_reference' => $demande['contrat_reference'], 'contrat_version' => $demande['contrat_version'],
                'source_reference' => PolitiqueMatching::SOURCE, 'realm_reference' => $demande['realm_reference'],
                'finalite_reference' => $demande['finalite_reference'], 'correlation_id' => $demande['correlation_id'],
                'survenu_le' => gmdate('c'), 'classification' => $demande['classification'],
                'idempotence_reference' => 'EXE-EVT-' . $executionReference,
                'producteur_capacite_reference' => PolitiqueMatching::CAPACITE,
                'charge' => ['execution' => $executionReference, 'demande' => $demande['reference'], 'resultats_total' => count($resultatsReferences)],
            ]);

            return [
                'execution' => $executionReference, 'etat' => 'TERMINEE',
                'resultats' => $resultatsReferences, 'candidats_evalues' => count($objets) - $candidatsRefuses,
                'candidats_refuses' => $candidatsRefuses,
            ];
        });
    }

    public function resoudreExecution(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_execution WHERE reference = ?', [$reference]);
    }

    // ==================================================================
    // Résultats (doc 02 §15-16, doc 03 §11)

    public function resoudreResultat(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_resultat WHERE reference = ?', [$reference]);
    }

    /** @return list<array<string,mixed>> */
    public function listerResultats(string $executionReference): array
    {
        return $this->lignes('SELECT * FROM matching_resultat WHERE execution_reference = ? ORDER BY rang IS NULL, rang', [$executionReference]);
    }

    /** @return list<array<string,mixed>> */
    private function facteursDuResultat(string $resultatReference): array
    {
        return $this->lignes('SELECT * FROM matching_facteur WHERE resultat_reference = ? ORDER BY ordre', [$resultatReference]);
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function expliquerResultat(string $reference, ?string $instantReference = null): array
    {
        $resultat = $this->resoudreResultat($reference);
        if ($resultat === null) {
            return $this->refus('RESULTAT_INCONNU', "résultat `{$reference}` inconnu");
        }
        $instant = $instantReference ?? gmdate('c');
        if (Explication::estExpire((string) $resultat['expire_le'], $instant)) {
            return $this->refus('MATCHING_RESULT_EXPIRED', "résultat `{$reference}` expiré le {$resultat['expire_le']}");
        }
        $facteurs = $this->facteursDuResultat($reference);
        $projection = Explication::projeter(
            [
                'classe' => $resultat['classe_resultat'], 'pertinence' => $resultat['pertinence'] !== null ? (float) $resultat['pertinence'] : null,
                'confiance' => $resultat['confiance'] !== null ? (float) $resultat['confiance'] : null, 'non_decisionnel' => (bool) $resultat['non_decisionnel'],
                'expire_le' => $resultat['expire_le'], 'politique_reference' => $resultat['politique_reference'],
                'politique_version' => $resultat['politique_version'], 'preuve_reference' => $resultat['preuve_reference'],
                'obligations' => json_decode((string) $resultat['obligations_json'], true) ?? [],
            ],
            array_map(static fn (array $f): array => [
                'critere_reference' => $f['critere_reference'], 'nature' => $f['nature'],
                'code_explication' => $f['code_explication'], 'public_autorise' => (bool) $f['public_autorise'],
            ], $facteurs),
        );

        return $projection;
    }

    // ==================================================================
    // Segments (doc 02 §17-19, doc 03 §12)

    /**
     * @param array{classes_incluses?:list<string>} $donnees
     * @return array{refus:string,detail:string}|array<string,mixed>
     */
    public function construireSegment(string $demandeReference, array $donnees, string $acteur, ?string $instantReference = null): array
    {
        $instant = $instantReference ?? gmdate('c');
        $demande = $this->resoudreDemande($demandeReference);
        if ($demande === null) {
            return $this->refus('DEMANDE_INCONNUE', "demande `{$demandeReference}` inconnue");
        }
        $contexte = $this->resoudreContexte((string) $demande['contexte_reference']);
        if ($contexte === null || !((bool) $contexte['segment_autorise'])) {
            return $this->refus('MATCHING_CONTEXT_INACTIVE', 'ce contexte n’autorise pas la construction de segment');
        }
        $execution = $this->ligne(
            "SELECT * FROM matching_execution WHERE demande_reference = ? AND etat = 'TERMINEE' ORDER BY demarre_le DESC LIMIT 1",
            [$demandeReference]
        );
        if ($execution === null) {
            return $this->refus('MATCHING_REQUIRED_DATA_UNKNOWN', 'aucune exécution terminée pour cette demande');
        }
        $classesIncluses = $donnees['classes_incluses'] ?? ['CORRESPONDANCE_FORTE', 'CORRESPONDANCE_PROBABLE'];
        $placeholders = implode(',', array_fill(0, count($classesIncluses), '?'));
        $admissibles = $this->lignes(
            "SELECT candidat_reference FROM matching_resultat WHERE execution_reference = ? AND classe_resultat IN ({$placeholders})",
            [$execution['reference'], ...$classesIncluses]
        );
        $sujets = array_values(array_unique(array_column($admissibles, 'candidat_reference')));
        sort($sujets, SORT_STRING);

        $construction = Segments::construire($sujets, PolitiqueMatching::SEUIL_PETITE_POPULATION_DEFAUT);
        if (isset($construction['refus'])) {
            return $construction;
        }

        return $this->transaction(function () use ($demande, $execution, $contexte, $construction, $instant): array {
            $reference = $this->allouerReference('matching_segment');
            $maintenant = gmdate('c');
            $expireLe = gmdate('c', strtotime($instant) + PolitiqueMatching::SEGMENT_TTL_SECONDES_DEFAUT);
            $this->magasin->prepare(
                'INSERT INTO matching_segment
                 (reference,demande_reference,execution_reference,contexte_reference,consommateur_produit,finalite_reference,
                  realm_reference,politique_reference,politique_version,population_nombre,membres_hash,classification,
                  export_brut_autorise,verification_appartenance_autorisee,activation_autorisee,etat,cree_le,active_le,
                  expire_le,suspendu_le,revoque_le,preuve_reference)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $demande['reference'], $execution['reference'], $contexte['reference'], $demande['consommateur_produit'],
                $demande['finalite_reference'], $demande['realm_reference'], $demande['politique_reference'], $demande['politique_version'],
                $construction['population_nombre'], $construction['membres_hash'], $demande['classification'], 0, 1,
                (int) (bool) $contexte['activation_autorisee'], 'ACTIF', $maintenant, $maintenant, $expireLe, null, null, null,
            ]);
            foreach ($construction['membres'] as $membre) {
                $this->magasin->prepare(
                    'INSERT INTO matching_segment_membre
                     (segment_reference,membre_token,sujet_reference_interne,ajoute_par_resultat,valide_depuis,valide_jusqua,statut,preuve_reference)
                     VALUES(?,?,?,?,?,?,?,?)'
                )->execute([$reference, $membre['membre_token'], $membre['sujet_reference_interne'], null, $maintenant, $expireLe, 'ACTIF', null]);
            }

            $this->publier($this->magasin, [
                'type_evenement' => 'CAP-CORE-021.MATCHING_SEGMENT_ACTIF',
                'contrat_reference' => $demande['contrat_reference'], 'contrat_version' => $demande['contrat_version'],
                'source_reference' => PolitiqueMatching::SOURCE, 'realm_reference' => $demande['realm_reference'],
                'finalite_reference' => $demande['finalite_reference'], 'correlation_id' => $demande['correlation_id'],
                'survenu_le' => $maintenant, 'classification' => $demande['classification'],
                'idempotence_reference' => 'SEG-EVT-' . $reference, 'producteur_capacite_reference' => PolitiqueMatching::CAPACITE,
                'charge' => ['segment' => $reference, 'population_nombre' => $construction['population_nombre']],
            ]);

            return ['reference' => $reference, 'etat' => 'ACTIF', 'population_nombre' => $construction['population_nombre'], 'expire_le' => $expireLe];
        });
    }

    public function resoudreSegment(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_segment WHERE reference = ?', [$reference]);
    }

    /** @return array{statut:string,segment_expire_le:?string} */
    public function verifierAppartenanceSegment(string $segmentReference, string $token, ?string $instantReference = null): array
    {
        $instant = $instantReference ?? gmdate('c');
        $segment = $this->resoudreSegment($segmentReference);
        if ($segment === null || $segment['etat'] !== 'ACTIF' || !((bool) $segment['verification_appartenance_autorisee'])) {
            return ['statut' => 'N_APPARTIENT_PAS', 'segment_expire_le' => $segment['expire_le'] ?? null];
        }
        $membres = $this->lignes(
            'SELECT membre_token,sujet_reference_interne,statut,valide_jusqua FROM matching_segment_membre WHERE segment_reference = ?',
            [$segmentReference]
        );
        $statut = Segments::verifierAppartenance($membres, $token, $instant);

        return ['statut' => $statut, 'segment_expire_le' => $segment['expire_le']];
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function suspendreSegment(string $reference, string $acteur): array
    {
        return $this->transitionnerSegment($reference, 'SUSPENDU', ['ACTIF'], 'suspendu_le', $acteur);
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function revoquerSegment(string $reference, string $acteur): array
    {
        return $this->transitionnerSegment($reference, 'REVOQUE', ['ACTIF', 'SUSPENDU'], 'revoque_le', $acteur);
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    private function transitionnerSegment(string $reference, string $cible, array $etatsAutorises, string $colonneDate, string $acteur): array
    {
        $segment = $this->resoudreSegment($reference);
        if ($segment === null) {
            return $this->refus('SEGMENT_INCONNU', "segment `{$reference}` inconnu");
        }
        if (!in_array($segment['etat'], $etatsAutorises, true)) {
            return $this->refus('TRANSITION_INVALIDE', "segment `{$reference}` en état {$segment['etat']}");
        }

        return $this->transaction(function () use ($reference, $cible, $colonneDate): array {
            $this->magasin->prepare("UPDATE matching_segment SET etat = ?, {$colonneDate} = ? WHERE reference = ?")
                ->execute([$cible, gmdate('c'), $reference]);

            return ['reference' => $reference, 'etat' => $cible];
        });
    }

    // ==================================================================
    // Activations (doc 02 §20-21, doc 03 §13)

    /**
     * @param array<string,mixed> $donnees
     * @param array<string,mixed> $faits faits déjà résolus par l'appelant (réserve documentée en tête de classe)
     * @return array{refus:string,detail:string}|array<string,mixed>
     */
    public function demanderActivation(string $segmentReference, array $donnees, array $faits, string $acteur, ?string $instantReference = null): array
    {
        $instant = $instantReference ?? gmdate('c');
        $segment = $this->resoudreSegment($segmentReference);
        if ($segment === null) {
            return $this->refus('SEGMENT_INCONNU', "segment `{$segmentReference}` inconnu");
        }
        foreach (['consommateur_produit', 'finalite_reference', 'realm_reference', 'environnement', 'contrat_reference', 'contrat_version', 'autorisation_reference', 'usage_autorise'] as $champ) {
            if (trim((string) ($donnees[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ obligatoire absent : {$champ}");
            }
        }

        $verification = Activation::verifier([
            'segment_etat' => $segment['etat'], 'segment_expire_le' => $segment['expire_le'], 'instant_reference' => $instant,
            'segment_consommateur_produit' => $segment['consommateur_produit'], 'demande_consommateur_produit' => $donnees['consommateur_produit'],
            'segment_finalite_reference' => $segment['finalite_reference'], 'demande_finalite_reference' => $donnees['finalite_reference'],
            'segment_realm_reference' => $segment['realm_reference'], 'demande_realm_reference' => $donnees['realm_reference'],
            'produit_actif' => (bool) ($faits['produit_actif'] ?? false), 'contrat_actif' => (bool) ($faits['contrat_actif'] ?? false),
            'politique_active' => (bool) ($faits['politique_active'] ?? false), 'autorisation_decision' => (string) ($faits['autorisation_decision'] ?? 'REFUSE'),
            'decision_formelle_requise' => (bool) ($faits['decision_formelle_requise'] ?? false), 'decision_formelle_presente' => (bool) ($faits['decision_formelle_presente'] ?? false),
            'risque_bloquant' => (bool) ($faits['risque_bloquant'] ?? false), 'incident_bloquant' => (bool) ($faits['incident_bloquant'] ?? false),
            'obligations_acceptees' => (bool) ($faits['obligations_acceptees'] ?? false),
        ]);
        $etat = match ($verification['decision']) {
            'AUTORISEE' => 'AUTORISEE',
            'REFUSEE' => 'REFUSEE',
            default => 'EN_ECHEC',
        };

        return $this->transaction(function () use ($segment, $donnees, $etat, $verification, $instant): array {
            $maintenant = gmdate('c');
            $reference = $this->allouerReference('matching_activation');
            $expireLe = gmdate('c', strtotime($instant) + (int) ($donnees['duree_secondes'] ?? PolitiqueMatching::ACTIVATION_TTL_SECONDES_DEFAUT));
            $obligations = array_values(array_unique(array_merge(PolitiqueMatching::OBLIGATIONS_MINIMALES, $donnees['obligations_supplementaires'] ?? [])));
            $this->magasin->prepare(
                'INSERT INTO matching_activation
                 (reference,segment_reference,consommateur_produit,consommateur_organisation,contexte_reference,finalite_reference,
                  realm_reference,environnement,contrat_reference,contrat_version,decision_reference,autorisation_reference,
                  obligations_json,quota,usage_autorise,etat,demande_le,autorise_le,active_le,expire_le,termine_le,revoque_le,preuve_reference)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $segment['reference'], $donnees['consommateur_produit'], $donnees['consommateur_organisation'] ?? null,
                $segment['contexte_reference'], $donnees['finalite_reference'], $donnees['realm_reference'], $donnees['environnement'],
                $donnees['contrat_reference'], $donnees['contrat_version'], $donnees['decision_reference'] ?? null, $donnees['autorisation_reference'],
                json_encode($obligations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $donnees['quota'] ?? null, $donnees['usage_autorise'],
                $etat, $maintenant, $etat === 'AUTORISEE' ? $maintenant : null, null, $expireLe, null, null, null,
            ]);

            $this->publier($this->magasin, [
                'type_evenement' => 'CAP-CORE-021.MATCHING_ACTIVATION_' . $etat,
                'contrat_reference' => $donnees['contrat_reference'], 'contrat_version' => $donnees['contrat_version'],
                'source_reference' => PolitiqueMatching::SOURCE, 'realm_reference' => $donnees['realm_reference'],
                'finalite_reference' => $donnees['finalite_reference'], 'correlation_id' => $donnees['correlation_id'] ?? $reference,
                'survenu_le' => $maintenant, 'classification' => $segment['classification'],
                'idempotence_reference' => 'ACT-EVT-' . $reference, 'producteur_capacite_reference' => PolitiqueMatching::CAPACITE,
                'charge' => ['activation' => $reference, 'segment' => $segment['reference']],
            ]);

            return ['reference' => $reference, 'etat' => $etat, 'motif_code' => $verification['motif_code'], 'expire_le' => $expireLe];
        });
    }

    public function resoudreActivation(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_activation WHERE reference = ?', [$reference]);
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function accuserActivation(string $reference, string $acteur): array
    {
        $activation = $this->resoudreActivation($reference);
        if ($activation === null) {
            return $this->refus('ACTIVATION_INCONNUE', "activation `{$reference}` inconnue");
        }
        if ($activation['etat'] !== 'AUTORISEE') {
            return $this->refus('TRANSITION_INVALIDE', "activation `{$reference}` en état {$activation['etat']}, attendu AUTORISEE");
        }

        return $this->transaction(function () use ($reference): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare('UPDATE matching_activation SET etat = ?, active_le = ? WHERE reference = ?')
                ->execute(['ACTIVE', $maintenant, $reference]);

            return ['reference' => $reference, 'etat' => 'ACTIVE'];
        });
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function suspendreActivation(string $reference, string $acteur): array
    {
        return $this->transitionnerActivation($reference, 'SUSPENDUE', ['ACTIVE'], null);
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function revoquerActivation(string $reference, string $acteur): array
    {
        return $this->transitionnerActivation($reference, 'REVOQUEE', ['ACTIVE', 'SUSPENDUE', 'AUTORISEE'], 'revoque_le');
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    public function terminerActivation(string $reference, string $acteur): array
    {
        return $this->transitionnerActivation($reference, 'TERMINEE', ['ACTIVE', 'SUSPENDUE'], 'termine_le');
    }

    /** @return array{refus:string,detail:string}|array<string,mixed> */
    private function transitionnerActivation(string $reference, string $cible, array $etatsAutorises, ?string $colonneDate): array
    {
        $activation = $this->resoudreActivation($reference);
        if ($activation === null) {
            return $this->refus('ACTIVATION_INCONNUE', "activation `{$reference}` inconnue");
        }
        if (!in_array($activation['etat'], $etatsAutorises, true)) {
            return $this->refus('TRANSITION_INVALIDE', "activation `{$reference}` en état {$activation['etat']}");
        }

        return $this->transaction(function () use ($reference, $cible, $colonneDate): array {
            $sql = $colonneDate !== null
                ? "UPDATE matching_activation SET etat = ?, {$colonneDate} = ? WHERE reference = ?"
                : 'UPDATE matching_activation SET etat = ? WHERE reference = ?';
            $params = $colonneDate !== null ? [$cible, gmdate('c'), $reference] : [$cible, $reference];
            $this->magasin->prepare($sql)->execute($params);

            return ['reference' => $reference, 'etat' => $cible];
        });
    }

    // ==================================================================
    // Mesures (doc 02 §22, doc 03 §16)

    /**
     * @param array<string,mixed> $donnees
     * @param array{contrat_actif:bool,finalite_identique:bool,nominative:bool,nominatif_autorise_contrat:bool} $faits
     * @return array{refus:string,detail:string}|array<string,mixed>
     */
    public function enregistrerMesure(string $activationReference, array $donnees, array $faits, string $acteur): array
    {
        $activation = $this->resoudreActivation($activationReference);
        if ($activation === null) {
            return $this->refus('ACTIVATION_INCONNUE', "activation `{$activationReference}` inconnue");
        }
        $validation = Mesure::valider($faits);
        if (!$validation['valide']) {
            return $this->refus((string) $validation['motif_code'], 'mesure refusée');
        }
        foreach (['mesure_code', 'fenetre_debut', 'fenetre_fin', 'source_reference', 'contrat_reference', 'classification'] as $champ) {
            if (trim((string) ($donnees[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ obligatoire absent : {$champ}");
            }
        }

        return $this->transaction(function () use ($activation, $donnees): array {
            $reference = $this->allouerReference('matching_activation_mesure');
            $this->magasin->prepare(
                'INSERT INTO matching_activation_mesure
                 (reference,activation_reference,mesure_code,valeur_numerique,valeur_categorielle,population_reference,
                  fenetre_debut,fenetre_fin,source_reference,contrat_reference,preuve_reference,classification,recu_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $activation['reference'], (string) $donnees['mesure_code'], $donnees['valeur_numerique'] ?? null,
                $donnees['valeur_categorielle'] ?? null, $donnees['population_reference'] ?? null, (string) $donnees['fenetre_debut'],
                (string) $donnees['fenetre_fin'], (string) $donnees['source_reference'], (string) $donnees['contrat_reference'],
                $donnees['preuve_reference'] ?? null, (string) $donnees['classification'], gmdate('c'),
            ]);

            $this->publier($this->magasin, [
                'type_evenement' => 'CAP-CORE-021.MATCHING_MESURE_ENREGISTREE',
                'contrat_reference' => $donnees['contrat_reference'], 'contrat_version' => $donnees['contrat_version'] ?? '1',
                'source_reference' => PolitiqueMatching::SOURCE, 'realm_reference' => $activation['realm_reference'],
                'finalite_reference' => $activation['finalite_reference'], 'correlation_id' => $donnees['correlation_id'] ?? $reference,
                'survenu_le' => gmdate('c'), 'classification' => (string) $donnees['classification'],
                'idempotence_reference' => 'MES-EVT-' . $reference, 'producteur_capacite_reference' => PolitiqueMatching::CAPACITE,
                'charge' => ['mesure' => $reference, 'activation' => $activation['reference']],
            ]);

            return ['reference' => $reference];
        });
    }

    /** @return array<string,array{nombre:int,somme:float,moyenne:?float}> */
    public function agregerMesures(string $activationReference): array
    {
        $mesures = $this->lignes('SELECT mesure_code,valeur_numerique FROM matching_activation_mesure WHERE activation_reference = ?', [$activationReference]);

        return Mesure::agreger(array_map(static fn (array $m): array => [
            'mesure_code' => $m['mesure_code'], 'valeur_numerique' => $m['valeur_numerique'] !== null ? (float) $m['valeur_numerique'] : null,
        ], $mesures));
    }

    // ==================================================================
    // Contestations et réexamen (doc 02 §23-24, doc 03 §19)

    /**
     * @param array<string,mixed> $donnees
     * @param array{contestant_autorise:bool} $faits réserve documentée en tête de classe
     * @return array{refus:string,detail:string}|array<string,mixed>
     */
    public function ouvrirContestation(array $donnees, array $faits, string $acteur): array
    {
        $resultatReference = $donnees['resultat_reference'] ?? null;
        $resultat = $resultatReference !== null ? $this->resoudreResultat((string) $resultatReference) : null;
        $recevabilite = Contestations::verifierRecevabilite([
            'contestant_autorise' => (bool) ($faits['contestant_autorise'] ?? false),
            'resultat_existe' => $resultatReference === null || $resultat !== null,
            'motif_code' => $donnees['motif_code'] ?? null,
        ]);
        if (!$recevabilite['recevable']) {
            return $this->refus((string) $recevabilite['motif_refus'], 'contestation non recevable');
        }
        foreach (['contestant_reference', 'realm_reference', 'classification'] as $champ) {
            if (trim((string) ($donnees[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ obligatoire absent : {$champ}");
            }
        }

        return $this->transaction(function () use ($donnees): array {
            $reference = $this->allouerReference('matching_contestation');
            $this->magasin->prepare(
                'INSERT INTO matching_contestation
                 (reference,resultat_reference,segment_reference,activation_reference,contestant_reference,motif_code,
                  description_minimale,source_contestee,ouvert_le,etat,responsable,realm_reference,classification,preuve_initiale)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $donnees['resultat_reference'] ?? null, $donnees['segment_reference'] ?? null, $donnees['activation_reference'] ?? null,
                (string) $donnees['contestant_reference'], (string) $donnees['motif_code'], $donnees['description_minimale'] ?? null,
                $donnees['source_contestee'] ?? null, gmdate('c'), 'RECEVABLE', null, (string) $donnees['realm_reference'],
                (string) $donnees['classification'], null,
            ]);

            $this->publier($this->magasin, [
                'type_evenement' => 'CAP-CORE-021.MATCHING_CONTESTATION_OUVERTE',
                'contrat_reference' => 'CTR-MAT-10', 'contrat_version' => '1',
                'source_reference' => PolitiqueMatching::SOURCE, 'realm_reference' => (string) $donnees['realm_reference'],
                'finalite_reference' => $donnees['finalite_reference'] ?? PolitiqueMatching::SOURCE,
                'correlation_id' => $donnees['correlation_id'] ?? $reference, 'survenu_le' => gmdate('c'),
                'classification' => (string) $donnees['classification'], 'idempotence_reference' => 'CTS-EVT-' . $reference,
                'producteur_capacite_reference' => PolitiqueMatching::CAPACITE,
                'charge' => ['contestation' => $reference, 'resultat' => $donnees['resultat_reference'] ?? null],
            ]);

            return ['reference' => $reference, 'etat' => 'RECEVABLE'];
        });
    }

    public function resoudreContestation(string $reference): ?array
    {
        return $this->ligne('SELECT * FROM matching_contestation WHERE reference = ?', [$reference]);
    }

    /**
     * Ne réécrit jamais l'ancien résultat (doc 02 §24) : produit un constat
     * distinct et son verdict.
     *
     * @param list<string> $sourcesCorrigees
     * @return array{refus:string,detail:string}|array<string,mixed>
     */
    public function reexaminer(string $contestationReference, ?string $nouvelleExecutionReference, array $sourcesCorrigees, string $acteur): array
    {
        $contestation = $this->resoudreContestation($contestationReference);
        if ($contestation === null) {
            return $this->refus('CONTESTATION_INCONNUE', "contestation `{$contestationReference}` inconnue");
        }
        if (!in_array($contestation['etat'], ['RECEVABLE', 'EN_INSTRUCTION', 'CORRECTION_SOURCE_ATTENDUE', 'REEXECUTION_ATTENDUE'], true)) {
            return $this->refus('TRANSITION_INVALIDE', "contestation `{$contestationReference}` en état {$contestation['etat']}");
        }
        $resultatAncien = $contestation['resultat_reference'] !== null ? $this->resoudreResultat((string) $contestation['resultat_reference']) : null;
        $classeAncienne = $resultatAncien['classe_resultat'] ?? null;
        $classeNouvelle = null;
        $ancienneExecution = $resultatAncien['execution_reference'] ?? '';
        if ($nouvelleExecutionReference !== null && $resultatAncien !== null) {
            $resultatNouveau = $this->ligne(
                'SELECT classe_resultat FROM matching_resultat WHERE execution_reference = ? AND candidat_reference = ?',
                [$nouvelleExecutionReference, $resultatAncien['candidat_reference']]
            );
            $classeNouvelle = $resultatNouveau['classe_resultat'] ?? null;
        }
        $verdict = Contestations::determinerVerdict($classeAncienne, $classeNouvelle);

        return $this->transaction(function () use (
            $contestation, $nouvelleExecutionReference, $sourcesCorrigees, $ancienneExecution, $resultatAncien, $classeAncienne, $classeNouvelle, $verdict,
        ): array {
            $reference = $this->allouerReference('matching_reexamen');
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO matching_reexamen
                 (reference,contestation_reference,ancienne_execution,nouvelle_execution,sources_corrigees_json,politique_reference,
                  politique_version,resultat_ancien,resultat_nouveau,ecart_json,decision_reference,preuve_reference,termine_le,verdict)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $contestation['reference'], $ancienneExecution, $nouvelleExecutionReference,
                json_encode($sourcesCorrigees, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $resultatAncien['politique_reference'] ?? PolitiqueMatching::POLITIQUE, $resultatAncien['politique_version'] ?? '1',
                $classeAncienne, $classeNouvelle, json_encode(['ancien' => $classeAncienne, 'nouveau' => $classeNouvelle], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                null, null, $maintenant, $verdict,
            ]);
            $this->magasin->prepare('UPDATE matching_contestation SET etat = ? WHERE reference = ?')
                ->execute(['RESOLUE', $contestation['reference']]);

            $this->publier($this->magasin, [
                'type_evenement' => 'CAP-CORE-021.MATCHING_REEXAMEN_CONCLU',
                'contrat_reference' => 'CTR-MAT-10', 'contrat_version' => '1',
                'source_reference' => PolitiqueMatching::SOURCE, 'realm_reference' => $contestation['realm_reference'],
                'finalite_reference' => PolitiqueMatching::SOURCE, 'correlation_id' => $reference, 'survenu_le' => $maintenant,
                'classification' => $contestation['classification'], 'idempotence_reference' => 'REX-EVT-' . $reference,
                'producteur_capacite_reference' => PolitiqueMatching::CAPACITE,
                'charge' => ['reexamen' => $reference, 'contestation' => $contestation['reference'], 'verdict' => $verdict],
            ]);

            return ['reference' => $reference, 'verdict' => $verdict];
        });
    }

    // ==================================================================
    // Internes

    private function allouerReference(string $type): string
    {
        $prefixe = PolitiqueMatching::PREFIXE[$type] ?? throw new ExceptionMatching("type de référence `{$type}` inconnu");
        $this->magasin->prepare('INSERT INTO compteur_reference_matching(type,dernier) VALUES(?,0) ON CONFLICT(type) DO NOTHING')
            ->execute([$type]);
        $sql = 'SELECT dernier FROM compteur_reference_matching WHERE type = ?';
        if ((string) $this->magasin->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sql .= ' FOR UPDATE';
        }
        $st = $this->magasin->prepare($sql);
        $st->execute([$type]);
        $numero = ((int) $st->fetchColumn()) + 1;
        $this->magasin->prepare('UPDATE compteur_reference_matching SET dernier = ? WHERE type = ?')->execute([$numero, $type]);

        return sprintf('%s-%06d', $prefixe, $numero);
    }

    /** @param array<string,mixed> $intention */
    private function publier(\PDO $pdo, array $intention): void
    {
        OutboxProducteur::preparerEvenement($pdo, $intention);
    }

    private function transaction(callable $operation): mixed
    {
        $propre = !$this->magasin->inTransaction();
        if ($propre) {
            $this->magasin->beginTransaction();
        }
        try {
            $resultat = $operation();
            if ($propre) {
                $this->magasin->commit();
            }

            return $resultat;
        } catch (\Throwable $e) {
            if ($propre && $this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }

    /** @param list<mixed> $params */
    private function ligne(string $sql, array $params): ?array
    {
        $st = $this->magasin->prepare($sql);
        $st->execute($params);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @param list<mixed> $params @return list<array<string,mixed>> */
    private function lignes(string $sql, array $params): array
    {
        $st = $this->magasin->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    /** @return array{refus:string,detail:string} */
    private function refus(string $code, string $detail): array
    {
        return ['refus' => $code, 'detail' => $detail];
    }
}
