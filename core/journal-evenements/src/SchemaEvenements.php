<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Migration additive du journal central de CAP-CORE-014.
 *
 * Physiquement distinct de tout autre magasin, en particulier du journal
 * d'audit privé (`CAP-CORE-013`, `core/journal-operationnel`) : ce magasin ne
 * conserve que des faits communs, contractuels et autorisés à circuler.
 *
 * `evenement_commun` et `tentative_livraison` sont strictement en ajout seul
 * (triggers PostgreSQL/SQLite refusant UPDATE et DELETE, comme pour le
 * journal d'audit). `abonnement_cycle` et `lettre_morte_evenement` sont en
 * ajout seul également : une relance de lettre morte n'y écrit jamais, elle
 * crée une nouvelle `tentative_livraison` (type `RELANCE`) et fait évoluer
 * `livraison_evenement`, table à mutation contrôlée.
 */
final class SchemaEvenements
{
    public const VERSION = 1;

    public const TABLES = [
        'evenement_commun',
        'evenement_charge',
        'recu_publication',
        'abonnement_evenement',
        'abonnement_cycle',
        'abonnement_type_evenement',
        'abonnement_producteur',
        'abonnement_realm',
        'livraison_evenement',
        'tentative_livraison',
        'curseur_abonnement',
        'demande_rejeu',
        'lettre_morte_evenement',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS migration_journal_evenements (
                version INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )'
        );
        $deja = $pdo->query(
            'SELECT version FROM migration_journal_evenements WHERE version = 1'
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
                CREATE TABLE IF NOT EXISTS evenement_commun (
                    sequence_id                    {$id},
                    reference                       TEXT NOT NULL UNIQUE,
                    type_evenement                  TEXT NOT NULL,
                    contrat_reference                TEXT NOT NULL,
                    contrat_version                  TEXT NOT NULL,
                    producteur_capacite_reference     TEXT,
                    producteur_produit_reference      TEXT,
                    producteur_reference              TEXT NOT NULL,
                    source_reference                  TEXT NOT NULL,
                    realm_reference                   TEXT NOT NULL,
                    finalite_reference                TEXT NOT NULL,
                    sujet_type                        TEXT,
                    sujet_reference                   TEXT,
                    correlation_id                    TEXT NOT NULL,
                    causation_reference               TEXT,
                    idempotence_reference             TEXT NOT NULL,
                    survenu_le                        TEXT NOT NULL,
                    enregistre_le                     TEXT NOT NULL,
                    classification                    TEXT NOT NULL,
                    schema_empreinte                  TEXT NOT NULL,
                    charge_empreinte                  TEXT NOT NULL,
                    empreinte_precedente               TEXT,
                    empreinte_evenement                TEXT NOT NULL UNIQUE,
                    reconstruction                     INTEGER NOT NULL DEFAULT 0,
                    charge_expire_le                   TEXT,
                    UNIQUE(producteur_reference, idempotence_reference)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_commun_type ON evenement_commun(type_evenement)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_commun_contrat ON evenement_commun(contrat_reference, contrat_version)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_commun_producteur ON evenement_commun(producteur_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_commun_realm ON evenement_commun(realm_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_commun_sujet ON evenement_commun(sujet_type, sujet_reference)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_commun_correlation ON evenement_commun(correlation_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS evenement_commun_survenu ON evenement_commun(survenu_le)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS evenement_charge (
                    evenement_reference TEXT PRIMARY KEY,
                    media_type            TEXT NOT NULL,
                    schema_format          TEXT NOT NULL,
                    charge_json            TEXT NOT NULL,
                    empreinte              TEXT NOT NULL,
                    taille_octets          INTEGER NOT NULL,
                    cree_le                TEXT NOT NULL,
                    expire_le              TEXT
                )
            SQL);

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS recu_publication (
                    id                     {$id},
                    producteur_reference    TEXT NOT NULL,
                    idempotence_reference   TEXT NOT NULL,
                    evenement_reference     TEXT NOT NULL,
                    sequence_id             INTEGER NOT NULL,
                    accepte_le              TEXT NOT NULL,
                    UNIQUE(producteur_reference, idempotence_reference)
                )
            SQL);

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS abonnement_evenement (
                    reference                          TEXT PRIMARY KEY,
                    nom                                  TEXT NOT NULL,
                    consommateur_capacite_reference       TEXT,
                    consommateur_produit_reference         TEXT,
                    consommateur_reference                 TEXT NOT NULL,
                    organisation_reference                 TEXT,
                    realm_reference                        TEXT NOT NULL,
                    finalite_reference                     TEXT NOT NULL,
                    mode_livraison                         TEXT NOT NULL,
                    taille_lot_max                         INTEGER NOT NULL,
                    duree_bail_secondes                    INTEGER NOT NULL,
                    tentatives_max                         INTEGER NOT NULL,
                    cree_par_reference                     TEXT NOT NULL,
                    source_reference                       TEXT NOT NULL,
                    cree_le                                TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS abonnement_evenement_consommateur ON abonnement_evenement(consommateur_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS abonnement_cycle (
                    id                   {$id},
                    abonnement_reference  TEXT NOT NULL,
                    etat                  TEXT NOT NULL CHECK (etat IN ('PREPARATION','ACTIF','SUSPENDU','RETIRE')),
                    date_effet            TEXT NOT NULL,
                    motif                 TEXT,
                    acteur_reference      TEXT NOT NULL,
                    politique_reference   TEXT NOT NULL,
                    preuve_reference      TEXT NOT NULL,
                    correlation_id        TEXT,
                    cree_le               TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS abonnement_cycle_abonnement ON abonnement_cycle(abonnement_reference, id)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS abonnement_type_evenement (
                    id                    {$id},
                    abonnement_reference   TEXT NOT NULL,
                    contrat_reference      TEXT NOT NULL,
                    version_contrainte     TEXT,
                    type_evenement         TEXT NOT NULL,
                    cree_le                TEXT NOT NULL,
                    UNIQUE(abonnement_reference, contrat_reference, type_evenement)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS abonnement_type_abonnement ON abonnement_type_evenement(abonnement_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS abonnement_producteur (
                    id                    {$id},
                    abonnement_reference   TEXT NOT NULL,
                    producteur_reference   TEXT NOT NULL,
                    cree_le                TEXT NOT NULL,
                    UNIQUE(abonnement_reference, producteur_reference)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS abonnement_producteur_abonnement ON abonnement_producteur(abonnement_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS abonnement_realm (
                    id                    {$id},
                    abonnement_reference   TEXT NOT NULL,
                    realm_reference        TEXT NOT NULL,
                    portee                 TEXT NOT NULL CHECK (portee IN ('EXACT','DESCENDANTS_EXPLICITES')),
                    cree_le                TEXT NOT NULL,
                    UNIQUE(abonnement_reference, realm_reference)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS abonnement_realm_abonnement ON abonnement_realm(abonnement_reference)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS livraison_evenement (
                    reference                TEXT PRIMARY KEY,
                    abonnement_reference      TEXT NOT NULL,
                    evenement_reference       TEXT NOT NULL,
                    sequence_evenement        INTEGER NOT NULL,
                    etat                      TEXT NOT NULL CHECK (etat IN
                        ('DISPONIBLE','SOUS_BAIL','ACCUSE','A_REESSAYER','LETTRE_MORTE','ANNULE')),
                    disponible_le             TEXT NOT NULL,
                    bail_reference            TEXT,
                    bail_expire_le            TEXT,
                    tentatives                INTEGER NOT NULL DEFAULT 0,
                    prochaine_tentative_le    TEXT,
                    accuse_le                 TEXT,
                    dernier_code_erreur       TEXT,
                    rejeu                     INTEGER NOT NULL DEFAULT 0,
                    demande_rejeu_reference   TEXT,
                    cree_le                   TEXT NOT NULL,
                    UNIQUE(abonnement_reference, evenement_reference)
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS livraison_abonnement_etat ON livraison_evenement(abonnement_reference, etat, sequence_evenement)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS livraison_bail_expire ON livraison_evenement(bail_expire_le)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS livraison_prochaine_tentative ON livraison_evenement(prochaine_tentative_le)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS tentative_livraison (
                    id                   {$id},
                    livraison_reference   TEXT NOT NULL,
                    numero                INTEGER NOT NULL,
                    type_tentative        TEXT NOT NULL,
                    resultat              TEXT NOT NULL,
                    code_erreur           TEXT,
                    detail_sanitaire      TEXT,
                    commence_le           TEXT NOT NULL,
                    termine_le            TEXT
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS tentative_livraison_livraison ON tentative_livraison(livraison_reference, numero)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS curseur_abonnement (
                    abonnement_reference                     TEXT PRIMARY KEY,
                    derniere_sequence_contigue_accusee        INTEGER NOT NULL DEFAULT 0,
                    mis_a_jour_le                             TEXT NOT NULL
                )
            SQL);

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS demande_rejeu (
                    reference               TEXT PRIMARY KEY,
                    abonnement_reference     TEXT NOT NULL,
                    sequence_debut           INTEGER,
                    sequence_fin             INTEGER,
                    date_debut               TEXT,
                    date_fin                 TEXT,
                    types_json               TEXT NOT NULL,
                    motif                    TEXT NOT NULL,
                    etat                     TEXT NOT NULL CHECK (etat IN
                        ('DEMANDEE','VALIDEE','EN_COURS','TERMINEE','REFUSEE','ANNULEE')),
                    demandeur_reference      TEXT NOT NULL,
                    politique_reference      TEXT NOT NULL,
                    preuve_reference         TEXT NOT NULL,
                    correlation_id           TEXT,
                    volume_estime            INTEGER,
                    curseur_sequence         INTEGER,
                    cree_le                  TEXT NOT NULL,
                    termine_le               TEXT
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS demande_rejeu_abonnement ON demande_rejeu(abonnement_reference, etat)');

            $pdo->exec(<<<SQL
                CREATE TABLE IF NOT EXISTS lettre_morte_evenement (
                    reference               TEXT PRIMARY KEY,
                    livraison_reference      TEXT NOT NULL,
                    raison_code              TEXT NOT NULL,
                    tentatives_total         INTEGER NOT NULL,
                    premiere_erreur_le       TEXT NOT NULL,
                    derniere_erreur_le       TEXT NOT NULL,
                    cree_le                  TEXT NOT NULL
                )
            SQL);
            $pdo->exec('CREATE INDEX IF NOT EXISTS lettre_morte_livraison ON lettre_morte_evenement(livraison_reference)');

            self::verrouillerMutations($pdo, 'evenement_commun');
            self::verrouillerMutations($pdo, 'tentative_livraison');
            self::verrouillerMutations($pdo, 'abonnement_cycle');
            self::verrouillerMutations($pdo, 'lettre_morte_evenement');
            self::verrouillerMutations($pdo, 'recu_publication');
            self::verrouillerUpdateSeulement($pdo, 'evenement_charge');

            $st = $pdo->prepare(
                'INSERT INTO migration_journal_evenements(version, appliquee_le) VALUES(?, ?)'
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

    /** Table à mutation contrôlée mais sans modification après insertion : UPDATE refusé, DELETE gouverné (purge). */
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
