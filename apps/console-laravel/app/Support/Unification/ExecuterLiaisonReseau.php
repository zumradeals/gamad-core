<?php

declare(strict_types=1);

namespace App\Support\Unification;

use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Point d'entrée interne unique du parcours de liaison.
 * Aucune route publique ni élévation de privilèges console.
 */
final class ExecuterLiaisonReseau
{
    public static function executer(Request $request, CompteReseauUnifie $compte): void
    {
        // La qualification précède toute écriture. LierCompteIdentite effectue
        // ensuite l'admissibilité dans le registre Core souverain distinct.
        // Seule l'insertion de liaison est transactionnelle localement.
        $verdict = VerifierContexteLiaison::verifier(
            $request,
            $compte->identiteReference,
            $compte->userId,
        );

        if ($verdict['qualifie'] !== true) {
            throw new DomainException('Liaison refusée : ' . implode('; ', $verdict['blocages']));
        }

        // Vérification à nouveau dans la transaction applicative : elle réduit
        // la fenêtre entre qualification et insertion, sans prétendre rendre
        // atomique la révocation d'une session Core externe.
        DB::transaction(function () use ($request, $compte): void {
            $verdict = VerifierContexteLiaison::verifier(
                $request,
                $compte->identiteReference,
                $compte->userId,
            );

            if ($verdict['qualifie'] !== true) {
                throw new DomainException('Liaison refusée : qualification expirée');
            }

            // Une inscription historique ne suffit pas : le cycle Core doit
            // confirmer une identité active au moment de l'écriture.
            if (! ResoudreCompteReseauCore::identiteActive($compte->identiteReference)) {
                throw new DomainException('Liaison refusée : identité Core inactive');
            }

            // Les verrous et contraintes uniques concernent la base Réseau.
            // Aucune atomicité distribuée n'est revendiquée avec le Core.
            LierCompteIdentite::executer($compte, true);
        }, 1);
    }
}
