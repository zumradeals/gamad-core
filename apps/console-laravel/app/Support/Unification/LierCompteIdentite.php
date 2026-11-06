<?php

declare(strict_types=1);

namespace App\Support\Unification;

use DomainException;
use Illuminate\Support\Facades\DB;
use Gamad\RegistreIdentites\Magasin as IdentitesMagasin;
use PDO;

/**
 * Liaison interne uniquement : aucune route publique ne doit invoquer ce service
 * sans preuve préalable d'autorisation et de possession du compte.
 *
 * Le Core reste souverain : la vérification d'identité est une lecture externe.
 * La transaction locale ne protège que le compte et l'unicité de la liaison.
 */
final class LierCompteIdentite
{
    public static function executer(CompteReseauUnifie $compte, bool $autorise): void
    {
        if (! $autorise) {
            throw new DomainException('Liaison non autorisée');
        }

        // Vérification du registre souverain, jamais de sa copie applicative.
        $registre = IdentitesMagasin::ouvrir();
        $requete = $registre->prepare(
            'SELECT type, regime, provisoire FROM identite_inscrite WHERE reference = ?'
        );
        $requete->execute([$compte->identiteReference]);
        $identite = $requete->fetch(PDO::FETCH_ASSOC);
        if ($identite === false || $identite['type'] !== 'personne'
            || $identite['regime'] !== 'INSCRIT_AU_REGISTRE'
            || (int) $identite['provisoire'] !== 0
            || ! ResoudreCompteReseauCore::identiteActive($compte->identiteReference, $registre)) {
            throw new DomainException('Identité Core non admissible');
        }

        DB::transaction(function () use ($compte): void {
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
