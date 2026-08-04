<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

/**
 * Canonicalisation JSON déterministe (CAP-CORE-015, fiche partie 2 §8).
 *
 * Produit exactement les mêmes octets quelle que soit la plateforme
 * d'exécution (SQLite, PostgreSQL, CLI, HTTP) : clés d'objet triées
 * récursivement, ordre des listes préservé, Unicode normalisé (NFC),
 * encodage constant, aucun espace variable.
 *
 * `VERSION` est gelée : toute évolution de la convention de canonicalisation
 * doit introduire une nouvelle version plutôt que modifier le comportement
 * de celle-ci — une preuve existante reste vérifiable avec la version
 * enregistrée au moment de son émission.
 */
final class Canonicaliseur
{
    public const VERSION = 1;

    /** @param mixed $valeur */
    public static function canonicaliser(mixed $valeur): string
    {
        $normalisee = self::normaliser($valeur);

        $json = json_encode(
            $normalisee,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return $json;
    }

    /** @return mixed */
    private static function normaliser(mixed $valeur): mixed
    {
        if (is_float($valeur)) {
            if (!is_finite($valeur)) {
                throw new ExceptionPreuve('valeur JSON non finie (NAN/INF) refusée par le canonicaliseur');
            }

            return $valeur;
        }
        if (is_resource($valeur)) {
            throw new ExceptionPreuve('une ressource ne peut pas être canonicalisée');
        }
        if (is_object($valeur)) {
            throw new ExceptionPreuve('un objet PHP non normalisé ne peut pas être canonicalisé — fournir un tableau');
        }
        if (is_string($valeur)) {
            return self::normaliserUnicode($valeur);
        }
        if (!is_array($valeur)) {
            return $valeur;
        }

        $estListe = array_is_list($valeur);
        if ($estListe) {
            return array_map(self::normaliser(...), $valeur);
        }

        $clefs = array_keys($valeur);
        foreach ($clefs as $clef) {
            if (!is_string($clef)) {
                throw new ExceptionPreuve('une clé d\'objet doit être une chaîne pour être canonicalisée');
            }
        }
        sort($clefs, SORT_STRING);
        $trie = [];
        foreach ($clefs as $clef) {
            $trie[$clef] = self::normaliser($valeur[$clef]);
        }

        return $trie;
    }

    private static function normaliserUnicode(string $chaine): string
    {
        if (!mb_check_encoding($chaine, 'UTF-8')) {
            throw new ExceptionPreuve('chaîne non UTF-8 refusée par le canonicaliseur');
        }
        if (class_exists(\Normalizer::class)) {
            $normalisee = \Normalizer::normalize($chaine, \Normalizer::FORM_C);
            if ($normalisee !== false) {
                return $normalisee;
            }
        }

        return $chaine;
    }
}
