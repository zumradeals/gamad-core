<?php

declare(strict_types=1);

namespace Gamad\RegistreSecretsCles;

/**
 * Migration additive du registre de gouvernance de CAP-CORE-016.
 *
 * Ce magasin ne conserve jamais le matériel secret : ni valeur en clair, ni
 * clé privée, ni mot de passe, ni phrase secrète. Il conserve uniquement les
 * références, versions, usages, rotations, compromissions et le matériel
 * public nécessaires pour gouverner des secrets conservés dans des
 * fournisseurs externes (`FournisseurSecret`).
 *
 * `secret_version_cycle`, `secret_rotation_execution` et `secret_compromission`
 * sont en ajout seul pour les états déjà actés — comme le journal d'audit.
 * `secret_ressource`, `secret_fournisseur`, `secret_version`,
 * `secret_rotation_plan` sont à mutation contrôlée : jamais réécrits pour
 * falsifier l'historique, seulement complétés par les colonnes prévues.
 * `secret_usage`, `secret_dependance` et `secret_materiel_public` se ferment
 * par `date_fin`, jamais supprimés.
 */
final class SchemaSecretsCles
{
    public const VERSION = 1;

    public const TABLES = [
        'secret_ressource',
        'secret_fournisseur',
        'secret_version',
        'secret_version_cycle',
        'secret_usage',
        'secret_dependance',
        'secret_rotation_plan',
        'secret_rotation_execution',
        'secret_compromission',
        'secret_materiel_public',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migration_registre_secrets_cles (
                version INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )'
        );
        $deja = $pdo->query(
            'SELECT version FROM migration_registre_secrets_cles WHERE version = 1'
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
                CREATE TABLE IF NOT EXISTS secret_ressource (
                    reference               TEXT PRIMARY KEY,
                    nom                      TEXT NOT NULL,
                    type_secret              TEXT NOT NULL,
                    finalite_reference       TEXT NOT NULL,
                    proprietaire_reference   TEXT NOT NULL,
                    source_reference         TEXT NOT NULL,
                    realm_reference          TEXT,
                    environnement_reference  TEXT NOT NULL,
                    classification_reference TEXT NOT NULL,
                    description              TEXT,
                    rotation_requise         INTEGER NOT NULL DEFAULT 0,
                    duree_rotation_jours     INTEGER,
                    cree_le                  TEXT NOT NULL,
                    modifie_le               TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_ressource_type ON secret_ressource(type_secret)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_ressource_realm ON secret_ressource(realm_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_ressource_environnement ON secret_ressource(environnement_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_fournisseur (
                    reference               TEXT PRIMARY KEY,
                    nom                      TEXT NOT NULL,
                    type_fournisseur         TEXT NOT NULL,
                    realm_reference          TEXT,
                    environnement_reference  TEXT NOT NULL,
                    proprietaire_reference   TEXT NOT NULL,
                    etat                     TEXT NOT NULL CHECK (etat IN
                        ('PREPARATION','ACTIF','DEGRADE','SUSPENDU','RETIRE')),
                    capacites_json           TEXT NOT NULL,
                    configuration_reference  TEXT,
                    cree_le                  TEXT NOT NULL,
                    modifie_le               TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_fournisseur_type ON secret_fournisseur(type_fournisseur)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_fournisseur_etat ON secret_fournisseur(etat)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_version (
                    id                       {$id},
                    secret_reference         TEXT NOT NULL,
                    version                  TEXT NOT NULL,
                    fournisseur_reference    TEXT NOT NULL,
                    handle_fournisseur       TEXT NOT NULL,
                    algorithme_reference     TEXT,
                    taille_bits              INTEGER,
                    empreinte_publique       TEXT,
                    identifiant_public       TEXT,
                    cle_publique             TEXT,
                    date_debut_prevue        TEXT,
                    date_fin_prevue          TEXT,
                    cree_par_reference       TEXT NOT NULL,
                    preuve_reference         TEXT NOT NULL,
                    cree_le                  TEXT NOT NULL,
                    UNIQUE(secret_reference, version)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_version_secret ON secret_version(secret_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_version_fournisseur ON secret_version(fournisseur_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_version_cycle (
                    id                    {$id},
                    secret_version_id     INTEGER NOT NULL,
                    etat                  TEXT NOT NULL CHECK (etat IN
                        ('PREPARATION','ACTIVE_ECRITURE','ACTIVE_LECTURE','DEPRECIEE',
                         'SUSPENDUE','REVOQUEE','COMPROMISE','DETRUITE')),
                    date_effet            TEXT NOT NULL,
                    motif                 TEXT,
                    acteur_reference      TEXT NOT NULL,
                    politique_reference   TEXT NOT NULL,
                    preuve_reference      TEXT NOT NULL,
                    correlation_id        TEXT,
                    cree_le               TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_version_cycle_version ON secret_version_cycle(secret_version_id, id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_version_cycle_etat ON secret_version_cycle(etat)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_usage (
                    id                     {$id},
                    secret_version_id      INTEGER,
                    secret_reference       TEXT NOT NULL,
                    capacite_reference     TEXT,
                    produit_reference      TEXT,
                    organisation_reference TEXT,
                    realm_reference        TEXT,
                    environnement_reference TEXT NOT NULL,
                    operation_reference    TEXT NOT NULL,
                    finalite_reference     TEXT NOT NULL,
                    mode_usage             TEXT NOT NULL,
                    date_debut             TEXT NOT NULL,
                    date_fin               TEXT,
                    acteur_reference       TEXT NOT NULL,
                    politique_reference    TEXT NOT NULL,
                    preuve_reference       TEXT NOT NULL,
                    cree_le                TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_usage_secret ON secret_usage(secret_reference, date_fin)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_usage_version ON secret_usage(secret_version_id)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_dependance (
                    id                       {$id},
                    secret_reference         TEXT NOT NULL,
                    secret_version_id        INTEGER,
                    type_dependance          TEXT NOT NULL,
                    ressource_reference      TEXT NOT NULL,
                    date_debut               TEXT NOT NULL,
                    date_fin                 TEXT,
                    obligation_conservation  INTEGER NOT NULL DEFAULT 1,
                    motif                    TEXT,
                    cree_le                  TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_dependance_version ON secret_dependance(secret_version_id, date_fin)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_rotation_plan (
                    reference                TEXT PRIMARY KEY,
                    secret_reference         TEXT NOT NULL,
                    ancienne_version_id      INTEGER,
                    nouvelle_version_id      INTEGER,
                    strategie                TEXT NOT NULL,
                    date_prevue              TEXT NOT NULL,
                    fenetre_fin              TEXT,
                    retour_arriere_autorise  INTEGER NOT NULL DEFAULT 1,
                    etapes_json              TEXT NOT NULL,
                    impact_json              TEXT NOT NULL,
                    etat                     TEXT NOT NULL CHECK (etat IN
                        ('BROUILLON','EN_VALIDATION','VALIDE','EN_COURS','REUSSI','ECHEC','ANNULE')),
                    cree_par_reference       TEXT NOT NULL,
                    preuve_reference         TEXT NOT NULL,
                    cree_le                  TEXT NOT NULL,
                    modifie_le               TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_rotation_plan_secret ON secret_rotation_plan(secret_reference, etat)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_rotation_execution (
                    reference           TEXT PRIMARY KEY,
                    plan_reference      TEXT NOT NULL,
                    etape_reference     TEXT NOT NULL,
                    etat                TEXT NOT NULL CHECK (etat IN
                        ('EN_COURS','REUSSIE','ECHOUEE')),
                    commence_le         TEXT NOT NULL,
                    termine_le          TEXT,
                    resultat_code       TEXT,
                    resume_json         TEXT NOT NULL,
                    acteur_reference    TEXT NOT NULL,
                    preuve_reference    TEXT NOT NULL,
                    correlation_id      TEXT,
                    cree_le             TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_rotation_execution_plan ON secret_rotation_execution(plan_reference, cree_le)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_compromission (
                    reference                TEXT PRIMARY KEY,
                    secret_version_id        INTEGER NOT NULL,
                    detectee_le              TEXT NOT NULL,
                    declaree_par_reference   TEXT NOT NULL,
                    source_reference         TEXT NOT NULL,
                    niveau                   TEXT NOT NULL CHECK (niveau IN
                        ('SUSPECTEE','PROBABLE','CONFIRMEE')),
                    portee_presumee          TEXT,
                    motif                    TEXT NOT NULL,
                    etat                     TEXT NOT NULL CHECK (etat IN
                        ('OUVERTE','CONTENUE','ROTATION_EN_COURS','CLOTUREE')),
                    preuve_reference         TEXT NOT NULL,
                    correlation_id           TEXT,
                    cree_le                  TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_compromission_version ON secret_compromission(secret_version_id)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS secret_materiel_public (
                    id                  {$id},
                    secret_version_id   INTEGER NOT NULL,
                    type_materiel       TEXT NOT NULL,
                    format               TEXT NOT NULL,
                    contenu_public       TEXT NOT NULL,
                    empreinte            TEXT NOT NULL,
                    date_debut           TEXT NOT NULL,
                    date_fin             TEXT,
                    cree_le              TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS secret_materiel_public_version ON secret_materiel_public(secret_version_id)');

            self::verrouillerMutations($pdo, 'secret_version_cycle');
            self::verrouillerMutations($pdo, 'secret_rotation_execution');
            self::verrouillerMutations($pdo, 'secret_compromission');
            self::verrouillerUpdateSeulement($pdo, 'secret_version');

            $st = $pdo->prepare(
                'INSERT INTO migration_registre_secrets_cles(version, appliquee_le) VALUES(?, ?)'
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

    /** Table strictement en ajout seul : UPDATE et DELETE refusés. */
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

    /** Table à mutation contrôlée mais sans modification après insertion : UPDATE refusé. */
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
