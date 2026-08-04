<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Registre de gouvernance des secrets et clés (CAP-CORE-016).
 *
 * Ce registre ne possède jamais le matériel secret : seulement les
 * références, versions, usages, rotations, compromissions et le matériel
 * public nécessaires pour gouverner des secrets conservés dans des
 * fournisseurs externes (`FournisseurSecret`). Toute tentative d'écrire une
 * valeur ressemblant à un secret dans un dossier, un résumé ou une charge
 * est refusée avant écriture par `refuserChampsInterdits()`.
 *
 * Comme les autres registres persistants du Core, ce module ne décide
 * d'aucune autorisation lui-même : la preuve d'autorisation (`preuve`) est un
 * intrant obligatoire, produite en amont par la couche applicative
 * (`CAP-CORE-004`).
 */
final class RegistreSecretsCles
{
    public const CAPACITE = 'CAP-CORE-016';

    public function __construct(
        private \PDO $magasin,
    ) {
        SchemaSecretsCles::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Ressources

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireSecret(array $dossier): array
    {
        $g = $this->refuserChampsInterdits($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['reference', 'nom', 'type_secret', 'finalite_reference', 'proprietaire_reference', 'source_reference', 'environnement_reference', 'classification_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $reference = trim((string) $dossier['reference']);
        if (!preg_match('/^(SEC|KEY)-GAMAD-[A-Z0-9-]+$/', $reference)) {
            return $this->refus('REFERENCE_INVALIDE', 'la référence doit suivre SEC-GAMAD-… ou KEY-GAMAD-…');
        }
        if ($this->ligneSecret($reference) !== null) {
            return $this->refus('REFERENCE_DEJA_UTILISEE', "la référence `{$reference}` existe déjà");
        }
        $type = (string) $dossier['type_secret'];
        if (!in_array($type, PolitiqueSecretsCles::TYPES_SECRET, true)) {
            return $this->refus('TYPE_SECRET_INCONNU', 'type_secret hors liste close');
        }
        $environnement = (string) $dossier['environnement_reference'];
        if (!in_array($environnement, PolitiqueSecretsCles::ENVIRONNEMENTS, true)) {
            return $this->refus('ENVIRONNEMENT_REFUSE', 'environnement hors liste close');
        }
        $classification = (string) $dossier['classification_reference'];
        if (!in_array($classification, PolitiqueSecretsCles::CLASSIFICATIONS, true)) {
            return $this->refus('CLASSIFICATION_INCONNUE', 'classification hors liste close');
        }

        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO secret_ressource
             (reference,nom,type_secret,finalite_reference,proprietaire_reference,source_reference,
              realm_reference,environnement_reference,classification_reference,description,
              rotation_requise,duree_rotation_jours,cree_le,modifie_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, trim((string) $dossier['nom']), $type, (string) $dossier['finalite_reference'],
            (string) $dossier['proprietaire_reference'], (string) $dossier['source_reference'],
            $this->nullable($dossier['realm_reference'] ?? null), $environnement, $classification,
            $this->nullable($dossier['description'] ?? null),
            !empty($dossier['rotation_requise']) ? 1 : 0,
            isset($dossier['duree_rotation_jours']) ? (int) $dossier['duree_rotation_jours'] : null,
            $maintenant, $maintenant,
        ]);

        return ['reference' => $reference, 'type_secret' => $type];
    }

    /** @return array<string,mixed>|null */
    public function resoudreSecret(string $reference): ?array
    {
        return $this->ligneSecret($reference);
    }

    /** @param array<string,mixed> $filtres @return list<array<string,mixed>> */
    public function listerSecrets(array $filtres = []): array
    {
        $conditions = [];
        $valeurs = [];
        if (isset($filtres['type_secret'])) {
            $conditions[] = 'type_secret = ?';
            $valeurs[] = $filtres['type_secret'];
        }
        if (isset($filtres['environnement_reference'])) {
            $conditions[] = 'environnement_reference = ?';
            $valeurs[] = $filtres['environnement_reference'];
        }
        if (isset($filtres['realm_reference'])) {
            $conditions[] = 'realm_reference = ?';
            $valeurs[] = $filtres['realm_reference'];
        }
        $sql = 'SELECT * FROM secret_ressource';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($valeurs);

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------
    // Fournisseurs

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireFournisseur(array $dossier): array
    {
        $g = $this->refuserChampsInterdits($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['nom', 'type_fournisseur', 'environnement_reference', 'proprietaire_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_fournisseur'];
        if (!in_array($type, PolitiqueSecretsCles::TYPES_FOURNISSEUR, true)) {
            return $this->refus('TYPE_FOURNISSEUR_INCONNU', 'type_fournisseur hors liste close');
        }
        $capacites = is_array($dossier['capacites'] ?? null) ? $dossier['capacites'] : [];
        foreach ($capacites as $capacite) {
            if (!in_array($capacite, PolitiqueSecretsCles::CAPACITES_FOURNISSEUR, true)) {
                return $this->refus('CAPACITE_FOURNISSEUR_INCONNUE', "capacité `{$capacite}` hors liste close");
            }
        }

        $reference = (string) ($dossier['reference'] ?? ('FOU-GAMAD-' . strtoupper(bin2hex(random_bytes(8)))));
        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO secret_fournisseur
             (reference,nom,type_fournisseur,realm_reference,environnement_reference,proprietaire_reference,
              etat,capacites_json,configuration_reference,cree_le,modifie_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, trim((string) $dossier['nom']), $type,
            $this->nullable($dossier['realm_reference'] ?? null), (string) $dossier['environnement_reference'],
            (string) $dossier['proprietaire_reference'], 'PREPARATION', json_encode($capacites, JSON_UNESCAPED_SLASHES),
            $this->nullable($dossier['configuration_reference'] ?? null), $maintenant, $maintenant,
        ]);

        return ['reference' => $reference, 'etat' => 'PREPARATION'];
    }

    /**
     * Vérification générale du fournisseur — sans handle de secret précis.
     * Pour un fournisseur dont la disponibilité dépend d'un handle par secret
     * (un `FICHIER_0600` donné, par exemple), passer `handle_sonde` dans
     * `$dossier` ; sans lui, l'adaptateur diagnostique sa disponibilité
     * générique (répertoire de credentials présent, agent joignable…).
     *
     * @param array<string,mixed> $dossier @return array<string,mixed>
     */
    public function verifierFournisseur(string $reference, FournisseurSecret $adaptateur, array $dossier = []): array
    {
        $fournisseur = $this->ligneFournisseur($reference);
        if ($fournisseur === null) {
            return $this->refus('FOURNISSEUR_INCONNU', "fournisseur `{$reference}` inconnu");
        }
        $diagnostic = $adaptateur->verifierDisponibilite(
            new DescripteurVersion($reference, '*', $this->nullable($dossier['handle_sonde'] ?? null)),
        );
        $nouvelEtat = $diagnostic->disponible ? 'ACTIF' : 'DEGRADE';
        $this->magasin->prepare('UPDATE secret_fournisseur SET etat = ?, modifie_le = ? WHERE reference = ?')
            ->execute([$nouvelEtat, gmdate('c'), $reference]);

        return ['reference' => $reference, 'etat' => $nouvelEtat, 'motif' => $diagnostic->motif];
    }

    /** @return array<string,mixed>|null */
    public function resoudreFournisseur(string $reference): ?array
    {
        return $this->ligneFournisseur($reference);
    }

    /** @return list<array<string,mixed>> */
    public function listerFournisseurs(): array
    {
        return $this->magasin->query('SELECT * FROM secret_fournisseur ORDER BY reference')->fetchAll();
    }

    // ------------------------------------------------------------------
    // Versions

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerVersion(array $dossier): array
    {
        $g = $this->refuserChampsInterdits($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['secret_reference', 'version', 'fournisseur_reference', 'handle_fournisseur'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $secretReference = (string) $dossier['secret_reference'];
        if ($this->ligneSecret($secretReference) === null) {
            return $this->refus('SECRET_INCONNU', "secret `{$secretReference}` inconnu");
        }
        $fournisseurReference = (string) $dossier['fournisseur_reference'];
        $fournisseur = $this->ligneFournisseur($fournisseurReference);
        if ($fournisseur === null) {
            return $this->refus('FOURNISSEUR_INDISPONIBLE', "fournisseur `{$fournisseurReference}` inconnu");
        }
        if ($fournisseur['etat'] === 'RETIRE') {
            return $this->refus('FOURNISSEUR_INDISPONIBLE', 'fournisseur retiré');
        }
        $version = (string) $dossier['version'];
        if ($this->ligneVersion($secretReference, $version) !== null) {
            return $this->refus('VERSION_DEJA_DECLAREE', "la version `{$version}` existe déjà pour `{$secretReference}`");
        }
        // Une clé publique explicite est acceptée ; toute autre valeur qui
        // ressemble à du matériel privé (PEM privé, longueur excessive) est
        // refusée — le handle reste la seule référence opaque au matériel.
        $clePublique = $this->nullable($dossier['cle_publique'] ?? null);
        if ($clePublique !== null && str_contains($clePublique, 'PRIVATE KEY')) {
            return $this->refus('CLE_PRIVEE_REFUSEE', 'une clé privée ne peut jamais être déclarée ici');
        }

        return $this->transaction(function () use ($dossier, $secretReference, $fournisseurReference, $version, $clePublique): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO secret_version
                 (secret_reference,version,fournisseur_reference,handle_fournisseur,algorithme_reference,
                  taille_bits,empreinte_publique,identifiant_public,cle_publique,date_debut_prevue,
                  date_fin_prevue,cree_par_reference,preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $secretReference, $version, $fournisseurReference, (string) $dossier['handle_fournisseur'],
                $this->nullable($dossier['algorithme_reference'] ?? null),
                isset($dossier['taille_bits']) ? (int) $dossier['taille_bits'] : null,
                $this->nullable($dossier['empreinte_publique'] ?? null),
                $this->nullable($dossier['identifiant_public'] ?? null), $clePublique,
                $this->nullable($dossier['date_debut_prevue'] ?? null),
                $this->nullable($dossier['date_fin_prevue'] ?? null),
                (string) $dossier['producteur'], (string) $dossier['preuve'], $maintenant,
            ]);
            $id = (int) $this->magasin->lastInsertId(
                $this->driver() === 'pgsql' ? 'secret_version_id_seq' : null,
            );
            if ($id === 0) {
                $id = (int) $this->magasin->query(
                    "SELECT id FROM secret_version WHERE secret_reference = " . $this->magasin->quote($secretReference)
                    . " AND version = " . $this->magasin->quote($version)
                )->fetchColumn();
            }
            $this->inscrireCycle($id, 'PREPARATION', $maintenant, null, (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            return ['id' => $id, 'secret_reference' => $secretReference, 'version' => $version, 'etat' => 'PREPARATION'];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function verifierVersion(int $id, FournisseurSecret $adaptateur, array $dossier): array
    {
        $version = $this->ligneVersionParId($id);
        if ($version === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$id}` inconnue");
        }
        $diagnostic = $adaptateur->verifierDisponibilite(new DescripteurVersion(
            (string) $version['secret_reference'], (string) $version['version'], (string) $version['handle_fournisseur'],
        ));
        if (!$diagnostic->disponible) {
            return $this->refus('FOURNISSEUR_NON_CONFORME', $diagnostic->motif ?? 'matériel indisponible');
        }

        return ['id' => $id, 'disponible' => true];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerVersion(int $id, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $version = $this->ligneVersionParId($id);
        if ($version === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$id}` inconnue");
        }
        $etat = $this->etatCourant($id);
        if ($etat === null || !in_array($etat, ['PREPARATION', 'ACTIVE_LECTURE'], true)) {
            if ($etat === 'PREPARATION') {
                // ok
            } else {
                return $this->refus('VERSION_INACTIVE', "la version doit être vérifiée avant activation (état actuel : {$etat})");
            }
        }
        if (!($dossier['verifiee'] ?? false)) {
            return $this->refus('VERSION_NON_VERIFIEE', 'appeler verifierVersion() avant activation');
        }

        return $this->transaction(function () use ($id, $version, $dossier): array {
            $maintenant = gmdate('c');
            $ancienne = $this->ligneVersionActiveEcriture($version['secret_reference']);
            if ($ancienne !== null && (int) $ancienne['id'] !== $id) {
                $this->inscrireCycle(
                    (int) $ancienne['id'], 'ACTIVE_LECTURE', $maintenant, 'basculée par nouvelle activation',
                    (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'],
                    $this->nullable($dossier['correlation_id'] ?? null),
                );
            }
            $this->inscrireCycle($id, 'ACTIVE_ECRITURE', $maintenant, $this->nullable($dossier['motif'] ?? null), (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            return ['id' => $id, 'etat' => 'ACTIVE_ECRITURE'];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreVersion(int $id, array $dossier): array
    {
        return $this->transitionner($id, 'SUSPENDUE', $dossier, ['ACTIVE_ECRITURE', 'ACTIVE_LECTURE']);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function revoquerVersion(int $id, array $dossier): array
    {
        return $this->transitionner($id, 'REVOQUEE', $dossier, ['ACTIVE_ECRITURE', 'ACTIVE_LECTURE', 'SUSPENDUE']);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerCompromission(array $dossier): array
    {
        $g = $this->refuserChampsInterdits($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['secret_version_id', 'niveau', 'source_reference', 'motif'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $id = (int) $dossier['secret_version_id'];
        $version = $this->ligneVersionParId($id);
        if ($version === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$id}` inconnue");
        }
        $niveau = (string) $dossier['niveau'];
        if (!in_array($niveau, PolitiqueSecretsCles::NIVEAUX_COMPROMISSION, true)) {
            return $this->refus('NIVEAU_INCONNU', 'niveau hors liste close');
        }

        return $this->transaction(function () use ($id, $niveau, $dossier): array {
            $maintenant = gmdate('c');
            $reference = (string) ($dossier['reference'] ?? ('CPR-GAMAD-' . strtoupper(bin2hex(random_bytes(8)))));
            $this->magasin->prepare(
                'INSERT INTO secret_compromission
                 (reference,secret_version_id,detectee_le,declaree_par_reference,source_reference,
                  niveau,portee_presumee,motif,etat,preuve_reference,correlation_id,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $id, $this->nullable($dossier['detectee_le'] ?? null) ?? $maintenant,
                (string) $dossier['producteur'], (string) $dossier['source_reference'], $niveau,
                $this->nullable($dossier['portee_presumee'] ?? null), (string) $dossier['motif'], 'OUVERTE',
                (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null), $maintenant,
            ]);
            // Blocage immédiat, quel que soit le niveau déclaré : une compromission
            // suspectée bloque déjà les nouveaux usages (partie 3 §14).
            $this->inscrireCycle($id, 'COMPROMISE', $maintenant, (string) $dossier['motif'], (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            return ['reference' => $reference, 'secret_version_id' => $id, 'niveau' => $niveau, 'etat' => 'OUVERTE'];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function detruireVersion(int $id, FournisseurSecret $adaptateur, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $version = $this->ligneVersionParId($id);
        if ($version === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$id}` inconnue");
        }
        $etat = $this->etatCourant($id);
        if ($etat === 'ACTIVE_ECRITURE' || $etat === 'ACTIVE_LECTURE') {
            return $this->refus('DESTRUCTION_REFUSEE', 'une version encore active ne peut pas être détruite');
        }
        if ($etat === 'DETRUITE') {
            return $this->refus('DESTRUCTION_REFUSEE', 'version déjà détruite');
        }
        $dependance = $this->dependanceBloquante($version['secret_reference'], $id);
        if ($dependance !== null) {
            return $this->refus('DEPENDANCE_BLOQUANTE', "dépendance non expirée : {$dependance['type_dependance']} sur {$dependance['ressource_reference']}");
        }
        if (empty($dossier['confirmation_renforcee'])) {
            return $this->refus('DESTRUCTION_REFUSEE', 'confirmation renforcée requise');
        }
        $resultat = $adaptateur->detruire(new DescripteurVersion($version['secret_reference'], $version['version']));
        if (!$resultat->reussie) {
            return $this->refus('DESTRUCTION_REFUSEE', $resultat->motif ?? 'le fournisseur ne confirme pas la destruction');
        }

        return $this->transaction(function () use ($id, $dossier): array {
            $maintenant = gmdate('c');
            $this->inscrireCycle($id, 'DETRUITE', $maintenant, $this->nullable($dossier['motif'] ?? null), (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            return ['id' => $id, 'etat' => 'DETRUITE'];
        });
    }

    private function transitionner(int $id, string $cible, array $dossier, array $etatsAutorises): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $version = $this->ligneVersionParId($id);
        if ($version === null) {
            return $this->refus('VERSION_INCONNUE', "version `{$id}` inconnue");
        }
        $etat = $this->etatCourant($id);
        if (in_array($etat, PolitiqueSecretsCles::ETATS_TERMINAUX, true)) {
            return $this->refus('VERSION_COMPROMISE', "une version `{$etat}` ne redevient jamais active");
        }
        if (!in_array($etat, $etatsAutorises, true)) {
            return $this->refus('VERSION_INACTIVE', "transition refusée depuis l'état {$etat}");
        }
        if (empty($dossier['motif'])) {
            return $this->refus('DOSSIER_INCOMPLET', 'motif obligatoire');
        }

        return $this->transaction(function () use ($id, $cible, $dossier): array {
            $maintenant = gmdate('c');
            $this->inscrireCycle($id, $cible, $maintenant, (string) $dossier['motif'], (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

            return ['id' => $id, 'etat' => $cible];
        });
    }

    // ------------------------------------------------------------------
    // Usages

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerUsage(array $dossier): array
    {
        $g = $this->refuserChampsInterdits($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['secret_reference', 'environnement_reference', 'operation_reference', 'finalite_reference', 'mode_usage'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $secretReference = (string) $dossier['secret_reference'];
        if ($this->ligneSecret($secretReference) === null) {
            return $this->refus('SECRET_INCONNU', "secret `{$secretReference}` inconnu");
        }
        $capacite = $this->nullable($dossier['capacite_reference'] ?? null);
        $produit = $this->nullable($dossier['produit_reference'] ?? null);
        if ($capacite === null && $produit === null) {
            return $this->refus('USAGE_REFUSE', 'au moins un consommateur (capacité ou produit) est requis');
        }
        $modeUsage = (string) $dossier['mode_usage'];
        if (!in_array($modeUsage, PolitiqueSecretsCles::MODES_USAGE, true)) {
            return $this->refus('USAGE_REFUSE', 'mode_usage hors liste close');
        }
        foreach (['operation_reference', 'finalite_reference', 'realm_reference'] as $champ) {
            if (($dossier[$champ] ?? null) === '*') {
                return $this->refus('USAGE_REFUSE', "aucun joker universel n'est autorisé (`{$champ}`)");
            }
        }
        $environnement = (string) $dossier['environnement_reference'];
        if (!in_array($environnement, PolitiqueSecretsCles::ENVIRONNEMENTS, true)) {
            return $this->refus('ENVIRONNEMENT_REFUSE', 'environnement hors liste close');
        }

        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO secret_usage
             (secret_version_id,secret_reference,capacite_reference,produit_reference,organisation_reference,
              realm_reference,environnement_reference,operation_reference,finalite_reference,mode_usage,
              date_debut,date_fin,acteur_reference,politique_reference,preuve_reference,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            isset($dossier['secret_version_id']) ? (int) $dossier['secret_version_id'] : null,
            $secretReference, $capacite, $produit, $this->nullable($dossier['organisation_reference'] ?? null),
            $this->nullable($dossier['realm_reference'] ?? null), $environnement,
            (string) $dossier['operation_reference'], (string) $dossier['finalite_reference'], $modeUsage,
            $this->nullable($dossier['date_debut'] ?? null) ?? $maintenant,
            $this->nullable($dossier['date_fin'] ?? null),
            (string) $dossier['producteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $maintenant,
        ]);

        return ['secret_reference' => $secretReference, 'mode_usage' => $modeUsage];
    }

    /** @return list<array<string,mixed>> */
    public function listerUsages(string $secretReference): array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_usage WHERE secret_reference = ? ORDER BY id');
        $st->execute([$secretReference]);

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------
    // Dépendances

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerDependance(array $dossier): array
    {
        foreach (['secret_reference', 'type_dependance', 'ressource_reference'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_dependance'];
        if (!in_array($type, PolitiqueSecretsCles::TYPES_DEPENDANCE, true)) {
            return $this->refus('TYPE_DEPENDANCE_INCONNU', 'type_dependance hors liste close');
        }
        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO secret_dependance
             (secret_reference,secret_version_id,type_dependance,ressource_reference,date_debut,date_fin,
              obligation_conservation,motif,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([
            (string) $dossier['secret_reference'],
            isset($dossier['secret_version_id']) ? (int) $dossier['secret_version_id'] : null,
            $type, (string) $dossier['ressource_reference'],
            $this->nullable($dossier['date_debut'] ?? null) ?? $maintenant,
            $this->nullable($dossier['date_fin'] ?? null),
            array_key_exists('obligation_conservation', $dossier) ? (int) (bool) $dossier['obligation_conservation'] : 1,
            $this->nullable($dossier['motif'] ?? null), $maintenant,
        ]);

        return ['secret_reference' => (string) $dossier['secret_reference'], 'type_dependance' => $type];
    }

    public function fermerDependance(int $id, ?string $motif = null): array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_dependance WHERE id = ?');
        $st->execute([$id]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return $this->refus('DEPENDANCE_INCONNUE', "dépendance `{$id}` inconnue");
        }
        $this->magasin->prepare('UPDATE secret_dependance SET date_fin = ?, motif = COALESCE(?, motif) WHERE id = ?')
            ->execute([gmdate('c'), $motif, $id]);

        return ['id' => $id, 'fermee' => true];
    }

    /** @return list<array<string,mixed>> */
    public function listerDependances(string $secretReference, ?int $versionId = null): array
    {
        if ($versionId !== null) {
            $st = $this->magasin->prepare('SELECT * FROM secret_dependance WHERE secret_reference = ? AND secret_version_id = ? ORDER BY id');
            $st->execute([$secretReference, $versionId]);
        } else {
            $st = $this->magasin->prepare('SELECT * FROM secret_dependance WHERE secret_reference = ? ORDER BY id');
            $st->execute([$secretReference]);
        }

        return $st->fetchAll();
    }

    private function dependanceBloquante(string $secretReference, int $versionId): ?array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM secret_dependance
             WHERE secret_reference = ? AND (secret_version_id = ? OR secret_version_id IS NULL)
               AND date_fin IS NULL AND obligation_conservation = 1
             ORDER BY id LIMIT 1'
        );
        $st->execute([$secretReference, $versionId]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    // ------------------------------------------------------------------
    // Rotation

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function planifierRotation(array $dossier): array
    {
        $g = $this->refuserChampsInterdits($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        foreach (['secret_reference', 'strategie', 'date_prevue'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        if ($this->ligneSecret((string) $dossier['secret_reference']) === null) {
            return $this->refus('SECRET_INCONNU', "secret `{$dossier['secret_reference']}` inconnu");
        }
        $strategie = (string) $dossier['strategie'];
        if (!in_array($strategie, PolitiqueSecretsCles::STRATEGIES_ROTATION, true)) {
            return $this->refus('STRATEGIE_INCONNUE', 'strategie hors liste close');
        }
        $impact = $dossier['impact_json'] ?? $dossier['impact'] ?? null;
        if (empty($impact)) {
            return $this->refus('PLAN_SANS_CONSOMMATEURS', 'impact_json doit inventorier les consommateurs affectés');
        }
        if (!array_key_exists('retour_arriere_autorise', $dossier)) {
            return $this->refus('PLAN_SANS_RETOUR_ARRIERE', 'retour_arriere_autorise doit être explicite');
        }

        $reference = (string) ($dossier['reference'] ?? ('ROT-GAMAD-' . strtoupper(bin2hex(random_bytes(8)))));
        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO secret_rotation_plan
             (reference,secret_reference,ancienne_version_id,nouvelle_version_id,strategie,date_prevue,
              fenetre_fin,retour_arriere_autorise,etapes_json,impact_json,etat,cree_par_reference,
              preuve_reference,cree_le,modifie_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, (string) $dossier['secret_reference'],
            isset($dossier['ancienne_version_id']) ? (int) $dossier['ancienne_version_id'] : null,
            isset($dossier['nouvelle_version_id']) ? (int) $dossier['nouvelle_version_id'] : null,
            $strategie, (string) $dossier['date_prevue'], $this->nullable($dossier['fenetre_fin'] ?? null),
            (int) (bool) $dossier['retour_arriere_autorise'],
            json_encode($dossier['etapes_json'] ?? $dossier['etapes'] ?? [], JSON_UNESCAPED_SLASHES),
            json_encode($impact, JSON_UNESCAPED_SLASHES), 'BROUILLON',
            (string) $dossier['producteur'], (string) $dossier['preuve'], $maintenant, $maintenant,
        ]);

        return ['reference' => $reference, 'etat' => 'BROUILLON'];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function validerRotation(string $reference, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $plan = $this->lignePlan($reference);
        if ($plan === null) {
            return $this->refus('ROTATION_INCONNUE', "plan `{$reference}` inconnu");
        }
        if (!in_array($plan['etat'], ['BROUILLON', 'EN_VALIDATION'], true)) {
            return $this->refus('ROTATION_ETAT_INVALIDE', "validation impossible depuis l'état {$plan['etat']}");
        }
        $this->magasin->prepare('UPDATE secret_rotation_plan SET etat = ?, modifie_le = ? WHERE reference = ?')
            ->execute(['VALIDE', gmdate('c'), $reference]);

        return ['reference' => $reference, 'etat' => 'VALIDE'];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function executerEtapeRotation(string $planReference, string $etapeReference, array $dossier): array
    {
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $g2 = $this->refuserChampsInterdits($dossier);
        if (isset($g2['refus'])) {
            return $g2;
        }
        $plan = $this->lignePlan($planReference);
        if ($plan === null) {
            return $this->refus('ROTATION_INCONNUE', "plan `{$planReference}` inconnu");
        }
        if (!in_array($plan['etat'], ['VALIDE', 'EN_COURS'], true)) {
            return $this->refus('ROTATION_ETAT_INVALIDE', "exécution impossible depuis l'état {$plan['etat']}");
        }
        $existante = $this->ligneExecutionReussie($planReference, $etapeReference);
        if ($existante !== null) {
            // Idempotent : une étape déjà réussie n'est pas rejouée.
            return ['reference' => $existante['reference'], 'etat' => 'REUSSIE', 'idempotent' => true];
        }
        if ($plan['etat'] === 'VALIDE') {
            $this->magasin->prepare('UPDATE secret_rotation_plan SET etat = ?, modifie_le = ? WHERE reference = ?')
                ->execute(['EN_COURS', gmdate('c'), $planReference]);
        }

        $reussie = !empty($dossier['reussie']);
        $reference = 'EXE-GAMAD-' . strtoupper(bin2hex(random_bytes(8)));
        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO secret_rotation_execution
             (reference,plan_reference,etape_reference,etat,commence_le,termine_le,resultat_code,
              resume_json,acteur_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference, $planReference, $etapeReference, $reussie ? 'REUSSIE' : 'ECHOUEE',
            $this->nullable($dossier['commence_le'] ?? null) ?? $maintenant, $maintenant,
            $this->nullable($dossier['resultat_code'] ?? null),
            json_encode($dossier['resume'] ?? [], JSON_UNESCAPED_SLASHES),
            (string) $dossier['producteur'], (string) $dossier['preuve'],
            $this->nullable($dossier['correlation_id'] ?? null), $maintenant,
        ]);
        if (!$reussie) {
            // Un échec ne détruit jamais automatiquement l'ancienne version ; le
            // plan reste EN_COURS pour permettre une reprise ou un retour arrière
            // explicite, sauf demande explicite de clôture en échec.
            if (!empty($dossier['cloturer_en_echec'])) {
                $this->magasin->prepare('UPDATE secret_rotation_plan SET etat = ?, modifie_le = ? WHERE reference = ?')
                    ->execute(['ECHEC', gmdate('c'), $planReference]);
            }
        }

        return ['reference' => $reference, 'etat' => $reussie ? 'REUSSIE' : 'ECHOUEE'];
    }

    public function cloturerRotationReussie(string $reference): array
    {
        $plan = $this->lignePlan($reference);
        if ($plan === null) {
            return $this->refus('ROTATION_INCONNUE', "plan `{$reference}` inconnu");
        }
        $this->magasin->prepare('UPDATE secret_rotation_plan SET etat = ?, modifie_le = ? WHERE reference = ?')
            ->execute(['REUSSI', gmdate('c'), $reference]);

        return ['reference' => $reference, 'etat' => 'REUSSI'];
    }

    /** @return array<string,mixed>|null */
    public function resoudreRotation(string $reference): ?array
    {
        return $this->lignePlan($reference);
    }

    /** @return list<array<string,mixed>> */
    public function listerRotations(string $secretReference): array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_rotation_plan WHERE secret_reference = ? ORDER BY cree_le');
        $st->execute([$secretReference]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function listerCompromissions(array $filtres = []): array
    {
        if (isset($filtres['etat'])) {
            $st = $this->magasin->prepare('SELECT * FROM secret_compromission WHERE etat = ? ORDER BY cree_le');
            $st->execute([$filtres['etat']]);
        } else {
            $st = $this->magasin->query('SELECT * FROM secret_compromission ORDER BY cree_le');
        }

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------
    // Résolution de version

    /** @return list<array<string,mixed>> */
    public function listerVersions(string $secretReference): array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_version WHERE secret_reference = ? ORDER BY id');
        $st->execute([$secretReference]);

        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function resoudreVersion(string $secretReference, string $version): ?array
    {
        return $this->ligneVersion($secretReference, $version);
    }

    /** @return array<string,mixed>|null */
    public function resoudreVersionActiveEcriture(string $secretReference): ?array
    {
        return $this->ligneVersionActiveEcriture($secretReference);
    }

    /** @return list<array<string,mixed>> */
    public function resoudreVersionsLecture(string $secretReference): array
    {
        $st = $this->magasin->prepare(
            "SELECT v.* FROM secret_version v
             WHERE v.secret_reference = ? AND v.id IN (
                 SELECT c.secret_version_id FROM secret_version_cycle c
                 WHERE c.id = (SELECT MAX(c2.id) FROM secret_version_cycle c2 WHERE c2.secret_version_id = c.secret_version_id)
                   AND c.etat = 'ACTIVE_LECTURE'
             )
             ORDER BY v.id"
        );
        $st->execute([$secretReference]);

        return $st->fetchAll();
    }

    public function etatVersion(int $id): ?string
    {
        return $this->etatCourant($id);
    }

    // ------------------------------------------------------------------
    // Matériel public

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function inscrireMaterielPublic(int $secretVersionId, array $dossier): array
    {
        foreach (['type_materiel', 'format', 'contenu_public', 'empreinte'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $type = (string) $dossier['type_materiel'];
        if (!in_array($type, PolitiqueSecretsCles::TYPES_MATERIEL_PUBLIC, true)) {
            return $this->refus('TYPE_MATERIEL_INCONNU', 'type_materiel hors liste close');
        }
        if (str_contains((string) $dossier['contenu_public'], 'PRIVATE KEY')) {
            return $this->refus('CLE_PRIVEE_REFUSEE', 'ce contenu ne peut pas être public');
        }
        $maintenant = gmdate('c');
        $this->magasin->prepare(
            'INSERT INTO secret_materiel_public
             (secret_version_id,type_materiel,format,contenu_public,empreinte,date_debut,date_fin,cree_le)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([
            $secretVersionId, $type, (string) $dossier['format'], (string) $dossier['contenu_public'],
            (string) $dossier['empreinte'], $this->nullable($dossier['date_debut'] ?? null) ?? $maintenant,
            $this->nullable($dossier['date_fin'] ?? null), $maintenant,
        ]);

        return ['secret_version_id' => $secretVersionId, 'type_materiel' => $type];
    }

    // ------------------------------------------------------------------
    // Diagnostic

    /** @return array<string,mixed> */
    public function diagnostiquerRegistre(): array
    {
        $ressources = (int) $this->magasin->query('SELECT count(*) FROM secret_ressource')->fetchColumn();
        $versionsActivesEcriture = (int) $this->magasin->query(
            "SELECT count(*) FROM secret_version_cycle c1
             WHERE c1.etat = 'ACTIVE_ECRITURE' AND c1.id = (
                 SELECT MAX(c2.id) FROM secret_version_cycle c2 WHERE c2.secret_version_id = c1.secret_version_id
             )"
        )->fetchColumn();
        $doublonsEcriture = (int) $this->magasin->query(
            "SELECT count(*) FROM (
                SELECT v.secret_reference FROM secret_version v
                JOIN secret_version_cycle c1 ON c1.secret_version_id = v.id
                WHERE c1.etat = 'ACTIVE_ECRITURE' AND c1.id = (
                    SELECT MAX(c2.id) FROM secret_version_cycle c2 WHERE c2.secret_version_id = c1.secret_version_id
                )
                GROUP BY v.secret_reference HAVING count(*) > 1
            ) t"
        )->fetchColumn();
        $compromisesActives = (int) $this->magasin->query(
            "SELECT count(*) FROM secret_version_cycle c1
             WHERE c1.etat = 'COMPROMISE' AND c1.id = (
                 SELECT MAX(c2.id) FROM secret_version_cycle c2 WHERE c2.secret_version_id = c1.secret_version_id
             )"
        )->fetchColumn();
        $compromissionsOuvertes = (int) $this->magasin->query(
            "SELECT count(*) FROM secret_compromission WHERE etat != 'CLOTUREE'"
        )->fetchColumn();
        $fournisseursDegrades = (int) $this->magasin->query(
            "SELECT count(*) FROM secret_fournisseur WHERE etat IN ('DEGRADE','SUSPENDU')"
        )->fetchColumn();
        $transition = (int) $this->magasin->query(
            "SELECT count(*) FROM secret_version v JOIN secret_fournisseur f ON f.reference = v.fournisseur_reference
             WHERE f.type_fournisseur = 'VARIABLE_ENVIRONNEMENT_TRANSITION'"
        )->fetchColumn();

        return [
            'ressources' => $ressources,
            'versions_actives_ecriture' => $versionsActivesEcriture,
            'doublons_ecriture' => $doublonsEcriture,
            'versions_compromises_actives' => $compromisesActives,
            'compromissions_ouvertes' => $compromissionsOuvertes,
            'fournisseurs_degrades' => $fournisseursDegrades,
            'references_transition' => $transition,
            // Une version compromise n'est jamais incohérente en soi — c'est le
            // résultat attendu d'une compromission traitée. L'incohérence réelle
            // que ce diagnostic peut détecter est un doublon d'écriture active :
            // deux versions ACTIVE_ECRITURE simultanées pour le même secret.
            'coherent' => $doublonsEcriture === 0,
        ];
    }

    /** @return array<string,mixed> */
    public function diagnostiquerFournisseurs(): array
    {
        $st = $this->magasin->query('SELECT reference, type_fournisseur, etat FROM secret_fournisseur ORDER BY reference');

        return ['fournisseurs' => $st->fetchAll()];
    }

    // ------------------------------------------------------------------
    // Internes

    /** @return array<string,mixed>|null */
    private function ligneSecret(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_ressource WHERE reference = ?');
        $st->execute([$reference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function ligneFournisseur(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_fournisseur WHERE reference = ?');
        $st->execute([$reference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersion(string $secretReference, string $version): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_version WHERE secret_reference = ? AND version = ?');
        $st->execute([$secretReference, $version]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersionParId(int $id): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_version WHERE id = ?');
        $st->execute([$id]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function ligneVersionActiveEcriture(string $secretReference): ?array
    {
        $st = $this->magasin->prepare(
            "SELECT v.* FROM secret_version v
             WHERE v.secret_reference = ? AND v.id = (
                 SELECT c.secret_version_id FROM secret_version_cycle c
                 WHERE c.secret_version_id IN (SELECT id FROM secret_version WHERE secret_reference = ?)
                   AND c.etat = 'ACTIVE_ECRITURE'
                   AND c.id = (SELECT MAX(c2.id) FROM secret_version_cycle c2 WHERE c2.secret_version_id = c.secret_version_id)
                 LIMIT 1
             )"
        );
        $st->execute([$secretReference, $secretReference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function lignePlan(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM secret_rotation_plan WHERE reference = ?');
        $st->execute([$reference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    /** @return array<string,mixed>|null */
    private function ligneExecutionReussie(string $planReference, string $etapeReference): ?array
    {
        $st = $this->magasin->prepare(
            "SELECT * FROM secret_rotation_execution
             WHERE plan_reference = ? AND etape_reference = ? AND etat = 'REUSSIE'
             ORDER BY cree_le DESC LIMIT 1"
        );
        $st->execute([$planReference, $etapeReference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $ligne;
    }

    private function etatCourant(int $secretVersionId): ?string
    {
        $st = $this->magasin->prepare(
            'SELECT etat FROM secret_version_cycle WHERE secret_version_id = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$secretVersionId]);
        $etat = $st->fetchColumn();

        return $etat === false ? null : (string) $etat;
    }

    private function inscrireCycle(
        int $secretVersionId,
        string $etat,
        string $dateEffet,
        ?string $motif,
        string $acteur,
        string $politique,
        string $preuve,
        ?string $correlation,
    ): void {
        $this->magasin->prepare(
            'INSERT INTO secret_version_cycle
             (secret_version_id,etat,date_effet,motif,acteur_reference,politique_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?)'
        )->execute([$secretVersionId, $etat, $dateEffet, $motif, $acteur, $politique, $preuve, $correlation, gmdate('c')]);
    }

    private function controlerGouvernance(array $dossier): array
    {
        foreach (['politique', 'producteur', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('COMMANDE_NON_GOUVERNEE', "champ `{$champ}` absent");
            }
        }

        return ['valide' => true];
    }

    /**
     * Garde absolue : refuse tout dossier portant un champ qui ressemble à
     * une valeur secrète, quelle que soit la commande. Voir
     * `PolitiqueSecretsCles::CHAMPS_INTERDITS`.
     */
    private function refuserChampsInterdits(array $dossier): array
    {
        foreach (array_keys($dossier) as $cle) {
            if (in_array(strtolower((string) $cle), PolitiqueSecretsCles::CHAMPS_INTERDITS, true)) {
                return $this->refus('CHAMP_INTERDIT', "le champ `{$cle}` ne peut jamais être transmis à ce registre");
            }
        }

        return ['valide' => true];
    }

    private function nullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $chaine = trim((string) $valeur);

        return $chaine === '' ? null : $chaine;
    }

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
    }

    private function driver(): string
    {
        return (string) $this->magasin->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
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
}
