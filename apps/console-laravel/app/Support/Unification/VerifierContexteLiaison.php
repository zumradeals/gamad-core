<?php

declare(strict_types=1);

namespace App\Support\Unification;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Contrôles réels, sans route ni écriture.
 *
 * Possession : compte Laravel authentifié. Autorisation : décision PERMIS
 * explicite du Core. Sans politique dédiée, la liaison reste fermée.
 */
final class VerifierContexteLiaison
{
    /**
     * @return array{qualifie: bool, blocages: list<string>}
     */
    public static function verifier(Request $request, string $referenceIdentite, string $userId): array
    {
        $sessionValide = false;
        $acteur = null;

        try {
            $session = $request->session()->get('gamad_session');
            if (is_string($session) && $session !== '') {
                $verdict = (new Ctr16(Magasin::connecter()))->verifierSession($session);
                if (($verdict['valide'] ?? false) === true
                    && is_string($verdict['entite'] ?? null)) {
                    $sessionValide = true;
                    $acteur = $verdict['entite'];
                }
            }
        } catch (Throwable) {
            // Magasin indisponible ou session invalide : refus fermé.
        }

        $possessionProuvee = self::possedeCompte($request, $userId);
        $liaisonAutorisee = false;
        if ($sessionValide && $acteur === $referenceIdentite && $possessionProuvee) {
            try {
                $decision = (new Ctr03(PolitiquesMagasin::connecter()))
                    ->autoriser($acteur, 'lier compte reseau', 'reseau');
                $liaisonAutorisee = ($decision['decision'] ?? null) === 'PERMIS';
            } catch (Throwable) {
                // Politique absente ou registre indisponible : refus fermé.
            }
        }

        // La transaction de liaison porte uniquement sur le stockage local.
        // Le registre Core est consulte en lecture via son propre magasin.
        $memeTransaction = self::stockageLiaisonLocaleDisponible();

        return QualificationLiaisonReseau::evaluer(
            $sessionValide,
            $acteur,
            $referenceIdentite,
            $possessionProuvee,
            $memeTransaction,
            $liaisonAutorisee,
        );
    }

    public static function possedeCompte(Request $request, string $userId): bool
    {
        if (! preg_match('/^[1-9][0-9]*$/D', $userId)) {
            return false;
        }

        try {
            $utilisateur = $request->user();

            return $utilisateur !== null
                && (string) $utilisateur->getAuthIdentifier() === $userId;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * La transaction s'exécute exclusivement sur la connexion applicative.
     * Le registre Core doit désigner le même stockage canonique ; deux PDO
     * distincts vers ce stockage ne constituent pas deux transactions ici.
     * En cas de configuration ambiguë, la liaison reste interdite.
     */
    /**
     * La liaison locale est atomique sur sa base, sans transaction repartie
     * ni exigence de co-localisation avec le registre souverain.
     */
    public static function stockageLiaisonLocaleDisponible(): bool
    {
        try {
            $schema = DB::connection()->getSchemaBuilder();

            return $schema->hasTable('users')
                && $schema->hasTable('reseau_comptes_identites');
        } catch (Throwable) {
            return false;
        }
    }

    public static function registreDansTransactionLocale(): bool
    {
        try {
            $application = DB::connection();
            $registre = DB::connection('gamad_identity');
            $configApplication = $application->getConfig();
            $configRegistre = $registre->getConfig();

            if (($configApplication['driver'] ?? null) !== ($configRegistre['driver'] ?? null)) {
                return false;
            }

            $urlApplication = $configApplication['url'] ?? null;
            $urlRegistre = $configRegistre['url'] ?? null;

            if ($urlApplication || $urlRegistre) {
                if (! is_string($urlApplication) || ! is_string($urlRegistre)
                    || $urlApplication !== $urlRegistre) {
                    return false;
                }
            } elseif (($configApplication['driver'] ?? null) === 'sqlite') {
                // Deux connexions SQLite en mémoire ne partagent pas un registre.
                if (($configApplication['database'] ?? null) === ':memory:'
                    && $application->getPdo() !== $registre->getPdo()) {
                    return false;
                }
                if (($configApplication['database'] ?? null) !== ($configRegistre['database'] ?? null)) {
                    return false;
                }
            } elseif (($configApplication['driver'] ?? null) === 'pgsql') {
                foreach (['host', 'port', 'database', 'username', 'search_path'] as $cle) {
                    if (($configApplication[$cle] ?? null) !== ($configRegistre[$cle] ?? null)) {
                        return false;
                    }
                }
            } else {
                return false;
            }

            // Toutes les lectures et écritures de la liaison utilisent
            // exclusivement la connexion applicative, y compris identite_inscrite.
            return $application->getSchemaBuilder()->hasTable('users')
                && $application->getSchemaBuilder()->hasTable('reseau_comptes_identites')
                && $application->getSchemaBuilder()->hasTable('identite_inscrite');
        } catch (Throwable) {
            return false;
        }
    }
}
