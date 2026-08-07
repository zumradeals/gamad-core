<?php

declare(strict_types=1);

namespace App\Application\Comptes;

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;

/**
 * Création gouvernée d'un Compte GAMAD pour une personne.
 *
 * L'appelant est obligatoirement un sujet Core déjà authentifié (typiquement
 * un produit reconnu). Le navigateur public n'appelle jamais ce cas d'usage
 * directement.
 *
 * Cette commande possède sa propre permission : `créer un Compte GAMAD`.
 * Elle ne délègue jamais au produit la permission générique d'inscrire une
 * identité. Le type créé reste fermé à `personne` et le canal à
 * `PRODUIT_RECONNU`.
 *
 * Les registres identité et accès pouvant être physiquement séparés, cette
 * orchestration ne prétend pas fournir une transaction SQL distribuée. Elle
 * précontrôle les collisions et reste fail-closed : aucune session n'est
 * rendue tant que toutes les briques ne sont pas établies.
 */
final class CreerCompteGamad
{
    public const ACTION = 'créer un Compte GAMAD';

    /**
     * @param array{nom:string,type_identifiant:string,identifiant:string,mot_de_passe:string} $donnees
     * @return array{statut:int,corps:array<string,mixed>}
     */
    public function executer(array $donnees, string $produit, ?string $correlation = null): array
    {
        $type = strtoupper(trim($donnees['type_identifiant']));
        $identifiant = trim($donnees['identifiant']);
        $nom = trim($donnees['nom']);
        $motDePasse = $donnees['mot_de_passe'];

        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $resolution = new IdentifiantsResolution($registreIdentites);
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser(
                $produit,
                self::ACTION,
                'personne',
            );
            $journal = new Journal(JournalMagasin::connecter());
        } catch (\Throwable) {
            return $this->indisponible('SOCLE_INDISPONIBLE');
        }

        try {
            $preuveDecision = $journal->enregistrer([
                'categorie' => 'AUTORISATION',
                'type' => 'DECISION_CREATION_COMPTE_GAMAD',
                'acteur' => $produit,
                'action' => self::ACTION,
                'ressource' => 'personne',
                'decision' => ($decision['decision'] ?? null) === 'PERMIS' ? 'PERMIS' : 'REFUSE',
                'motif' => $decision['motif'] ?? null,
                'correlation_id' => $correlation,
                'donnees' => [
                    'type_identifiant' => $type,
                    'politique' => $decision['politique'] ?? null,
                    'version' => $decision['version'] ?? null,
                ],
            ]);
        } catch (\Throwable) {
            return $this->indisponible('JOURNAL_INDISPONIBLE');
        }

        if (($decision['decision'] ?? null) !== 'PERMIS') {
            return [
                'statut' => 403,
                'corps' => [
                    'erreur' => 'CREATION_COMPTE_NON_AUTORISEE',
                    'message' => 'Ce produit n’est pas habilité à créer des Comptes GAMAD.',
                    'preuve' => $preuveDecision,
                ],
            ];
        }

        if ($resolution->resoudre($identifiant, $type) !== null) {
            // Ne jamais révéler si l'identifiant correspond à un compte actif.
            return [
                'statut' => 409,
                'corps' => [
                    'erreur' => 'COMPTE_NON_CREATABLE',
                    'message' => 'Ce moyen de connexion ne peut pas être utilisé pour créer un nouveau compte.',
                    'preuve' => $preuveDecision,
                ],
            ];
        }

        try {
            $identite = $ctr01->inscrireIdentite([
                'canal' => 'PRODUIT_RECONNU',
                'type' => 'personne',
                'libelle' => $nom,
                'producteur' => $produit,
                'politique' => (string) ($decision['politique'] ?? 'POL-COMPTES-GAMAD-V1'),
                'source' => (string) ($decision['source'] ?? 'CAP-CORE — Compte GAMAD'),
                'preuve' => (string) $preuveDecision['reference'],
                'classification' => 'CONFIDENTIEL',
                'provisoire' => false,
                'date' => gmdate('Y-m-d'),
            ]);
        } catch (\Throwable) {
            return $this->indisponible('REGISTRE_IDENTITES_INDISPONIBLE');
        }

        if (isset($identite['refus'])) {
            return [
                'statut' => 422,
                'corps' => [
                    'erreur' => 'INSCRIPTION_IDENTITE_REFUSEE',
                    'message' => 'Le Core a refusé la création de l’identité canonique.',
                    'resultat' => $identite,
                    'preuve' => $preuveDecision,
                ],
            ];
        }

        $reference = (string) ($identite['reference'] ?? '');
        if ($reference === '') {
            return $this->indisponible('INSCRIPTION_INCOMPLETE');
        }

        try {
            $identifiantLie = $resolution->attacher($reference, $type, $identifiant, [
                'verifie' => false,
                'source' => (string) ($decision['source'] ?? 'CAP-CORE — Compte GAMAD'),
                'preuve' => (string) $preuveDecision['reference'],
                'producteur' => $produit,
                'classification' => 'CONFIDENTIEL',
            ]);
        } catch (\Throwable) {
            return $this->compensationRequise($reference, 'LIAISON_IDENTIFIANT_INDISPONIBLE', $correlation, $produit);
        }

        if (isset($identifiantLie['refus'])) {
            return $this->compensationRequise($reference, 'LIAISON_IDENTIFIANT_REFUSEE', $correlation, $produit);
        }

        try {
            $acces = new Ctr16(AccesMagasin::connecter());
            $authn = $acces->inscrireAuthentificateur(
                $reference,
                $motDePasse,
                'mot_de_passe',
                'AS1 — FACTEUR UNIQUE',
            );
            $session = $acces->etablirSession($reference, $motDePasse);
        } catch (\InvalidArgumentException) {
            return $this->compensationRequise($reference, 'SECRET_NON_CONFORME', $correlation, $produit);
        } catch (\Throwable) {
            return $this->compensationRequise($reference, 'REGISTRE_ACCES_INDISPONIBLE', $correlation, $produit);
        }

        if ($session === null) {
            return $this->compensationRequise($reference, 'SESSION_INITIALE_REFUSEE', $correlation, $produit);
        }

        try {
            $preuve = $journal->enregistrer([
                'categorie' => 'IDENTITE',
                'type' => 'COMPTE_GAMAD_CREE',
                'acteur' => $reference,
                'action' => self::ACTION,
                'ressource' => $produit,
                'decision' => 'EXECUTEE',
                'correlation_id' => $correlation,
                'donnees' => [
                    'type_identifiant' => $type,
                    'identifiant_reference' => $identifiantLie['reference'] ?? null,
                    'authentificateur_reference' => $authn,
                    'assurance' => $session['assurance'] ?? null,
                ],
            ]);
        } catch (\Throwable) {
            // Une session sans preuve opérationnelle n'est jamais livrée.
            try {
                $acces->revoquerSession((string) $session['session']);
            } catch (\Throwable) {
                // Le refus reste fermé même si la révocation doit être reprise par l'exploitation.
            }
            return $this->compensationRequise($reference, 'JOURNAL_INDISPONIBLE', $correlation, $produit);
        }

        return [
            'statut' => 201,
            'corps' => [
                'compte' => [
                    'identite' => $reference,
                    'type_identifiant' => $type,
                    'identifiant_reference' => $identifiantLie['reference'] ?? null,
                    'authentificateur_reference' => $authn,
                    'assurance' => $session['assurance'],
                ],
                'session' => [
                    'type' => 'Bearer',
                    'jeton' => $session['session'],
                    'entite' => $session['entite'],
                    'assurance' => $session['assurance'],
                    'expire_le' => $session['expire_le'],
                ],
                'preuve' => $preuve,
            ],
        ];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    private function indisponible(string $code): array
    {
        return [
            'statut' => 503,
            'corps' => [
                'erreur' => $code,
                'message' => 'Le compte n’a pas été ouvert. Réessayez plus tard.',
            ],
        ];
    }

    /** @return array{statut:int,corps:array<string,mixed>} */
    private function compensationRequise(
        string $reference,
        string $motif,
        ?string $correlation,
        string $produit,
    ): array {
        try {
            (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'IDENTITE',
                'type' => 'CREATION_COMPTE_INCOMPLETE',
                'acteur' => $produit,
                'action' => 'compenser une création de Compte GAMAD incomplète',
                'ressource' => $reference,
                'decision' => 'A_REPRENDRE',
                'motif' => $motif,
                'correlation_id' => $correlation,
            ]);
        } catch (\Throwable) {
            // L'erreur primaire reste prioritaire ; aucune session n'est rendue.
        }

        return [
            'statut' => 503,
            'corps' => [
                'erreur' => 'CREATION_COMPTE_INCOMPLETE',
                'message' => 'Le compte n’a pas été ouvert. Une reprise gouvernée est requise.',
                'reference_reprise' => $reference,
            ],
        ];
    }
}
