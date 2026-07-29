<?php

declare(strict_types=1);

namespace Gamad\RegistreIdentites;

/**
 * Magasin des identités INSCRITES — `CAP-CORE-001`, loi révisée (ADOPTION-0064).
 *
 * ------------------------------------------------------------------------
 * INV-73 — DEUX RÉGIMES DE VÉRITÉ, JAMAIS MÊLÉS
 * ------------------------------------------------------------------------
 *
 * Le module de `CAP-CORE-007` porte un schéma dérivé que `Schema::create()`
 * DÉTRUIT et reconstruit à chaque ingestion : `entite`, `etat_entite` et
 * `denomination` y figurent, et c'est correct — les sept entités que le corpus
 * déclare sont reconstructibles depuis les fichiers Git, qui demeurent la
 * source de vérité (INV-5).
 *
 * Une personne inscrite par un produit n'est déclarée par AUCUN fichier du
 * corpus, et ne le sera jamais. Pour elle, le registre EST la source. Placer
 * son inscription dans une table que l'ingestion détruit la ferait disparaître
 * à la première réindexation — c'est `M-84`, et ce n'est pas une menace
 * hypothétique : c'est ce que le code aurait fait si l'écriture avait été
 * ouverte sur le schéma existant.
 *
 * Les tables ci-dessous sont donc créées `IF NOT EXISTS`, ne figurent dans
 * AUCUNE liste de suppression, et sont détenues par le module de
 * `CAP-CORE-001`. La séparation est physique, non disciplinaire : il n'y a pas
 * de chemin par lequel une réindexation puisse les atteindre.
 *
 * ------------------------------------------------------------------------
 * L'AJOUT SEUL EST TENU PAR LA STRUCTURE
 * ------------------------------------------------------------------------
 *
 * `identite_inscrite` ne porte QUE des faits de création, tous immuables.
 * L'état (INV-21) et le niveau d'assurance (INV-78) n'y ont pas de colonne :
 * ils se DÉRIVENT de `evenement_cycle` et `evenement_assurance`.
 *
 * Il n'existe donc aucune colonne d'assurance à écrire, et `INV-78` — l'
 * assurance ne se déduit d'aucun usage — cesse d'être une consigne pour
 * devenir une impossibilité. Aucun `UPDATE`, aucun `DELETE` n'est émis par
 * `Ctr01` sur ces tables.
 *
 * ------------------------------------------------------------------------
 * INV-19 DEMEURE ENTIER
 * ------------------------------------------------------------------------
 *
 * Aucune colonne de profil, de contenu, de dossier métier, de réputation ni de
 * jugement. Le périmètre s'ouvre aux personnes et aux organisations ; il ne
 * s'ouvre à aucune donnée nouvelle sur elles.
 */
final class SchemaInscription
{
    /** Tables du magasin d'inscription. Aucune n'est jamais supprimée. */
    public const TABLES = [
        'identite_inscrite',
        'evenement_cycle',
        'evenement_assurance',
        'relation_produit',
        'relation_organisation',
        'rapprochement',
    ];

    public static function create(\PDO $pdo): void
    {
        $id = self::driver($pdo) === 'pgsql'
            ? 'bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        // Faits de création, tous immuables. Ni état, ni assurance : ils se
        // dérivent des événements, et n'ont donc pas d'endroit où être écrits.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS identite_inscrite (
                reference             TEXT PRIMARY KEY,
                type                  TEXT NOT NULL
                    CHECK (type IN ('personne','organisation','produit','realm','agent','service','INDETERMINE')),
                libelle               TEXT NOT NULL,
                regime                TEXT NOT NULL
                    CHECK (regime = 'INSCRIT_AU_REGISTRE'),
                canal                 TEXT NOT NULL,
                producteur            TEXT NOT NULL,
                politique_inscription TEXT NOT NULL,
                source_inscription    TEXT NOT NULL,
                classification        TEXT NOT NULL,
                date_creation         TEXT NOT NULL
            )
        SQL);

        // INV-21 — le cycle de vie est en ajout seul. L'état courant est le
        // dernier événement par date d'effet ; l'état antérieur demeure lisible.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS evenement_cycle (
                id                    {$id},
                identite_reference    TEXT NOT NULL,
                evenement_type        TEXT NOT NULL,
                etat_avant            TEXT,
                etat_apres            TEXT NOT NULL,
                source                TEXT NOT NULL,
                politique_inscription TEXT,
                acteur_reference      TEXT NOT NULL,
                date_effet            TEXT NOT NULL
            )
        SQL);

        // INV-78 — SEUL chemin d'écriture de l'assurance. Le niveau ne se
        // déduit ni de l'usage, ni de l'ancienneté, ni du nombre de produits :
        // il n'existe que si un événement de preuve l'a inscrit.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS evenement_assurance (
                id                 {$id},
                identite_reference TEXT NOT NULL,
                niveau             TEXT NOT NULL
                    CHECK (niveau IN ('A0','A1','A2','A3')),
                preuve             TEXT NOT NULL,
                source             TEXT NOT NULL,
                date_effet         TEXT NOT NULL
            )
        SQL);

        // INV-77 — toute relation porte type, état, source, durée et
        // classification. `produit_reference` n'est PAS une clé étrangère vers
        // `entite` : cette table appartient au régime dérivé et disparaît à
        // chaque ingestion. Les deux régimes ne se mêlent pas, jusque dans les
        // contraintes.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS relation_produit (
                id                 {$id},
                identite_reference TEXT NOT NULL,
                produit_reference  TEXT NOT NULL,
                relation_type      TEXT NOT NULL,
                etat               TEXT NOT NULL,
                sujet_local_opaque TEXT,
                source             TEXT NOT NULL,
                date_debut         TEXT NOT NULL,
                date_fin           TEXT,
                classification     TEXT NOT NULL
            )
        SQL);

        // `mandat_reference` demeure NULL tant que CAP-CORE-003 n'a pas
        // vérifié un mandat. Une relation REPRESENTANT sans mandat est
        // inscriptible et n'est JAMAIS opposable : le service restitue les deux
        // faits séparément et ne présente pas le premier comme valant le second.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS relation_organisation (
                id                     {$id},
                identite_reference     TEXT NOT NULL,
                organisation_reference TEXT NOT NULL,
                relation_type          TEXT NOT NULL,
                etat                   TEXT NOT NULL,
                mandat_reference       TEXT,
                source                 TEXT NOT NULL,
                date_debut             TEXT NOT NULL,
                date_fin               TEXT,
                classification         TEXT NOT NULL
            )
        SQL);

        // INV-80 — un doublon probable est SIGNALÉ, jamais fusionné d'office.
        // `decideur` demeure NULL tant que l'état est PROPOSE : c'est la
        // décision qui le remplit, et rien d'autre ne le peut.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS rapprochement (
                id          {$id},
                reference_a TEXT NOT NULL,
                reference_b TEXT NOT NULL,
                preuves     TEXT NOT NULL,
                etat        TEXT NOT NULL
                    CHECK (etat IN ('PROPOSE','VALIDE','REJETE')),
                decideur    TEXT,
                date_effet  TEXT NOT NULL
            )
        SQL);
    }

    /**
     * Les tables du magasin sont-elles présentes ?
     *
     * Éprouvé par la garde après une réindexation complète : si l'une manque,
     * `M-84` s'est produite.
     */
    public static function present(\PDO $pdo): bool
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
