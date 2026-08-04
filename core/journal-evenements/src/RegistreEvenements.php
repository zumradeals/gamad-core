<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreSources\RegistreSources;

/**
 * Journal central des événements communs (CAP-CORE-014).
 *
 * Reçoit les intentions transmises par les relais d'outbox authentifiés
 * (`core/evenements-sortants`), jamais directement par un client externe.
 * Ce module ne décide d'aucune autorisation lui-même : la preuve
 * d'autorisation (`preuve`) est un intrant obligatoire, produite en amont par
 * la couche applicative (comme pour tous les autres registres persistants du
 * Core). Il ne possède ni les données métier complètes, ni les secrets, ni
 * les contrats eux-mêmes (`CAP-CORE-009`), ni le vocabulaire (`CAP-CORE-010`).
 *
 * Les dépendances `contrats`, `sources` et `realms` sont obligatoires : sans
 * elles, aucune des vérifications contractuelles qui conditionnent `GO`
 * (contrat actif, producteur déclaré, source active, realm actif) ne peut
 * être établie, et la fiche interdit explicitement toute permission
 * implicite en cas de dépendance absente (partie 3 §19).
 *
 * Limite assumée de ce chantier : la charge est validée contre les règles de
 * sécurité et de taille génériques (`ValidateurEvenement`), pas encore contre
 * le schéma JSON déclaré par l'opération du contrat (`contrat_schema`) — une
 * conformité structurelle complète reste un chantier ultérieur non bloquant
 * pour le noyau.
 */
final class RegistreEvenements
{
    public const CAPACITE = 'CAP-CORE-014';

    public function __construct(
        private \PDO $magasin,
        private RegistreContrats $contrats,
        private RegistreSources $sources,
        private RegistreRealms $realms,
    ) {
        SchemaEvenements::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Commande centrale

    /**
     * @param array<string,mixed> $intention Champs de l'enveloppe + charge + empreinte annoncée.
     * @param array<string,mixed> $dossier   politique, producteur, source, preuve.
     * @return array<string,mixed>
     */
    public function accepterEvenement(array $intention, array $dossier): array
    {
        foreach (['politique', 'producteur', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                throw new ExceptionEvenement("Champ de gouvernance obligatoire absent : {$champ}.");
            }
        }

        $ecarts = ValidateurEvenement::validerEnveloppe($intention);
        if ($ecarts !== []) {
            return $this->refus('ENVELOPPE_INVALIDE', implode(' ; ', $ecarts));
        }

        $producteurCapacite = $this->nullable($intention['producteur_capacite_reference'] ?? null);
        $producteurProduit = $this->nullable($intention['producteur_produit_reference'] ?? null);
        $producteurReference = $producteurCapacite ?? $producteurProduit;
        $idempotence = (string) $intention['idempotence_reference'];

        // Idempotence : le rejeu d'une même outbox retourne le reçu existant
        // sans créer une nouvelle séquence (partie 2 §6, partie 3 §4).
        $existant = $this->recuExistant((string) $producteurReference, $idempotence);
        if ($existant !== null) {
            return $existant + ['idempotent' => true];
        }

        $contratReference = (string) $intention['contrat_reference'];
        $contratVersionAttendue = (string) $intention['contrat_version'];
        $contrat = $this->contrats->resoudreContrat($contratReference);
        if ($contrat === null) {
            return $this->refus('CONTRAT_INCONNU', "contrat `{$contratReference}` inconnu");
        }
        if ($contrat['type_contrat'] !== 'EVENEMENT') {
            return $this->refus('CONTRAT_TYPE_INVALIDE', "contrat `{$contratReference}` n'est pas de type EVENEMENT");
        }
        $versionActive = $this->contrats->resoudreVersionActive($contratReference);
        if ($versionActive === null || $versionActive['etat'] !== 'ACTIVE') {
            return $this->refus('VERSION_INCOMPATIBLE', "aucune version active pour `{$contratReference}`");
        }
        if ($versionActive['version'] !== $contratVersionAttendue) {
            return $this->refus(
                'VERSION_INCOMPATIBLE',
                "version active `{$versionActive['version']}` différente de `{$contratVersionAttendue}`",
            );
        }

        $partieType = $producteurCapacite !== null ? 'CAPACITE' : 'PRODUIT';
        $producteurDeclare = array_filter(
            $versionActive['parties'] ?? [],
            static fn (array $p): bool => $p['role'] === 'PRODUCTEUR'
                && $p['partie_type'] === $partieType
                && $p['partie_reference'] === $producteurReference,
        );
        if ($producteurDeclare === []) {
            return $this->refus(
                'PRODUCTEUR_NON_DECLARE',
                "`{$producteurReference}` n'est pas déclaré PRODUCTEUR du contrat `{$contratReference}`",
            );
        }

        $sourceReference = (string) $intention['source_reference'];
        $etatSource = $this->sources->resoudreEtat($sourceReference);
        if ($etatSource === null || $etatSource['etat'] !== 'ACTIVE') {
            return $this->refus('SOURCE_INACTIVE', "source `{$sourceReference}` non active");
        }

        $finaliteReference = (string) $intention['finalite_reference'];
        $finalites = array_map(
            static fn (array $f): string => $f['finalite_reference'],
            $this->sources->resoudreFinalites($sourceReference, true),
        );
        if (!in_array($finaliteReference, $finalites, true)) {
            return $this->refus(
                'FINALITE_ABSENTE',
                "finalité `{$finaliteReference}` non déclarée active pour la source `{$sourceReference}`",
            );
        }

        $realmReference = (string) $intention['realm_reference'];
        $etatRealm = $this->realms->resoudreEtat($realmReference);
        if ($etatRealm === null || $etatRealm['etat'] !== 'ACTIF') {
            return $this->refus('REALM_INACTIF', "realm `{$realmReference}` non actif");
        }

        $charge = is_array($intention['charge'] ?? null) ? $intention['charge'] : [];
        $ecartsCharge = ValidateurEvenement::validerCharge($charge);
        if ($ecartsCharge !== []) {
            return $this->refus('CHARGE_INVALIDE', implode(' ; ', $ecartsCharge));
        }
        $chargeEmpreinte = EnveloppeEvenement::empreinteCharge($charge);
        $empreinteAnnoncee = $this->nullable($intention['charge_empreinte'] ?? null);
        if ($empreinteAnnoncee !== null && !hash_equals($chargeEmpreinte, $empreinteAnnoncee)) {
            return $this->refus('CHARGE_INVALIDE', 'empreinte de charge annoncée divergente');
        }

        $survenuLe = (string) $intention['survenu_le'];
        $enregistreLe = gmdate('c');
        if ($survenuLe > $enregistreLe) {
            return $this->refus('ENVELOPPE_INVALIDE', 'survenu_le postérieur à enregistre_le');
        }

        return $this->inserer(
            $intention,
            $charge,
            $chargeEmpreinte,
            (string) $producteurReference,
            $idempotence,
            $enregistreLe,
        );
    }

    /** @return array<string,mixed> */
    private function inserer(
        array $intention,
        array $charge,
        string $chargeEmpreinte,
        string $producteurReference,
        string $idempotence,
        string $enregistreLe,
    ): array {
        $propreTransaction = !$this->magasin->inTransaction();
        $transactionSqlite = $propreTransaction && $this->driver() === 'sqlite';
        if ($propreTransaction) {
            $transactionSqlite ? $this->magasin->exec('BEGIN IMMEDIATE') : $this->magasin->beginTransaction();
        }

        try {
            // Un second appel concurrent pour la même idempotence a pu passer
            // le contrôle initial avant que ce verrou ne soit acquis.
            $existant = $this->recuExistant($producteurReference, $idempotence);
            if ($existant !== null) {
                if ($propreTransaction) {
                    $transactionSqlite ? $this->magasin->exec('COMMIT') : $this->magasin->commit();
                }

                return $existant + ['idempotent' => true];
            }

            if ($this->driver() === 'pgsql') {
                $this->magasin->exec('SELECT pg_advisory_xact_lock(714114)');
            }

            $precedente = $this->magasin
                ->query('SELECT empreinte_evenement FROM evenement_commun ORDER BY sequence_id DESC LIMIT 1')
                ->fetchColumn();
            $precedente = $precedente === false ? null : (string) $precedente;

            $reference = 'EVT-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $schemaEmpreinte = hash('sha256', EnveloppeEvenement::jsonCanonique([
                'contrat_reference' => $intention['contrat_reference'],
                'contrat_version' => $intention['contrat_version'],
            ]));
            $producteurCapacite = $this->nullable($intention['producteur_capacite_reference'] ?? null);
            $producteurProduit = $this->nullable($intention['producteur_produit_reference'] ?? null);
            $reconstruction = (bool) ($intention['reconstruction'] ?? false);

            $contenu = [
                'reference' => $reference,
                'type_evenement' => (string) $intention['type_evenement'],
                'contrat_reference' => (string) $intention['contrat_reference'],
                'contrat_version' => (string) $intention['contrat_version'],
                'producteur_reference' => $producteurReference,
                'source_reference' => (string) $intention['source_reference'],
                'realm_reference' => (string) $intention['realm_reference'],
                'finalite_reference' => (string) $intention['finalite_reference'],
                'sujet_type' => $this->nullable($intention['sujet_type'] ?? null),
                'sujet_reference' => $this->nullable($intention['sujet_reference'] ?? null),
                'correlation_id' => (string) $intention['correlation_id'],
                'causation_reference' => $this->nullable($intention['causation_reference'] ?? null),
                'idempotence_reference' => $idempotence,
                'survenu_le' => (string) $intention['survenu_le'],
                'classification' => (string) $intention['classification'],
                'schema_empreinte' => $schemaEmpreinte,
                'charge_empreinte' => $chargeEmpreinte,
                'reconstruction' => $reconstruction,
            ];
            $empreinte = EnveloppeEvenement::empreinteChainee($precedente, $contenu);

            $this->magasin->prepare(
                'INSERT INTO evenement_commun
                 (reference,type_evenement,contrat_reference,contrat_version,
                  producteur_capacite_reference,producteur_produit_reference,producteur_reference,
                  source_reference,realm_reference,finalite_reference,sujet_type,sujet_reference,
                  correlation_id,causation_reference,idempotence_reference,survenu_le,enregistre_le,
                  classification,schema_empreinte,charge_empreinte,empreinte_precedente,
                  empreinte_evenement,reconstruction)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, $contenu['type_evenement'], $contenu['contrat_reference'], $contenu['contrat_version'],
                $producteurCapacite, $producteurProduit, $producteurReference,
                $contenu['source_reference'], $contenu['realm_reference'], $contenu['finalite_reference'],
                $contenu['sujet_type'], $contenu['sujet_reference'],
                $contenu['correlation_id'], $contenu['causation_reference'], $idempotence,
                $contenu['survenu_le'], $enregistreLe,
                $contenu['classification'], $schemaEmpreinte, $chargeEmpreinte, $precedente,
                $empreinte, $reconstruction ? 1 : 0,
            ]);

            $sequenceId = (int) $this->magasin->lastInsertId(
                $this->driver() === 'pgsql' ? 'evenement_commun_sequence_id_seq' : null,
            );
            if ($sequenceId === 0) {
                $sequenceId = (int) $this->magasin
                    ->query('SELECT sequence_id FROM evenement_commun WHERE reference = ' . $this->magasin->quote($reference))
                    ->fetchColumn();
            }

            $this->magasin->prepare(
                'INSERT INTO evenement_charge(evenement_reference,media_type,schema_format,charge_json,empreinte,taille_octets,cree_le)
                 VALUES(?,?,?,?,?,?,?)'
            )->execute([
                $reference, 'application/json', 'JSON_CANONIQUE',
                EnveloppeEvenement::jsonCanonique($charge), $chargeEmpreinte,
                strlen(EnveloppeEvenement::jsonCanonique($charge)), $enregistreLe,
            ]);

            $this->magasin->prepare(
                'INSERT INTO recu_publication(producteur_reference,idempotence_reference,evenement_reference,sequence_id,accepte_le)
                 VALUES(?,?,?,?,?)'
            )->execute([$producteurReference, $idempotence, $reference, $sequenceId, $enregistreLe]);

            $routeur = new RouteurEvenements($this->magasin);
            $routeur->distribuer($reference, $sequenceId, $contenu, $enregistreLe);

            if ($propreTransaction) {
                $transactionSqlite ? $this->magasin->exec('COMMIT') : $this->magasin->commit();
            }

            return [
                'reference' => $reference,
                'sequence' => $sequenceId,
                'empreinte' => $empreinte,
                'algorithme' => 'sha256',
                'signee' => false,
                'enregistre_le' => $enregistreLe,
                'idempotent' => false,
            ];
        } catch (\Throwable $e) {
            if ($propreTransaction) {
                if ($transactionSqlite) {
                    try {
                        $this->magasin->exec('ROLLBACK');
                    } catch (\Throwable) {
                    }
                } elseif ($this->magasin->inTransaction()) {
                    $this->magasin->rollBack();
                }
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreEvenement(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM evenement_commun WHERE reference = ?');
        $st->execute([$reference]);
        $ligne = $st->fetch();

        return $ligne === false ? null : $this->projeter($ligne);
    }

    /** @param array<string,mixed> $filtres @return list<array<string,mixed>> */
    public function listerEvenements(array $filtres = [], int $limite = 100, int $decalage = 0): array
    {
        $limite = max(1, min($limite, PolitiqueEvenements::TAILLE_LOT_MAX));
        $sql = 'SELECT * FROM evenement_commun WHERE 1=1';
        $args = [];
        foreach ([
            'type_evenement' => 'type_evenement',
            'contrat_reference' => 'contrat_reference',
            'producteur_reference' => 'producteur_reference',
            'realm_reference' => 'realm_reference',
            'sujet_reference' => 'sujet_reference',
            'correlation_id' => 'correlation_id',
        ] as $filtre => $colonne) {
            if (isset($filtres[$filtre])) {
                $sql .= " AND {$colonne} = ?";
                $args[] = (string) $filtres[$filtre];
            }
        }
        if (isset($filtres['sequence_debut'])) {
            $sql .= ' AND sequence_id >= ?';
            $args[] = (int) $filtres['sequence_debut'];
        }
        if (isset($filtres['sequence_fin'])) {
            $sql .= ' AND sequence_id <= ?';
            $args[] = (int) $filtres['sequence_fin'];
        }
        $sql .= ' ORDER BY sequence_id LIMIT ? OFFSET ?';
        $args[] = $limite;
        $args[] = max(0, $decalage);
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        return array_map(fn (array $l): array => $this->projeter($l), $st->fetchAll());
    }

    /** Nombre d'événements publiés depuis une date ISO 8601 donnée (tableau de bord, fiche partie 4 §9). */
    public function compterDepuis(string $depuis): int
    {
        $st = $this->magasin->prepare('SELECT COUNT(*) FROM evenement_commun WHERE survenu_le >= ?');
        $st->execute([$depuis]);

        return (int) $st->fetchColumn();
    }

    /** @return array<string,mixed> */
    public function resoudreCharge(string $reference): array
    {
        $evenement = $this->resoudreEvenement($reference);
        if ($evenement === null) {
            return ['refus' => 'EVENEMENT_INCONNU'];
        }
        $st = $this->magasin->prepare('SELECT * FROM evenement_charge WHERE evenement_reference = ?');
        $st->execute([$reference]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return ['reference' => $reference, 'etat' => 'CHARGE_EXPIREE', 'charge' => null];
        }
        if ($ligne['expire_le'] !== null && (string) $ligne['expire_le'] < gmdate('c')) {
            return ['reference' => $reference, 'etat' => 'CHARGE_EXPIREE', 'charge' => null];
        }

        return [
            'reference' => $reference,
            'etat' => 'DISPONIBLE',
            'charge' => json_decode((string) $ligne['charge_json'], true, flags: JSON_THROW_ON_ERROR),
            'empreinte' => $ligne['empreinte'],
        ];
    }

    /** @return array<string,mixed>|null */
    public function resoudrePublication(string $producteurReference, string $idempotence): ?array
    {
        return $this->recuExistant($producteurReference, $idempotence);
    }

    // ------------------------------------------------------------------
    // Purge gouvernée de la charge (jamais de l'enveloppe)

    /**
     * Références d'événements dont la charge a contractuellement expiré et
     * qui ne dépendent plus d'aucune livraison active — candidats sûrs pour
     * `purgerCharge()`. `$avant` ne peut que resserrer la sélection, jamais
     * l'élargir au-delà de l'expiration réelle : `purgerCharge()` referait de
     * toute façon le même contrôle, mais la liste de simulation doit rester
     * honnête sur ce qui sera réellement purgé.
     *
     * @return list<array{reference:string,charge_expire_le:string}>
     */
    public function listerChargesPurgeables(?string $avant, int $limite): array
    {
        $borne = $avant !== null && $avant < gmdate('c') ? $avant : gmdate('c');
        $st = $this->magasin->prepare(
            'SELECT e.reference AS reference, e.charge_expire_le AS charge_expire_le
             FROM evenement_commun e
             INNER JOIN evenement_charge ec ON ec.evenement_reference = e.reference
             WHERE e.charge_expire_le IS NOT NULL AND e.charge_expire_le <= ?
               AND NOT EXISTS (
                   SELECT 1 FROM livraison_evenement l
                   WHERE l.evenement_reference = e.reference
                     AND l.etat IN (\'DISPONIBLE\', \'SOUS_BAIL\', \'A_REESSAYER\')
               )
             ORDER BY e.sequence_id
             LIMIT ?'
        );
        $st->execute([$borne, max(1, $limite)]);

        return array_map(
            static fn (array $l): array => ['reference' => (string) $l['reference'], 'charge_expire_le' => (string) $l['charge_expire_le']],
            $st->fetchAll(),
        );
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function purgerCharge(string $reference, array $dossier): array
    {
        foreach (['politique', 'producteur', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                throw new ExceptionEvenement("Champ de gouvernance obligatoire absent : {$champ}.");
            }
        }
        $evenement = $this->resoudreEvenement($reference);
        if ($evenement === null) {
            return $this->refus('EVENEMENT_INCONNU', "événement `{$reference}` inconnu");
        }
        if ($evenement['charge_expire_le'] === null || (string) $evenement['charge_expire_le'] > gmdate('c')) {
            return $this->refus('PURGE_PREMATUREE', 'la charge n’a pas encore atteint sa date d’expiration contractuelle');
        }
        $enUsage = $this->magasin->prepare(
            "SELECT COUNT(*) FROM livraison_evenement WHERE evenement_reference = ? AND etat IN ('DISPONIBLE','SOUS_BAIL','A_REESSAYER')"
        );
        $enUsage->execute([$reference]);
        if ((int) $enUsage->fetchColumn() > 0) {
            return $this->refus('PURGE_PREMATUREE', 'des livraisons actives dépendent encore de cette charge');
        }

        $this->magasin->prepare('DELETE FROM evenement_charge WHERE evenement_reference = ?')->execute([$reference]);

        return ['reference' => $reference, 'purgee' => true, 'purgee_le' => gmdate('c')];
    }

    // ------------------------------------------------------------------
    // Diagnostic

    /** @return array{valide:bool,evenements:int,ecarts:list<string>,tete:?string} */
    public function verifierChaine(): array
    {
        $precedente = null;
        $ecarts = [];
        $nombre = 0;
        $lignes = $this->magasin->query(
            'SELECT reference,type_evenement,contrat_reference,contrat_version,producteur_reference,
                    source_reference,realm_reference,finalite_reference,sujet_type,sujet_reference,
                    correlation_id,causation_reference,idempotence_reference,survenu_le,classification,
                    schema_empreinte,charge_empreinte,empreinte_precedente,empreinte_evenement,reconstruction
             FROM evenement_commun ORDER BY sequence_id'
        )->fetchAll();
        foreach ($lignes as $l) {
            $nombre++;
            $contenu = [
                'reference' => $l['reference'],
                'type_evenement' => $l['type_evenement'],
                'contrat_reference' => $l['contrat_reference'],
                'contrat_version' => $l['contrat_version'],
                'producteur_reference' => $l['producteur_reference'],
                'source_reference' => $l['source_reference'],
                'realm_reference' => $l['realm_reference'],
                'finalite_reference' => $l['finalite_reference'],
                'sujet_type' => $l['sujet_type'],
                'sujet_reference' => $l['sujet_reference'],
                'correlation_id' => $l['correlation_id'],
                'causation_reference' => $l['causation_reference'],
                'idempotence_reference' => $l['idempotence_reference'],
                'survenu_le' => $l['survenu_le'],
                'classification' => $l['classification'],
                'schema_empreinte' => $l['schema_empreinte'],
                'charge_empreinte' => $l['charge_empreinte'],
                'reconstruction' => (bool) $l['reconstruction'],
            ];
            $attendue = EnveloppeEvenement::empreinteChainee($precedente, $contenu);
            if ($l['empreinte_precedente'] !== $precedente) {
                $ecarts[] = "chaînage rompu à {$l['reference']}";
            }
            if (!hash_equals($attendue, (string) $l['empreinte_evenement'])) {
                $ecarts[] = "empreinte invalide à {$l['reference']}";
            }
            $precedente = (string) $l['empreinte_evenement'];
        }

        return ['valide' => $ecarts === [], 'evenements' => $nombre, 'ecarts' => $ecarts, 'tete' => $precedente];
    }

    /** @return array<string,mixed> */
    public function diagnostiquerJournal(): array
    {
        $chaine = $this->verifierChaine();
        $sansCharge = (int) $this->magasin->query(
            "SELECT COUNT(*) FROM evenement_commun e
             LEFT JOIN evenement_charge c ON c.evenement_reference = e.reference
             WHERE c.evenement_reference IS NULL AND e.charge_expire_le IS NULL"
        )->fetchColumn();
        $baileExpires = (int) $this->magasin->query(
            "SELECT COUNT(*) FROM livraison_evenement WHERE etat = 'SOUS_BAIL' AND bail_expire_le < '" . gmdate('c') . "'"
        )->fetchColumn();
        $lettresMortes = (int) $this->magasin->query('SELECT COUNT(*) FROM lettre_morte_evenement')->fetchColumn();
        $abonnementsSansType = (int) $this->magasin->query(
            "SELECT COUNT(*) FROM abonnement_evenement a
             WHERE a.reference IN (
                 SELECT abonnement_reference FROM abonnement_cycle ac1
                 WHERE etat = 'ACTIF' AND id = (
                     SELECT id FROM abonnement_cycle ac2
                     WHERE ac2.abonnement_reference = ac1.abonnement_reference
                     ORDER BY id DESC LIMIT 1
                 )
             )
             AND a.reference NOT IN (SELECT DISTINCT abonnement_reference FROM abonnement_type_evenement)"
        )->fetchColumn();

        return [
            'chaine' => $chaine,
            'evenements_sans_charge_inattendus' => $sansCharge,
            'baux_expires_non_liberes' => $baileExpires,
            'lettres_mortes' => $lettresMortes,
            'abonnements_actifs_sans_type' => $abonnementsSansType,
            'coherent' => $chaine['valide'] && $sansCharge === 0 && $abonnementsSansType === 0,
        ];
    }

    // ------------------------------------------------------------------
    // Internes

    /** @return array<string,mixed>|null */
    private function recuExistant(string $producteurReference, string $idempotence): ?array
    {
        $st = $this->magasin->prepare(
            'SELECT r.*, e.empreinte_evenement, e.enregistre_le FROM recu_publication r
             JOIN evenement_commun e ON e.reference = r.evenement_reference
             WHERE r.producteur_reference = ? AND r.idempotence_reference = ?'
        );
        $st->execute([$producteurReference, $idempotence]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return null;
        }

        return [
            'reference' => $ligne['evenement_reference'],
            'sequence' => (int) $ligne['sequence_id'],
            'empreinte' => $ligne['empreinte_evenement'],
            'algorithme' => 'sha256',
            'signee' => false,
            'enregistre_le' => $ligne['enregistre_le'],
        ];
    }

    /** @return array<string,mixed> */
    private function projeter(array $l): array
    {
        return [
            'reference' => $l['reference'],
            'sequence' => (int) $l['sequence_id'],
            'type' => $l['type_evenement'],
            'contrat_reference' => $l['contrat_reference'],
            'contrat_version' => $l['contrat_version'],
            'producteur_capacite_reference' => $l['producteur_capacite_reference'],
            'producteur_produit_reference' => $l['producteur_produit_reference'],
            'source_reference' => $l['source_reference'],
            'realm_reference' => $l['realm_reference'],
            'finalite_reference' => $l['finalite_reference'],
            'sujet_type' => $l['sujet_type'],
            'sujet_reference' => $l['sujet_reference'],
            'correlation_id' => $l['correlation_id'],
            'causation_reference' => $l['causation_reference'],
            'survenu_le' => $l['survenu_le'],
            'enregistre_le' => $l['enregistre_le'],
            'classification' => $l['classification'],
            'empreinte' => $l['empreinte_evenement'],
            'charge_expire_le' => $l['charge_expire_le'],
            'reconstruction' => (bool) $l['reconstruction'],
            'signee' => false,
        ];
    }

    private function nullable(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' ? null : $t;
    }

    /** @return array{refus:string,detail:string} */
    private function refus(string $code, string $detail): array
    {
        return ['refus' => $code, 'detail' => $detail];
    }

    private function driver(): string
    {
        return (string) $this->magasin->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
