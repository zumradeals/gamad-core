<?php

declare(strict_types=1);

namespace App\Support\Unification;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Résolution en lecture seule du compte Réseau depuis la session Core.
 * Ne confère aucun accès à la console institutionnelle.
 */
final class ResoudreCompteReseauCore
{
    public static function resoudre(Request $request): ?CompteReseauUnifie
    {
        try {
            $session = $request->session()->get('gamad_session');
            if (! is_string($session) || $session === '') {
                return null;
            }

            $verdict = (new Ctr16(Magasin::connecter()))->verifierSession($session);
            $identite = $verdict['entite'] ?? null;
            if (($verdict['valide'] ?? false) !== true
                || ! is_string($identite) || $identite === '') {
                return null;
            }

            if (! VerifierContexteLiaison::registreDansTransactionLocale()) {
                return null;
            }

            $lien = DB::table('reseau_comptes_identites')
                ->join('identite_inscrite', 'identite_inscrite.reference', '=', 'reseau_comptes_identites.identite_reference')
                ->join('users', 'users.id', '=', 'reseau_comptes_identites.user_id')
                ->where('reseau_comptes_identites.identite_reference', $identite)
                ->where('identite_inscrite.type', 'personne')
                ->where('identite_inscrite.regime', 'INSCRIT_AU_REGISTRE')
                ->where('identite_inscrite.provisoire', 0)
                ->first(['reseau_comptes_identites.user_id', 'reseau_comptes_identites.identite_reference']);

            if ($lien === null) {
                return null;
            }

            // L'inscription est immuable : seul le journal de cycle dit si
            // l'identité est encore utilisable. Absence d'événement = refus.
            $dernierCycle = DB::table('evenement_cycle_identite')
                ->where('identite_reference', $identite)
                ->whereNotIn('evenement_type', ['RATTACHEMENT_PRODUIT', 'RETRAIT_PRODUIT'])
                ->orderByDesc('date_effet')
                ->orderByDesc('id')
                ->value('etat_apres');

            if (! in_array($dernierCycle, ['ACTIVE', 'VERIFIEE'], true)) {
                return null;
            }

            return CompteReseauUnifie::creer((string) $lien->user_id, $lien->identite_reference);
        } catch (Throwable) {
            return null;
        }
    }
}
