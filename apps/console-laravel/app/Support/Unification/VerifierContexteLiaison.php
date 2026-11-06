<?php

declare(strict_types=1);

namespace App\Support\Unification;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Contrôles réels, sans route ni écriture.
 *
 * Les preuves de possession du compte et d'autorisation ne sont PAS
 * implémentées ici : elles restent systématiquement refusées.
 */
final class VerifierContexteLiaison
{
    /**
     * @return array{qualifie: bool, blocages: list<string>}
     */
    public static function verifier(Request $request, string $referenceIdentite): array
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

        $memeTransaction = self::registreDansTransactionLocale();

        return QualificationLiaisonReseau::evaluer(
            $sessionValide,
            $acteur,
            $referenceIdentite,
            false, // Aucune preuve de possession de compte implémentée.
            $memeTransaction,
            false, // Aucune autorisation institutionnelle de liaison implémentée.
        );
    }

    /**
     * Une transaction Laravel ne couvre pas deux connexions PDO distinctes.
     * Ce contrôle est volontairement conservateur : même serveur/base
     * PostgreSQL ne signifie pas même transaction.
     */
    public static function registreDansTransactionLocale(): bool
    {
        try {
            $application = DB::connection();
            $registre = DB::connection('gamad_identity');

            if ($application->getPdo() !== $registre->getPdo()) {
                return false;
            }

            return $application->getSchemaBuilder()->hasTable('users')
                && $application->getSchemaBuilder()->hasTable('reseau_comptes_identites')
                && $application->getSchemaBuilder()->hasTable('identite_inscrite');
        } catch (Throwable) {
            return false;
        }
    }
}
