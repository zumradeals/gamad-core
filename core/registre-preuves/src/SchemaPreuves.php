<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Migration additive du registre des preuves d'intégrité (CAP-CORE-015).
 *
 * Ce magasin ne conserve jamais de matériel secret : aucune clé privée,
 * seulement des empreintes, des signatures déjà produites et des clés
 * publiques lorsqu'elles sont nécessaires à une vérification autonome.
 *
 * `preuve`, `preuve_representation`, `preuve_empreinte`, `preuve_signature`,
 * `manifeste`, `manifeste_membre`, `attestation`, `checkpoint_preuve` et
 * `paquet_preuve` sont verrouillées en mutation après insertion (une
 * correction crée une nouvelle preuve avec un lien `REMPLACE`, jamais une
 * réécriture). `preuve_cycle`, `verification_preuve` et `preuve_lien` sont
 * strictement en ajout seul.
 */
final class SchemaPreuves
{
    public const VERSION = 1;

    public const TABLES = [
        'preuve',
        'preuve_representation',
        'preuve_empreinte',
        'preuve_signature',
        'preuve_cycle',
        'manifeste',
        'manifeste_membre',
        'attestation',
        'checkpoint_preuve',
        'verification_preuve',
        'preuve_lien',
        'paquet_preuve',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migration_registre_preuves (
                version INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )'
        );
        $deja = $pdo->query(
            'SELECT version FROM migration_registre_preuves WHERE version = 1'
        )->fetchColumn();
        if ($deja !== false) {
            return;
        }

        $propreTransaction = !$pdo->inTransaction();
        if ($propreTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $id = self::driver($pdo) === 'pgsql'
                ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
                : 'INTEGER PRIMARY KEY AUTOINCREMENT';

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS preuve (
                    reference                         TEXT PRIMARY KEY,
                    type_preuve                        TEXT NOT NULL,
                    sujet_type                          TEXT NOT NULL,
                    sujet_reference                     TEXT NOT NULL,
                    producteur_capacite_reference         TEXT,
                    producteur_produit_reference           TEXT,
                    producteur_identite_reference          TEXT,
                    organisation_reference                 TEXT,
                    realm_reference                        TEXT NOT NULL,
                    finalite_reference                     TEXT NOT NULL,
                    source_reference                       TEXT NOT NULL,
                    contrat_reference                      TEXT,
                    contrat_version                        TEXT,
                    classification                         TEXT NOT NULL,
                    description                            TEXT,
                    cree_le                                TEXT NOT NULL,
                    cree_par_reference                     TEXT NOT NULL,
                    correlation_id                         TEXT NOT NULL,
                    idempotency_key                        TEXT,
                    UNIQUE(cree_par_reference, type_preuve, idempotency_key)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_type ON preuve(type_preuve)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_sujet ON preuve(sujet_type, sujet_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_realm ON preuve(realm_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS preuve_representation (
                    id                    {$id},
                    preuve_reference       TEXT NOT NULL,
                    format_representation  TEXT NOT NULL,
                    version_canonicalisation TEXT NOT NULL,
                    media_type              TEXT NOT NULL,
                    taille_octets           INTEGER,
                    artefact_reference      TEXT,
                    chemin_logique          TEXT,
                    contenu_inline          TEXT,
                    encodage                TEXT,
                    metadonnees_json        TEXT NOT NULL,
                    cree_le                 TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_representation_preuve ON preuve_representation(preuve_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS preuve_empreinte (
                    id                     {$id},
                    preuve_reference        TEXT NOT NULL,
                    algorithme               TEXT NOT NULL,
                    empreinte_hex            TEXT NOT NULL,
                    taille_bits              INTEGER NOT NULL,
                    calculee_le              TEXT NOT NULL,
                    calculateur_version      TEXT NOT NULL,
                    representation_empreinte TEXT NOT NULL,
                    est_principale           INTEGER NOT NULL DEFAULT 0,
                    cree_le                  TEXT NOT NULL,
                    UNIQUE(preuve_reference, algorithme)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_empreinte_preuve ON preuve_empreinte(preuve_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS preuve_signature (
                    reference                  TEXT PRIMARY KEY,
                    preuve_reference            TEXT NOT NULL,
                    algorithme_signature        TEXT NOT NULL,
                    cle_reference                TEXT NOT NULL,
                    cle_version_reference        TEXT NOT NULL,
                    signature_base64url          TEXT NOT NULL,
                    contexte_signature_version   TEXT NOT NULL,
                    empreinte_contexte           TEXT NOT NULL,
                    signee_le                    TEXT NOT NULL,
                    expire_le                    TEXT,
                    fournisseur_reference        TEXT NOT NULL,
                    resultat_operation_reference TEXT NOT NULL,
                    cree_le                      TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_signature_preuve ON preuve_signature(preuve_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS preuve_cycle (
                    id                       {$id},
                    preuve_reference          TEXT NOT NULL,
                    etat                      TEXT NOT NULL CHECK (etat IN
                        ('PREPAREE','EMISE','ACTIVE','EXPIREE','SUSPENDUE','REVOQUEE','COMPROMISE','ARCHIVEE')),
                    date_effet                TEXT NOT NULL,
                    motif_code                TEXT,
                    motif_detail              TEXT,
                    acteur_reference          TEXT NOT NULL,
                    politique_reference       TEXT NOT NULL,
                    preuve_autorisation_reference TEXT NOT NULL,
                    correlation_id            TEXT,
                    cree_le                   TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_cycle_preuve ON preuve_cycle(preuve_reference, id)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS manifeste (
                    reference           TEXT PRIMARY KEY,
                    preuve_reference     TEXT NOT NULL,
                    nom                  TEXT NOT NULL,
                    type_manifeste       TEXT NOT NULL,
                    version_format       TEXT NOT NULL,
                    ordre_significatif   INTEGER NOT NULL DEFAULT 0,
                    membres_attendus     INTEGER NOT NULL,
                    taille_totale        INTEGER,
                    racine_empreinte     TEXT NOT NULL,
                    algorithme_racine    TEXT NOT NULL,
                    cree_le              TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS manifeste_preuve ON manifeste(preuve_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS manifeste_membre (
                    id                    {$id},
                    manifeste_reference    TEXT NOT NULL,
                    ordre                  INTEGER NOT NULL,
                    chemin_logique         TEXT NOT NULL,
                    sujet_type             TEXT,
                    sujet_reference        TEXT,
                    media_type             TEXT NOT NULL,
                    taille_octets          INTEGER NOT NULL,
                    algorithme_empreinte   TEXT NOT NULL,
                    empreinte              TEXT NOT NULL,
                    obligatoire            INTEGER NOT NULL DEFAULT 1,
                    metadonnees_json       TEXT NOT NULL,
                    cree_le                TEXT NOT NULL,
                    UNIQUE(manifeste_reference, chemin_logique)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS manifeste_membre_manifeste ON manifeste_membre(manifeste_reference, ordre)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS attestation (
                    reference        TEXT PRIMARY KEY,
                    preuve_reference  TEXT NOT NULL,
                    type_attestation  TEXT NOT NULL,
                    declaration_json  TEXT NOT NULL,
                    version_schema    TEXT NOT NULL,
                    resultat          TEXT NOT NULL,
                    periode_debut     TEXT,
                    periode_fin       TEXT,
                    emettrice_reference TEXT NOT NULL,
                    cree_le           TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS attestation_preuve ON attestation(preuve_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS checkpoint_preuve (
                    reference           TEXT PRIMARY KEY,
                    preuve_reference     TEXT NOT NULL,
                    type_checkpoint      TEXT NOT NULL,
                    structure_reference  TEXT NOT NULL,
                    sequence             INTEGER,
                    tete_empreinte       TEXT NOT NULL,
                    nombre_elements      INTEGER,
                    instant_observe      TEXT NOT NULL,
                    metadonnees_json     TEXT NOT NULL,
                    cree_le              TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS checkpoint_preuve_preuve ON checkpoint_preuve(preuve_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS checkpoint_preuve_structure ON checkpoint_preuve(type_checkpoint, structure_reference, instant_observe)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS verification_preuve (
                    reference               TEXT PRIMARY KEY,
                    preuve_reference         TEXT NOT NULL,
                    verificateur_reference   TEXT NOT NULL,
                    instant_verification     TEXT NOT NULL,
                    resultat                 TEXT NOT NULL,
                    empreinte_presentee      TEXT,
                    signature_verifiee       INTEGER,
                    cle_version_reference    TEXT,
                    etat_cle_a_signature     TEXT,
                    etat_cle_aujourdhui      TEXT,
                    divergences_json         TEXT NOT NULL,
                    moteur_version           TEXT NOT NULL,
                    artefact_reference       TEXT,
                    correlation_id           TEXT,
                    cree_le                  TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS verification_preuve_preuve ON verification_preuve(preuve_reference, cree_le)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS preuve_lien (
                    id                       {$id},
                    preuve_source_reference   TEXT NOT NULL,
                    preuve_cible_reference    TEXT NOT NULL,
                    type_lien                 TEXT NOT NULL CHECK (type_lien IN
                        ('DERIVE_DE','REMPLACE','CONFIRME','CONTREDIT','COMPOSE','CHECKPOINT_DE','RESTAURE_DEPUIS')),
                    cree_le                    TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_lien_source ON preuve_lien(preuve_source_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS preuve_lien_cible ON preuve_lien(preuve_cible_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS paquet_preuve (
                    reference        TEXT PRIMARY KEY,
                    preuve_reference  TEXT NOT NULL,
                    format_paquet     TEXT NOT NULL,
                    version_format    TEXT NOT NULL,
                    empreinte_paquet  TEXT NOT NULL,
                    taille_octets     INTEGER NOT NULL,
                    classification    TEXT NOT NULL,
                    expire_le         TEXT,
                    cree_le           TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS paquet_preuve_preuve ON paquet_preuve(preuve_reference)');

            self::verrouillerMutations($pdo, 'preuve_cycle');
            self::verrouillerMutations($pdo, 'verification_preuve');
            self::verrouillerMutations($pdo, 'preuve_lien');
            self::verrouillerUpdateSeulement($pdo, 'preuve');
            self::verrouillerUpdateSeulement($pdo, 'preuve_representation');
            self::verrouillerUpdateSeulement($pdo, 'preuve_empreinte');
            self::verrouillerUpdateSeulement($pdo, 'preuve_signature');
            self::verrouillerUpdateSeulement($pdo, 'manifeste');
            self::verrouillerUpdateSeulement($pdo, 'manifeste_membre');
            self::verrouillerUpdateSeulement($pdo, 'attestation');
            self::verrouillerUpdateSeulement($pdo, 'checkpoint_preuve');
            self::verrouillerUpdateSeulement($pdo, 'paquet_preuve');

            $st = $pdo->prepare(
                'INSERT INTO migration_registre_preuves(version, appliquee_le) VALUES(?, ?)'
            );
            $st->execute([self::VERSION, gmdate('c')]);

            if ($propreTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($propreTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function presente(\PDO $pdo): bool
    {
        foreach (self::TABLES as $table) {
            try {
                $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            } catch (\PDOException) {
                return false;
            }
        }

        return true;
    }

    private static function verrouillerMutations(\PDO $pdo, string $table): void
    {
        if (self::driver($pdo) === 'pgsql') {
            $fonction = "gamad_refuser_mutation_{$table}";
            $pdo->exec(<<<SQL
                CREATE OR REPLACE FUNCTION {$fonction}()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION '{$table} est en ajout seul';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            $pdo->exec("DROP TRIGGER IF EXISTS {$table}_immuable ON {$table}");
            $pdo->exec(<<<SQL
                CREATE TRIGGER {$table}_immuable
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION {$fonction}()
            SQL);

            return;
        }

        $pdo->exec(<<<SQL
            CREATE TRIGGER IF NOT EXISTS {$table}_refuser_update
            BEFORE UPDATE ON {$table}
            BEGIN
                SELECT RAISE(ABORT, '{$table} est en ajout seul');
            END
        SQL);
        $pdo->exec(<<<SQL
            CREATE TRIGGER IF NOT EXISTS {$table}_refuser_delete
            BEFORE DELETE ON {$table}
            BEGIN
                SELECT RAISE(ABORT, '{$table} est en ajout seul');
            END
        SQL);
    }

    private static function verrouillerUpdateSeulement(\PDO $pdo, string $table): void
    {
        if (self::driver($pdo) === 'pgsql') {
            $fonction = "gamad_refuser_update_{$table}";
            $pdo->exec(<<<SQL
                CREATE OR REPLACE FUNCTION {$fonction}()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION '{$table} ne se modifie jamais après insertion';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            $pdo->exec("DROP TRIGGER IF EXISTS {$table}_immuable_update ON {$table}");
            $pdo->exec(<<<SQL
                CREATE TRIGGER {$table}_immuable_update
                BEFORE UPDATE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION {$fonction}()
            SQL);

            return;
        }

        $pdo->exec(<<<SQL
            CREATE TRIGGER IF NOT EXISTS {$table}_refuser_update
            BEFORE UPDATE ON {$table}
            BEGIN
                SELECT RAISE(ABORT, '{$table} ne se modifie jamais après insertion');
            END
        SQL);
    }

    private static function driver(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
