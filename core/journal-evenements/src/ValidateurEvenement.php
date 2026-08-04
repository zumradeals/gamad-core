<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Validation structurelle et sécuritaire d'une intention d'événement.
 *
 * Vérifie uniquement ce qui est vérifiable sans dépendance externe : la
 * présence des champs obligatoires de l'enveloppe, la taille et l'absence de
 * champs interdits dans la charge. Les vérifications contractuelles
 * (contrat actif, producteur déclaré, source active, realm actif) restent la
 * responsabilité de `RegistreEvenements`, qui seul détient les registres
 * externes.
 */
final class ValidateurEvenement
{
    /** @param array<string,mixed> $intention @return list<string> */
    public static function validerEnveloppe(array $intention): array
    {
        $ecarts = [];
        foreach (EnveloppeEvenement::CHAMPS_OBLIGATOIRES as $champ) {
            if (trim((string) ($intention[$champ] ?? '')) === '') {
                $ecarts[] = "champ obligatoire absent : {$champ}";
            }
        }
        if (
            trim((string) ($intention['producteur_capacite_reference'] ?? '')) === ''
            && trim((string) ($intention['producteur_produit_reference'] ?? '')) === ''
        ) {
            $ecarts[] = 'aucun producteur déclaré (capacité ou produit)';
        }
        if (
            trim((string) ($intention['producteur_capacite_reference'] ?? '')) !== ''
            && trim((string) ($intention['producteur_produit_reference'] ?? '')) !== ''
        ) {
            $ecarts[] = 'un seul producteur principal est autorisé (capacité OU produit)';
        }
        if (!in_array((string) ($intention['classification'] ?? ''), PolitiqueEvenements::CLASSIFICATIONS, true)) {
            $ecarts[] = 'classification hors vocabulaire canonique';
        }
        $survenu = (string) ($intention['survenu_le'] ?? '');
        if ($survenu !== '' && self::horodatageValide($survenu) === false) {
            $ecarts[] = 'survenu_le n’est pas un horodatage ISO 8601 valide';
        }

        return $ecarts;
    }

    /** @param array<string,mixed> $charge @return list<string> */
    public static function validerCharge(array $charge): array
    {
        $ecarts = [];
        $json = EnveloppeEvenement::jsonCanonique($charge);
        if (strlen($json) > PolitiqueEvenements::TAILLE_CHARGE_MAX_OCTETS) {
            $ecarts[] = 'charge au-delà de la taille maximale autorisée';
        }
        self::inspecterChamps($charge, $ecarts);

        return $ecarts;
    }

    /** @param array<string,mixed> $charge @param list<string> $ecarts */
    private static function inspecterChamps(array $charge, array &$ecarts, string $chemin = ''): void
    {
        foreach ($charge as $cle => $valeur) {
            $cleTexte = is_string($cle) ? $cle : (string) $cle;
            $cheminActuel = $chemin === '' ? $cleTexte : "{$chemin}.{$cleTexte}";
            if (is_string($cle) && self::champInterdit($cle)) {
                $ecarts[] = "champ interdit dans la charge : {$cheminActuel}";
                continue;
            }
            if (is_array($valeur)) {
                self::inspecterChamps($valeur, $ecarts, $cheminActuel);
                continue;
            }
            if (is_string($valeur) && self::ressembleAUnJeton($valeur)) {
                $ecarts[] = "valeur ressemblant à un secret ou un jeton : {$cheminActuel}";
            }
        }
    }

    private static function champInterdit(string $cle): bool
    {
        $normalisee = strtolower($cle);
        foreach (PolitiqueEvenements::FRAGMENTS_CHAMPS_INTERDITS as $fragment) {
            if (str_contains($normalisee, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function ressembleAUnJeton(string $valeur): bool
    {
        if (preg_match('/^Bearer\s+\S+/i', $valeur) === 1) {
            return true;
        }
        // Structure JWT : trois segments base64url séparés par des points.
        if (preg_match('/^[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}$/', $valeur) === 1) {
            return true;
        }

        return false;
    }

    private static function horodatageValide(string $valeur): bool
    {
        try {
            new \DateTimeImmutable($valeur);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
