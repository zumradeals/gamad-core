<?php

declare(strict_types=1);

namespace App\Support\Unification;

use PDO;
use Throwable;

/**
 * Contrôle en lecture seule du registre persistant Core.
 * Un rapprochement n'est pas une preuve d'identité, ni une autorisation.
 */
final class VerifierRegistreIdentites
{
    /**
     * @param list<array{reseau_user_id: string, core_reference: string}> $liens
     * @return array{valides: list<array{reseau_user_id: string, core_reference: string}>, conflits: list<string>}
     */
    public static function verifier(PDO $pdo, array $liens): array
    {
        $resultat = RapprochementIdentites::verifier($liens);
        if ($resultat['conflits'] !== []) {
            return ['valides' => [], 'conflits' => $resultat['conflits']];
        }

        try {
            $statement = $pdo->prepare(
                'SELECT reference, type, regime, provisoire FROM identite_inscrite WHERE reference = ?'
            );
            if ($statement === false) {
                return ['valides' => [], 'conflits' => ['Registre Core inaccessible']];
            }
            $valides = [];
            $conflits = [];
            foreach ($resultat['valides'] as $index => $lien) {
                $statement->execute([$lien['core_reference']]);
                $identite = $statement->fetch(PDO::FETCH_ASSOC);
                if (! is_array($identite)) {
                    $conflits[] = 'Ligne '.($index + 1).' : identité Core absente';
                } elseif ($identite['type'] !== 'personne' || $identite['regime'] !== 'INSCRIT_AU_REGISTRE'
                    || (int) $identite['provisoire'] !== 0) {
                    $conflits[] = 'Ligne '.($index + 1).' : identité Core non admissible au rapprochement automatique';
                } else {
                    $valides[] = $lien;
                }
            }

            // Une erreur invalide le lot entier : pas de rapprochement partiel.
            return ['valides' => $conflits === [] ? $valides : [], 'conflits' => $conflits];
        } catch (Throwable) {
            // Ne jamais exposer des détails de connexion ou de schéma dans la réponse.
            return ['valides' => [], 'conflits' => ['Registre Core inaccessible ou schéma incompatible']];
        }
    }
}
