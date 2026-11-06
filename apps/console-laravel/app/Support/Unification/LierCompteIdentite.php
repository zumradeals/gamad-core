<?php

declare(strict_types=1);

namespace App\Support\Unification;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Liaison interne uniquement : aucune route publique ne doit invoquer ce service
 * sans preuve préalable d'autorisation et de possession du compte.
 *
 * Précondition : identite_inscrite est accessible sur la même connexion SQL.
 * La transaction verrouille la ligne canonique pour sérialiser les associations.
 */
final class LierCompteIdentite
{
    public static function executer(CompteReseauUnifie $compte, bool $autorise): void
    {
        if (! $autorise) {
            throw new DomainException('Liaison non autorisée');
        }

        if (! VerifierContexteLiaison::registreDansTransactionLocale()) {
            throw new DomainException('Registre Core hors du périmètre transactionnel');
        }

        DB::transaction(function () use ($compte): void {
            $identite = DB::table('identite_inscrite')
                ->where('reference', $compte->identiteReference)
                ->lockForUpdate()
                ->first(['reference', 'type', 'regime', 'provisoire']);

            if ($identite === null || $identite->type !== 'personne'
                || $identite->regime !== 'INSCRIT_AU_REGISTRE'
                || (int) $identite->provisoire !== 0) {
                throw new DomainException('Identité canonique non admissible');
            }

            $utilisateur = DB::table('users')
                ->where('id', $compte->userId)
                ->lockForUpdate()
                ->first(['id']);

            if ($utilisateur === null) {
                throw new DomainException('Compte Réseau absent');
            }

            // Les contraintes UNIQUE en base arbitrent les courses concurrentes.
            // Aucun update/upsert : une liaison existante ne peut être remplacée.
            DB::table('reseau_comptes_identites')->insert([
                'user_id' => $compte->userId,
                'identite_reference' => $compte->identiteReference,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 1);
    }
}
