<?php

declare(strict_types=1);

namespace App\Support\Unification;

/**
 * Qualification préalable à l'ouverture des inscriptions Réseau.
 * Le Core utilise une session CTR-16, distincte de l'authentification Laravel.
 * Ce contrôle n'accorde aucun droit et n'effectue aucune écriture.
 */
final class QualificationLiaisonReseau
{
    /**
     * @return array{qualifie: bool, blocages: list<string>}
     */
    public static function evaluer(
        bool $sessionCoreValide,
        ?string $acteurCore,
        ?string $referenceIdentite,
        bool $possessionCompteProuvee,
        bool $registreMemeTransaction,
        bool $autorisationInstitutionnelleVerifiee,
    ): array {
        $blocages = [];

        if (! $sessionCoreValide || $acteurCore === null || trim($acteurCore) === '') {
            $blocages[] = 'Session Core CTR-16 non vérifiée';
        }
        if ($referenceIdentite === null || trim($referenceIdentite) === ''
            || $acteurCore !== $referenceIdentite) {
            $blocages[] = 'Identité canonique non liée à l’acteur authentifié';
        }
        if (! $possessionCompteProuvee) {
            $blocages[] = 'Possession du compte Réseau non démontrée';
        }
        if (! $registreMemeTransaction) {
            $blocages[] = 'Registre Core hors du périmètre transactionnel';
        }
        if (! $autorisationInstitutionnelleVerifiee) {
            $blocages[] = 'Autorisation de liaison non vérifiée';
        }

        return ['qualifie' => $blocages === [], 'blocages' => $blocages];
    }
}
