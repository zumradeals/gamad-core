<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Schéma relationnel de l'index dérivé (conception d'implémentation, Titre II).
 *
 * Portable PostgreSQL / SQLite. L'index étant dérivé et reconstructible, la
 * création commence par supprimer les tables : rejouer l'ingestion produit
 * toujours le même index (idempotence, INV-5). L'invariant d'ajout seul
 * (INV-3) est tenu par le code applicatif — Ingestion et Ctr04 n'émettent
 * jamais d'UPDATE ni de DELETE sur `statut`, `adoption` et
 * `relation_evolution` ; en déploiement, il est en outre durci par les
 * privilèges PostgreSQL (voir README).
 */
final class Schema
{
    public static function create(\PDO $pdo): void
    {
        $driver = Db::driver($pdo);
        $id = $driver === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        foreach ([
            'relation_evolution', 'etat_capacite', 'statut', 'version_norme',
            'delegation', 'etat_mandat', 'mandat', 'etat_fonction', 'fonction', 'titulaire',
            'adoption', 'norme', 'source', 'rang_normatif',
        ] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS {$t}");
        }

        // Hiérarchie normative de SOURCES-0001, Articles 25 à 33 (INV-8).
        // Le rang d'une norme procède de cette table et d'elle seule ; il n'est
        // jamais inventé. `INDETERMINE` y figure comme rang explicite : le
        // service déclare son ignorance plutôt que de présumer.
        $pdo->exec(<<<SQL
            CREATE TABLE rang_normatif (
                code    TEXT PRIMARY KEY,
                libelle TEXT NOT NULL,
                ordre   INTEGER NOT NULL,
                article TEXT NOT NULL
            )
        SQL);

        // Une source reconnue au sens de SOURCES-0001. L'authenticité (AUTH-n)
        // y est distincte du statut d'adoption (INV-9) : une source peut être
        // authentifiée et abrogée, ou de provenance déclarée et faire règle.
        $pdo->exec(<<<SQL
            CREATE TABLE source (
                reference    TEXT PRIMARY KEY,
                titre        TEXT NOT NULL,
                categorie    TEXT NOT NULL,
                authenticite TEXT NOT NULL,
                reserve      TEXT
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE norme (
                reference   TEXT PRIMARY KEY,
                titre       TEXT NOT NULL,
                rang_code   TEXT NOT NULL REFERENCES rang_normatif(code),
                domaine     TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE adoption (
                reference          TEXT PRIMARY KEY,
                autorite           TEXT NOT NULL,
                date_adoption      TEXT NOT NULL,
                signature_presente INTEGER NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE version_norme (
                id              {$id},
                norme_reference TEXT NOT NULL REFERENCES norme(reference),
                version         TEXT NOT NULL,
                empreinte_git   TEXT NOT NULL,
                chemin          TEXT NOT NULL,
                UNIQUE (norme_reference, version)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE statut (
                id                 {$id},
                version_norme_id   BIGINT NOT NULL REFERENCES version_norme(id),
                valeur             TEXT NOT NULL,
                date_effet         TEXT NOT NULL,
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        // État d'une capacité souveraine, en ajout seul (INV-3), fondé sur un
        // acte (INV-4). Séparé de `statut` : un état de capacité et un statut
        // de norme sont deux vocabulaires distincts et ne partagent pas une
        // colonne (INV-10, CONCEPTION-CAP-CORE-006, Art. 6 et 10).
        $pdo->exec(<<<SQL
            CREATE TABLE etat_capacite (
                id                 {$id},
                capacite_reference TEXT NOT NULL,
                dimension          TEXT NOT NULL
                    CHECK (dimension IN ('conception','implementation','exploitation','preuve')),
                valeur             TEXT NOT NULL,
                date_effet         TEXT NOT NULL,
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        // CAP-CORE-003 — autorités et mandats (CONCEPTION-CAP-CORE-003, Titre II).
        // Les vocabulaires d'état sont repris LITTÉRALEMENT des Articles 11 à 13
        // du registre adopté ; aucune valeur nouvelle n'est introduite.
        $pdo->exec(<<<SQL
            CREATE TABLE titulaire (
                reference TEXT PRIMARY KEY,
                libelle   TEXT NOT NULL,
                nature    TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE fonction (
                reference TEXT PRIMARY KEY,
                libelle   TEXT NOT NULL,
                source    TEXT NOT NULL
            )
        SQL);

        // État d'une fonction, en ajout seul (INV-3). Article 11 : 10 valeurs.
        $pdo->exec(<<<SQL
            CREATE TABLE etat_fonction (
                id                 {$id},
                fonction_reference TEXT NOT NULL REFERENCES fonction(reference),
                valeur             TEXT NOT NULL,
                date_effet         TEXT NOT NULL,
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE mandat (
                reference           TEXT PRIMARY KEY,
                fonction_reference  TEXT NOT NULL REFERENCES fonction(reference),
                titulaire_reference TEXT NOT NULL REFERENCES titulaire(reference),
                debut               TEXT NOT NULL,
                fin                 TEXT,
                niveau_preuve       TEXT NOT NULL,
                adoption_reference  TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        // État d'un mandat, en ajout seul (INV-3). Article 12 : 12 valeurs.
        $pdo->exec(<<<SQL
            CREATE TABLE etat_mandat (
                id                 {$id},
                mandat_reference   TEXT NOT NULL REFERENCES mandat(reference),
                valeur             TEXT NOT NULL,
                date_effet         TEXT NOT NULL,
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE delegation (
                reference          TEXT PRIMARY KEY,
                mandat_source      TEXT NOT NULL REFERENCES mandat(reference),
                delegataire        TEXT NOT NULL REFERENCES titulaire(reference),
                portee             TEXT NOT NULL,
                debut              TEXT NOT NULL,
                fin                TEXT,
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE relation_evolution (
                id                 {$id},
                norme_source       TEXT NOT NULL REFERENCES norme(reference),
                norme_cible        TEXT NOT NULL REFERENCES norme(reference),
                type               TEXT NOT NULL CHECK (type IN ('amende','remplace','abroge')),
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);
    }
}
