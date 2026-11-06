<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;

/** Orchestration Core commune à l'inscription web et aux produits authentifiés. */
final class ParcoursInscriptionGamad
{
    public const PRODUIT_WEB = 'PRD-GAMAD-005';

    public function __construct(private CreerCompteGamad $creer, private LivrerVerification $livrer)
    {
    }

    public function inscrire(array $donnees, string $produit, ?string $correlation): array
    {
        $execution = $this->creer->executer(
            $donnees,
            $produit,
            $correlation,
        );

        if ($execution['statut'] === 201
            && isset($execution['corps']['verification'])
            && is_array($execution['corps']['verification'])) {
            $verification = $execution['corps']['verification'];
            $code = (string) ($verification['code'] ?? '');
            $expireLe = (string) ($verification['expire_le'] ?? '');

            if ($code === '' || $expireLe === '') {
                return $this->resultat([
                    'erreur' => 'VERIFICATION_INCOMPLETE',
                    'message' => 'Le compte existe mais sa vérification n’a pas pu être livrée.',
                ], 503, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
            }

            $livraison = $this->livrer->executer(
                (string) $donnees['type_identifiant'],
                (string) $donnees['identifiant'],
                $code,
                $expireLe,
                [
                    'produit' => $produit,
                    'identifiant_reference' => $execution['corps']['compte']['identifiant_reference'] ?? null,
                    'verification_reference' => $verification['reference'] ?? null,
                    'correlation' => $correlation,
                ],
            );

            // Le code brut ne franchit jamais la frontière Core -> produit.
            unset($execution['corps']['verification']['code']);
            $execution['corps']['verification']['livraison'] = [
                'canal' => $livraison['canal'],
                'livree' => $livraison['livree'],
            ];

            if ($livraison['livree'] !== true) {
                return $this->resultat([
                    'erreur' => 'VERIFICATION_NON_LIVREE',
                    'message' => 'Le compte a été créé mais le code de vérification n’a pas pu être livré.',
                    'compte' => $execution['corps']['compte'] ?? null,
                    'verification' => $execution['corps']['verification'],
                    'motif' => $livraison['motif'] ?? 'LIVRAISON_INDISPONIBLE',
                ], 503, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
            }
        }

        return $this->resultat(
            $execution['corps'],
            $execution['statut'],
            [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }

    public function verifier(array $donnees, string $produit, ?string $correlation): array
    {
        try {
            $pdo = IdentiteMagasin::connecter();
            $st = $pdo->prepare(
                'SELECT producteur FROM verification_identifiant WHERE reference = ? AND identifiant_reference = ? LIMIT 1'
            );
            $st->execute([
                (string) $donnees['verification_reference'],
                (string) $donnees['identifiant_reference'],
            ]);
            $producteur = $st->fetchColumn();
            if (!is_string($producteur) || $producteur === '' || !hash_equals($producteur, $produit)) {
                return $this->resultat([
                    'erreur' => 'VERIFICATION_NON_AUTORISEE',
                    'message' => 'Ce produit ne peut pas consommer cette vérification.',
                ], 403);
            }

            $resultat = (new IdentifiantsResolution($pdo))->verifierPossession(
                (string) $donnees['identite'],
                (string) $donnees['identifiant_reference'],
                (string) $donnees['verification_reference'],
                (string) $donnees['code'],
            );
        } catch (\Throwable) {
            return $this->resultat([
                'erreur' => 'VERIFICATION_INDISPONIBLE',
                'message' => 'La vérification ne peut pas être traitée.',
            ], 503);
        }

        $acceptee = !isset($resultat['refus']);
        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'IDENTITE',
                'type' => 'VERIFICATION_IDENTIFIANT_COMPTE',
                'acteur' => $produit,
                'action' => 'vérifier la possession d’un identifiant de Compte GAMAD',
                'ressource' => (string) $donnees['identifiant_reference'],
                'decision' => $acceptee ? 'EXECUTEE' : 'REFUSEE',
                'motif' => $resultat['refus'] ?? null,
                'correlation_id' => $correlation,
                'donnees' => [
                    'verification_reference' => (string) $donnees['verification_reference'],
                ],
            ]);
        } catch (\Throwable) {
            return $this->resultat([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'La preuve opérationnelle de vérification n’a pas pu être produite.',
            ], 503);
        }

        if (!$acceptee) {
            return $this->resultat([
                'erreur' => (string) $resultat['refus'],
                'message' => 'Le code de vérification a été refusé.',
                'preuve' => $preuve,
            ], 422);
        }

        return $this->resultat([
            'identifiant' => $resultat,
            'preuve' => $preuve,
        ], 200, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }

    public function renvoyer(array $donnees, string $produit, ?string $correlation): array
    {
        try {
            $registre = new IdentifiantsResolution(IdentiteMagasin::connecter());
            $nouveau = $registre->renvoyerVerification(
                (string) $donnees['identifiant_reference'],
                (string) $donnees['destination'],
                $produit,
                [
                    'source' => 'CAP-CORE — renvoi vérification Compte GAMAD',
                    'preuve' => is_string($correlation) && $correlation !== '' ? $correlation : 'RENVOI-COMPTE-GAMAD',
                ],
            );
        } catch (\Throwable) {
            return $this->resultat([
                'erreur' => 'RENVOI_INDISPONIBLE',
                'message' => 'Un nouveau code ne peut pas être émis pour le moment.',
            ], 503);
        }

        if (isset($nouveau['refus'])) {
            $statut = in_array((string) $nouveau['refus'], ['RENVOI_TROP_RAPIDE', 'LIMITE_RENVOI_ATTEINTE'], true) ? 429 : 422;
            return $this->resultat([
                'erreur' => (string) $nouveau['refus'],
                'message' => 'Le renvoi du code a été refusé.',
                'reessayer_dans' => $nouveau['reessayer_dans'] ?? null,
            ], $statut);
        }

        $livraison = $this->livrer->executer(
            (string) $nouveau['type'],
            (string) $donnees['destination'],
            (string) $nouveau['code'],
            (string) $nouveau['expire_le'],
            [
                'produit' => $produit,
                'identifiant_reference' => (string) $donnees['identifiant_reference'],
                'verification_reference' => (string) $nouveau['reference'],
                'correlation' => $correlation,
            ],
        );

        if (($livraison['livree'] ?? false) !== true) {
            try {
                $registre->annulerVerification((string) $nouveau['reference']);
            } catch (\Throwable) {
            }
            return $this->resultat([
                'erreur' => 'LIVRAISON_VERIFICATION_ECHOUEE',
                'message' => 'Le nouveau code n’a pas pu être livré.',
                'canal' => $nouveau['type'],
            ], 503);
        }

        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'IDENTITE',
                'type' => 'RENVOI_VERIFICATION_COMPTE',
                'acteur' => $produit,
                'action' => 'renvoyer un code de vérification de Compte GAMAD',
                'ressource' => (string) $donnees['identifiant_reference'],
                'decision' => 'EXECUTEE',
                'correlation_id' => $correlation,
                'donnees' => [
                    'verification_reference' => (string) $nouveau['reference'],
                    'canal' => (string) $nouveau['type'],
                ],
            ]);
        } catch (\Throwable) {
            try {
                $registre->annulerVerification((string) $nouveau['reference']);
            } catch (\Throwable) {
            }
            return $this->resultat([
                'erreur' => 'JOURNAL_INDISPONIBLE',
                'message' => 'Le renvoi n’est pas conservé sans preuve opérationnelle.',
            ], 503);
        }

        return $this->resultat([
            'verification' => [
                'reference' => (string) $nouveau['reference'],
                'expire_le' => (string) $nouveau['expire_le'],
                'canal' => (string) $nouveau['type'],
                'livree' => true,
            ],
            'preuve' => $preuve,
        ], 201, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }

    private function resultat(array $corps, int $statut, array $entetes = []): array
    {
        return compact('corps', 'statut', 'entetes');
    }
}
