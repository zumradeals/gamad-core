<?php

declare(strict_types=1);

namespace Gamad\RegistreNormes;

/**
 * Schéma relationnel de l'index technique.
 *
 * Portable PostgreSQL / SQLite. L'index est reconstructible : la création
 * commence par supprimer les tables, de sorte que rejouer la reconstruction
 * depuis la baseline produise toujours le même index (idempotence). L'ajout
 * seul de `statut`, `adoption` et `relation_evolution` est tenu par le code
 * applicatif — CTR-04 n'émet jamais d'UPDATE ni de DELETE — et durci en
 * déploiement par les privilèges PostgreSQL (voir README).
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
            'denomination', 'etat_entite', 'entite', 'regle', 'politique',
            'adoption', 'norme', 'source', 'rang_normatif',
        ] as $t) {
            $pdo->exec("DROP TABLE IF EXISTS {$t}");
        }

        // Hiérarchie normative de référence. Le rang d'une norme procède de
        // cette table et d'elle seule ; il n'est jamais inventé. `INDETERMINE`
        // y figure comme rang explicite : le service déclare son ignorance
        // plutôt que de présumer.
        $pdo->exec(<<<SQL
            CREATE TABLE rang_normatif (
                code    TEXT PRIMARY KEY,
                libelle TEXT NOT NULL,
                ordre   INTEGER NOT NULL,
                article TEXT NOT NULL
            )
        SQL);

        // Une source reconnue. L'authenticité (AUTH-n) y est distincte du
        // statut d'adoption : une source peut être authentifiée et abrogée,
        // ou de provenance déclarée et faire règle.
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

        // Une version de norme est identifiée par sa référence canonique et son
        // numéro, jamais par un chemin de fichier : renommer un fichier ne
        // renomme pas une norme, et l'index ne référence plus aucun document.
        $pdo->exec(<<<SQL
            CREATE TABLE version_norme (
                id              {$id},
                norme_reference TEXT NOT NULL REFERENCES norme(reference),
                version         TEXT NOT NULL,
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

        // État d'une capacité, en ajout seul. Séparé de `statut` : un état de
        // capacité et un statut de norme sont deux vocabulaires distincts et
        // ne partagent pas une colonne.
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

        // CAP-CORE-004 — politiques d'autorisation. Les politiques sont des
        // DONNÉES de l'index, jamais du code du moteur : changer une règle est
        // un changement de données tracé, non un correctif enfoui.
        $pdo->exec(<<<SQL
            CREATE TABLE politique (
                reference          TEXT PRIMARY KEY,
                version            TEXT NOT NULL,
                libelle            TEXT NOT NULL,
                source             TEXT NOT NULL,
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        // `sujet_type` NULL = la règle vaut pour TOUT sujet, titulaire du
        // mandat compris. Un moteur qui exempterait l'autorité de ses
        // propres bornes ne serait pas un moteur d'autorisation.
        $pdo->exec(<<<SQL
            CREATE TABLE regle (
                id                  {$id},
                politique_reference TEXT NOT NULL REFERENCES politique(reference),
                effet               TEXT NOT NULL CHECK (effet IN ('PERMET','REFUSE')),
                action              TEXT NOT NULL,
                sujet_type          TEXT,
                motif               TEXT NOT NULL
            )
        SQL);

        // CAP-CORE-001 — identités.
        // AUCUNE colonne de profil, de dossier métier, de réputation ni de
        // jugement : l'exclusion est tenue par la STRUCTURE, non par
        // la discipline. Un registre d'identités souverain qui accumulerait des
        // jugements sur les personnes deviendrait un instrument de pouvoir sur
        // elles ; il n'y a pas d'endroit où les mettre.
        $pdo->exec(<<<SQL
            CREATE TABLE entite (
                reference TEXT PRIMARY KEY,
                type      TEXT NOT NULL
                    CHECK (type IN ('personne','organisation','produit','realm','agent','service','INDETERMINE')),
                libelle   TEXT NOT NULL,
                source    TEXT NOT NULL
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE etat_entite (
                id                 {$id},
                entite_reference   TEXT NOT NULL REFERENCES entite(reference),
                valeur             TEXT NOT NULL,
                date_effet         TEXT NOT NULL,
                adoption_reference TEXT NOT NULL REFERENCES adoption(reference)
            )
        SQL);

        // Dénominations relevées : plusieurs lignes pour une référence signalent
        // une divergence de provenance, que le service expose sans la trancher.
        $pdo->exec(<<<SQL
            CREATE TABLE denomination (
                id               {$id},
                entite_reference TEXT NOT NULL REFERENCES entite(reference),
                libelle          TEXT NOT NULL,
                source           TEXT NOT NULL
            )
        SQL);

        // CAP-CORE-003 — autorités et mandats. Les vocabulaires d'état sont des
        // données de l'index ; aucune valeur n'est introduite par le code.
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

        // État d'une fonction, en ajout seul.
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

        // État d'un mandat, en ajout seul.
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
