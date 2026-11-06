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
        // ensuite ses propres contrôles d'admissibilité sous verrous SQL.
        // Un registre sur une autre connexion PDO est refusé, jamais simulé.
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

            // La transaction englobante appartient au même stockage canonique.
            // L'insertion et les verrous de LierCompteIdentite sont imbriqués
            // sur cette connexion, jamais sur le PDO du registre secondaire.
            LierCompteIdentite::executer($compte, true);
        }, 1);
    }
}
