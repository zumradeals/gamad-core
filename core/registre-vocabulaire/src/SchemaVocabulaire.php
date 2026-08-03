<?php

declare(strict_types=1);

namespace Gamad\RegistreVocabulaire;

/**
 * Migration additive du registre persistant de CAP-CORE-010.
 *
 * Onze tables, toutes distinctes des autres magasins. Les versions sont
 * immuables dès la soumission ; seul leur cycle continue d'évoluer, en ajout
 * seul, comme pour `core/registre-contrats` et `core/registre-politiques`.
 */
final class SchemaVocabulaire
{
    public const VERSION = 1;

    public const TABLES = [
        'vocabulaire',
        'vocabulaire_version',
        'vocabulaire_version_cycle',
        'terme',
        'terme_libelle',
        'terme_alias',
        'terme_relation',
        'terme_mapping_externe',
        'terme_usage',
        'vocabulaire_compatibilite',
        'vocabulaire_conformite',
        'vocabulaire_projection',
    ];

    public static function migrer(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS migration_registre_vocabulaire (
                version      INTEGER PRIMARY KEY,
                appliquee_le TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS vocabulaire (
                reference               TEXT PRIMARY KEY,
                namespace               TEXT NOT NULL UNIQUE,
                nom                     TEXT NOT NULL,
                domaine                 TEXT NOT NULL,
                proprietaire_reference  TEXT NOT NULL,
                source_reference        TEXT NOT NULL,
                portee                  TEXT NOT NULL CHECK (portee IN
                    ('CORE','ECOSYSTEME','CONTRAT','CAPACITE','PRODUIT_PARTAGE')),
                description             TEXT,
                cree_le                 TEXT NOT NULL,
                modifie_le              TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS vocabulaire_version (
                id                     {$id},
                vocabulaire_reference  TEXT NOT NULL,
                version                TEXT NOT NULL,
                schema_version         INTEGER NOT NULL DEFAULT 1,
                date_effet_prevue      TEXT,
                empreinte_contenu      TEXT,
                cree_par_reference     TEXT NOT NULL,
                preuve_reference       TEXT NOT NULL,
                cree_le                TEXT NOT NULL,
                UNIQUE(vocabulaire_reference, version)
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS vocabulaire_version_voc ON vocabulaire_version(vocabulaire_reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS vocabulaire_version_cycle (
                id                      {$id},
                vocabulaire_version_id  INTEGER NOT NULL,
                etat                    TEXT NOT NULL CHECK (etat IN
                    ('BROUILLON','EN_VALIDATION','ACTIVE','DEPRECIEE','REMPLACEE','RETIREE')),
                date_effet              TEXT NOT NULL,
                motif                   TEXT,
                acteur_reference        TEXT NOT NULL,
                politique_reference     TEXT NOT NULL,
                preuve_reference        TEXT NOT NULL,
                correlation_id          TEXT,
                cree_le                 TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS vocabulaire_version_cycle_version ON vocabulaire_version_cycle(vocabulaire_version_id, date_effet, id)');

        // `reference` est la référence stable du terme, à travers ses
        // versions successives (ex. TERM-GAMAD-PRODUIT-ETAT-ACTIF) ; `code`
        // (ex. ACTIF) n'est unique que dans sa version.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS terme (
                reference               TEXT PRIMARY KEY,
                vocabulaire_version_id  INTEGER NOT NULL,
                code                    TEXT NOT NULL,
                definition              TEXT NOT NULL,
                type_semantique         TEXT NOT NULL CHECK (type_semantique IN
                    ('TYPE','ETAT','ACTION','FINALITE','RELATION','NIVEAU','RESULTAT',
                     'ROLE','CATEGORIE','ERREUR','ENVIRONNEMENT','CLASSIFICATION')),
                ordre_affichage         INTEGER,
                date_debut              TEXT NOT NULL,
                date_fin                TEXT,
                remplace_par_reference  TEXT,
                cree_le                 TEXT NOT NULL,
                UNIQUE(vocabulaire_version_id, code)
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_version ON terme(vocabulaire_version_id)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS terme_libelle (
                id                    {$id},
                terme_reference       TEXT NOT NULL,
                locale                TEXT NOT NULL,
                libelle               TEXT NOT NULL,
                description_courte    TEXT,
                principal             INTEGER NOT NULL DEFAULT 1 CHECK (principal IN (0,1)),
                cree_le               TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_libelle_terme ON terme_libelle(terme_reference, locale)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS terme_alias (
                id                {$id},
                terme_reference   TEXT NOT NULL,
                alias             TEXT NOT NULL,
                locale            TEXT,
                type_alias        TEXT NOT NULL CHECK (type_alias IN
                    ('ANCIEN_CODE','LIBELLE','ABREVIATION','CODE_EXTERNE','ORTHOGRAPHE_HISTORIQUE')),
                date_debut        TEXT NOT NULL,
                date_fin          TEXT,
                source_reference  TEXT NOT NULL,
                cree_le           TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_alias_terme ON terme_alias(terme_reference)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_alias_alias ON terme_alias(alias)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS terme_relation (
                id                       {$id},
                terme_source_reference   TEXT NOT NULL,
                terme_cible_reference    TEXT NOT NULL,
                type_relation            TEXT NOT NULL CHECK (type_relation IN
                    ('PLUS_LARGE_QUE','PLUS_ETROIT_QUE','EQUIVALENT_EXPLICITE','REMPLACE',
                     'ASSOCIE_A','INCOMPATIBLE_AVEC')),
                date_effet               TEXT NOT NULL,
                preuve_reference         TEXT NOT NULL,
                cree_le                  TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_relation_source ON terme_relation(terme_source_reference)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_relation_cible ON terme_relation(terme_cible_reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS terme_mapping_externe (
                id                   {$id},
                terme_reference      TEXT NOT NULL,
                systeme_reference    TEXT NOT NULL,
                vocabulaire_externe  TEXT NOT NULL,
                code_externe         TEXT NOT NULL,
                sens                 TEXT NOT NULL CHECK (sens IN ('ENTRANT','SORTANT','BIDIRECTIONNEL')),
                statut_mapping       TEXT NOT NULL CHECK (statut_mapping IN
                    ('EXACT','APPROXIMATIF','PERTE_INFORMATION','INTERDIT')),
                date_debut           TEXT NOT NULL,
                date_fin             TEXT,
                preuve_reference     TEXT NOT NULL,
                cree_le              TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_mapping_terme ON terme_mapping_externe(terme_reference)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS terme_usage (
                id                     {$id},
                terme_reference        TEXT NOT NULL,
                capacite_reference     TEXT,
                contrat_reference      TEXT,
                contrat_version        TEXT,
                politique_reference    TEXT,
                produit_reference      TEXT,
                usage_type             TEXT NOT NULL CHECK (usage_type IN
                    ('ENTREE','SORTIE','REGLE','ETAT_PERSISTE','AFFICHAGE','MAPPING','EVENEMENT','SIGNAL')),
                obligatoire            INTEGER NOT NULL DEFAULT 0 CHECK (obligatoire IN (0,1)),
                date_debut             TEXT NOT NULL,
                date_fin               TEXT,
                cree_le                TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS terme_usage_terme ON terme_usage(terme_reference)');

        // Non listée en section 11 de la fiche de codage, mais nécessaire :
        // `analyserCompatibilite()` (section 16.10) doit persister un
        // résultat en ajout seul, lié à l'empreinte exacte de la version
        // (section 25), au même titre que `contrat_compatibilite` pour
        // CAP-CORE-009.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS vocabulaire_compatibilite (
                id                       {$id},
                vocabulaire_version_id   INTEGER NOT NULL,
                version_comparee_id      INTEGER,
                resultat                 TEXT NOT NULL CHECK (resultat IN
                    ('COMPATIBLE','ADAPTATION_REQUISE','RUPTURE')),
                divergences_json         TEXT NOT NULL,
                acteur_reference         TEXT NOT NULL,
                cree_le                  TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS vocabulaire_compatibilite_version ON vocabulaire_compatibilite(vocabulaire_version_id)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS vocabulaire_conformite (
                reference               TEXT PRIMARY KEY,
                vocabulaire_version_id  INTEGER NOT NULL,
                consommateur_reference  TEXT NOT NULL,
                type_consommateur       TEXT NOT NULL CHECK (type_consommateur IN ('CAPACITE','PRODUIT')),
                resultat                TEXT NOT NULL CHECK (resultat IN ('CONFORME','NON_CONFORME','INCOMPLET')),
                commit_reference        TEXT,
                rapport_resume_json     TEXT NOT NULL,
                execute_le              TEXT NOT NULL,
                expire_le               TEXT
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS vocabulaire_conformite_version ON vocabulaire_conformite(vocabulaire_version_id)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS vocabulaire_projection (
                id                      {$id},
                vocabulaire_version_id  INTEGER NOT NULL,
                type_projection         TEXT NOT NULL CHECK (type_projection IN
                    ('JSON','PHP_CONSTANTS','OPENAPI_ENUM','SQL_CHECK','DOCUMENTATION')),
                chemin_artefact         TEXT,
                contenu_json            TEXT,
                empreinte_artefact      TEXT NOT NULL,
                generee_le              TEXT NOT NULL,
                cree_le                 TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS vocabulaire_projection_version ON vocabulaire_projection(vocabulaire_version_id)');

        $st = $pdo->prepare(
            'INSERT INTO migration_registre_vocabulaire(version,appliquee_le)
             SELECT ?, ? WHERE NOT EXISTS (
                 SELECT 1 FROM migration_registre_vocabulaire WHERE version = ?
             )'
        );
        $st->execute([self::VERSION, gmdate('c'), self::VERSION]);
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

    private static function driver(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
