<?php

declare(strict_types=1);

namespace App\Application\Acces;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;

/**
 * Moyens d'accès personnels (CAP-CORE-005).
 *
 * Chacun gère les siens, et rien que les siens : ni l'autorité ni un satellite
 * n'attache un facteur à autrui. Trois opérations, toutes décidées par
 * CAP-CORE-004 et prouvées par CAP-CORE-013.
 *
 * La règle héritée de la ligne de commande est conservée : posséder un mot de
 * passe ne suffit pas pour attacher un facteur fort. Elle est seulement rendue
 * praticable — une session déjà forte, ou un code de secours consommé, tient
 * lieu d'autorisation hors navigateur.
 */
final class MoyensAcces
{
    public const ACTION_CODES = 'engendrer des codes de secours pour soi-même';
    public const ACTION_FACTEUR_FORT = 'attacher un facteur fort à son identité';
    public const ACTION_RETRAIT = 'retirer un moyen d’accès de son identité';

    /** Niveau d'assurance qui dispense d'un code de secours. */
    public const ASSURANCE_FORTE = 'A2';

    /**
     * Inventaire lisible des moyens d'accès d'une entité. Aucune empreinte,
     * aucun secret : seulement de quoi décider quoi retirer.
     *
     * @return array<string,mixed>
     */
    public function inventaire(string $entite): array
    {
        try {
            $ctr16 = new Ctr16(AccesMagasin::connecter());
            $atteste = $ctr16->attester($entite);
            $passkeys = [];
            foreach ($ctr16->passkeysActives($entite) as $passkey) {
                $passkeys[(string) $passkey['authentificateur_ref']] = $passkey['libelle'];
            }
        } catch (\Throwable) {
            return ['disponible' => false, 'moyens' => [], 'codes_restants' => 0];
        }

        $moyens = [];
        foreach ($atteste['authentificateurs'] as $moyen) {
            if ($moyen['etat'] !== 'ACTIF' || $moyen['type'] === Ctr16::TYPE_CODE_SECOURS) {
                continue;
            }
            $moyens[] = [
                'reference' => $moyen['reference'],
                'type' => $moyen['type'],
                'libelle' => $passkeys[(string) $moyen['reference']] ?? null,
                'assurance' => $moyen['niveau_assurance'],
                'cree_le' => $moyen['cree_le'],
                'fort' => $moyen['type'] === 'passkey_webauthn',
            ];
        }

        return [
            'disponible' => true,
            'entite' => $entite,
            'moyens' => $moyens,
            'codes_restants' => $ctr16->codesSecoursRestants($entite),
            'sessions_actives' => $atteste['sessions_actives'],
        ];
    }

    /**
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function engendrerCodes(string $entite, string $acteur, ?string $correlation = null): array
    {
        $decision = $this->decider(self::ACTION_CODES, $entite, $acteur, $correlation);
        if ($decision['statut'] !== 200) {
            return $decision;
        }

        try {
            $codes = (new Ctr16(AccesMagasin::connecter()))->engendrerCodesSecours($entite);
        } catch (\Throwable) {
            return $this->erreur(503, 'MAGASIN_ACCES_INDISPONIBLE',
                'Les codes n’ont pas pu être engendrés.', $decision['preuve']);
        }

        // Aucun code n'entre au journal : seul leur nombre y figure.
        $this->tracer([
            'categorie' => 'SECURITE',
            'type' => 'CODES_SECOURS_ENGENDRES',
            'acteur' => $acteur,
            'action' => self::ACTION_CODES,
            'ressource' => $entite,
            'decision' => 'EXECUTEE',
            'correlation_id' => $decision['preuve']['correlation_id'] ?? null,
            'donnees' => ['nombre' => count($codes)],
        ]);

        return ['statut' => 201, 'corps' => ['codes' => $codes, 'preuve' => $decision['preuve']]];
    }

    /**
     * Autorise l'attachement d'un facteur fort et remet le jeton d'enrôlement.
     *
     * Une session déjà forte suffit — on ajoute un second appareil. Sinon, un
     * code de secours doit être consommé : c'est ce qui remplace l'autorisation
     * hors navigateur sans rendre le mot de passe suffisant à lui seul.
     *
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function autoriserFacteurFort(
        string $entite,
        string $acteur,
        string $assuranceSession,
        #[\SensitiveParameter] string $codeSecours = '',
        ?string $correlation = null,
    ): array {
        $decision = $this->decider(self::ACTION_FACTEUR_FORT, $entite, $acteur, $correlation);
        if ($decision['statut'] !== 200) {
            return $decision;
        }

        try {
            $ctr16 = new Ctr16(AccesMagasin::connecter());
            $sessionForte = str_contains($assuranceSession, self::ASSURANCE_FORTE);
            $parCode = false;
            if (! $sessionForte) {
                if (trim($codeSecours) === '') {
                    return $this->erreur(422, 'AUTORISATION_INSUFFISANTE',
                        'Un mot de passe seul n’attache pas un facteur fort. '
                        .'Saisissez un code de secours, ou ouvrez d’abord une session par passkey.',
                        $decision['preuve']);
                }
                $parCode = $ctr16->consommerCodeSecours($entite, trim($codeSecours));
                if (! $parCode) {
                    return $this->erreur(422, 'CODE_SECOURS_REFUSE',
                        'Ce code de secours est inconnu, déjà utilisé ou révoqué.',
                        $decision['preuve']);
                }
            }

            $autorisation = $ctr16->preparerEnrolementPasskey($entite);
        } catch (\Throwable) {
            return $this->erreur(503, 'MAGASIN_ACCES_INDISPONIBLE',
                'L’autorisation d’enrôlement n’a pas pu être produite.', $decision['preuve']);
        }

        $this->tracer([
            'categorie' => 'SECURITE',
            'type' => 'ENROLEMENT_FACTEUR_FORT_AUTORISE',
            'acteur' => $acteur,
            'action' => self::ACTION_FACTEUR_FORT,
            'ressource' => $entite,
            'decision' => 'AUTORISEE',
            'correlation_id' => $decision['preuve']['correlation_id'] ?? null,
            'donnees' => [
                'voie' => $sessionForte ? 'session forte' : 'code de secours',
                'autorisation' => $autorisation['reference'],
            ],
        ]);

        return [
            'statut' => 201,
            'corps' => [
                'jeton' => $autorisation['jeton'],
                'expire_le' => $autorisation['expire_le'],
                'voie' => $sessionForte ? 'session forte' : 'code de secours',
                'preuve' => $decision['preuve'],
            ],
        ];
    }

    /**
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function retirer(
        string $entite,
        string $reference,
        string $acteur,
        ?string $correlation = null,
    ): array {
        $decision = $this->decider(self::ACTION_RETRAIT, $entite, $acteur, $correlation);
        if ($decision['statut'] !== 200) {
            return $decision;
        }

        try {
            $resultat = (new Ctr16(AccesMagasin::connecter()))->revoquerMoyenAcces($entite, $reference);
        } catch (\Throwable) {
            return $this->erreur(503, 'MAGASIN_ACCES_INDISPONIBLE',
                'Le retrait n’a pas pu être confirmé.', $decision['preuve']);
        }

        if (isset($resultat['refus'])) {
            return [
                'statut' => 422,
                'corps' => [
                    'erreur' => 'RETRAIT_REFUSE',
                    'resultat' => $resultat,
                    'message' => (string) $resultat['detail'],
                    'preuve' => $decision['preuve'],
                ],
            ];
        }

        $this->tracer([
            'categorie' => 'SECURITE',
            'type' => 'MOYEN_ACCES_RETIRE',
            'acteur' => $acteur,
            'action' => self::ACTION_RETRAIT,
            'ressource' => $entite,
            'decision' => 'EXECUTEE',
            'correlation_id' => $decision['preuve']['correlation_id'] ?? null,
            'donnees' => ['moyen' => $reference],
        ]);

        return ['statut' => 200, 'corps' => ['retrait' => $resultat, 'preuve' => $decision['preuve']]];
    }

    // ------------------------------------------------------------------

    /**
     * @return array{statut:int,corps:array<string,mixed>,preuve?:array<string,mixed>}
     */
    private function decider(
        string $action,
        string $entite,
        string $acteur,
        ?string $correlation,
    ): array {
        // Chacun gère les siens. Aucune décision, aucune qualité ne permet
        // d'attacher ou de retirer un moyen d'accès pour autrui.
        if ($entite !== $acteur) {
            return [
                'statut' => 403,
                'corps' => [
                    'erreur' => 'ACTEUR_INCOMPETENT',
                    'message' => 'Un moyen d’accès ne se gère que pour soi-même.',
                ],
            ];
        }

        try {
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, $action, $entite);
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'SECURITE',
                'type' => 'DECISION_MOYEN_ACCES',
                'acteur' => $acteur,
                'action' => $action,
                'ressource' => $entite,
                'decision' => $decision['decision'] === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'],
                'correlation_id' => $correlation,
                'donnees' => ['politique' => $decision['politique']],
            ]);
        } catch (\Throwable) {
            return [
                'statut' => 503,
                'corps' => [
                    'erreur' => 'SOCLE_INDISPONIBLE',
                    'message' => 'L’opération est fermée car sa décision et sa preuve '
                        .'ne peuvent pas être établies.',
                ],
            ];
        }

        if ($decision['decision'] !== 'PERMIS') {
            return [
                'statut' => 403,
                'corps' => ['erreur' => 'AUTORISATION_REFUSEE', 'decision' => $decision, 'preuve' => $preuve],
            ];
        }

        return ['statut' => 200, 'corps' => [], 'preuve' => $preuve];
    }

    /**
     * @param  array<string,mixed>|null  $preuve
     * @return array{statut:int,corps:array<string,mixed>}
     */
    private function erreur(int $statut, string $code, string $message, ?array $preuve): array
    {
        return [
            'statut' => $statut,
            'corps' => ['erreur' => $code, 'message' => $message]
                + ($preuve === null ? [] : ['preuve' => $preuve]),
        ];
    }

    /** @param array<string,mixed> $evenement */
    private function tracer(array $evenement): void
    {
        try {
            (new Journal(JournalMagasin::connecter()))->enregistrer($evenement);
        } catch (\Throwable) {
            // Le fait est accompli ; la preuve amont est déjà chaînée.
        }
    }
}
