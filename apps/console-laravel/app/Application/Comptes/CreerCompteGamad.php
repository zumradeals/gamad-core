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
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;

/**
 * Création gouvernée d'un Compte GAMAD pour une personne.
 *
 * Deux portes seulement peuvent créer un compte :
 * - un produit explicitement autorisé par CAP-CORE-004 (ex. le PORTAIL) ;
 * - tout produit réellement inscrit comme SATELLITE et ACTIF dans CAP-CORE-011.
 *
 * Le statut SATELLITE n'est donc jamais autodéclaré par l'appelant. Il vient
 * du registre souverain des produits. Cette habilitation automatique reste
 * bornée à la création d'un Compte GAMAD ; elle ne donne aucun droit général
 * d'écriture dans les autres registres du Core.
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
            $registreProduits = new RegistreProduits(
                $index,
                $registreIdentites,
                ProduitsMagasin::connecter(),
                $ctr01,
            );
            $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser(
                $produit,
                self::ACTION,
                'personne',
            );

            if (($decision['decision'] ?? null) !== 'PERMIS') {
                $ficheProduit = $registreProduits->resoudreProduit($produit);
                if (is_array($ficheProduit)
                    && ($ficheProduit['type_produit'] ?? null) === 'SATELLITE'
                    && ($ficheProduit['etat'] ?? null) === 'ACTIF') {
                    $decision = [
                        'decision' => 'PERMIS',
                        'sujet' => $produit,
                        'action' => self::ACTION,
                        'ressource' => 'personne',
                        'motif' => 'Tout SATELLITE ACTIF reconnu par CAP-CORE-011 peut créer un Compte GAMAD.',
                        'politique' => 'POL-COMPTES-GAMAD-V1',
                        'version' => '1.0.0',
                        'source' => 'CAP-CORE-011 — statut SATELLITE ACTIF',
                        'habilitation' => 'SATELLITE_ACTIF',
                    ];
                }
            }
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
                    'habilitation' => $decision['habilitation'] ?? 'POLITIQUE_EXPLICITE',
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
                    'message' => 'Seul un produit explicitement habilité ou un SATELLITE ACTIF reconnu peut créer un Compte GAMAD.',
                    'preuve' => $preuveDecision,
                ],
            ];
        }

        if ($resolution->resoudre($identifiant, $type) !== null) {
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
                'verifie' => $type === 'USERNAME',
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
        } catch (\InvalidArgumentException) {
            return $this->compensationRequise($reference, 'SECRET_NON_CONFORME', $correlation, $produit);
        } catch (\Throwable) {
            return $this->compensationRequise($reference, 'REGISTRE_ACCES_INDISPONIBLE', $correlation, $produit);
        }

        $verification = null;
        $session = null;
        if (in_array($type, ['EMAIL', 'TELEPHONE'], true)) {
            try {
                $verification = $resolution->demarrerVerification(
                    (string) $identifiantLie['reference'],
                    [
                        'source' => (string) ($decision['source'] ?? 'CAP-CORE — Compte GAMAD'),
                        'preuve' => (string) $preuveDecision['reference'],
                        'producteur' => $produit,
                    ],
                );
            } catch (\Throwable) {
                return $this->compensationRequise($reference, 'VERIFICATION_INDISPONIBLE', $correlation, $produit);
            }
            if (isset($verification['refus'])) {
                return $this->compensationRequise($reference, 'VERIFICATION_REFUSEE', $correlation, $produit);
            }
        } else {
            $session = $acces->etablirSession($reference, $motDePasse);
            if ($session === null) {
                return $this->compensationRequise($reference, 'SESSION_INITIALE_REFUSEE', $correlation, $produit);
            }
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
                    'verification_reference' => $verification['reference'] ?? null,
                    'verification_requise' => $verification !== null,
                ],
            ]);
        } catch (\Throwable) {
            if (is_array($session) && isset($session['session'])) {
                try {
                    $acces->revoquerSession((string) $session['session']);
                } catch (\Throwable) {
                }
            }
            return $this->compensationRequise($reference, 'JOURNAL_INDISPONIBLE', $correlation, $produit);
        }

        $corps = [
            'compte' => [
                'identite' => $reference,
                'type_identifiant' => $type,
                'identifiant_reference' => $identifiantLie['reference'] ?? null,
                'authentificateur_reference' => $authn,
                'verification_requise' => $verification !== null,
            ],
            'preuve' => $preuve,
        ];

        if (is_array($verification)) {
            // Le code n'est montré qu'une fois au produit authentifié. Le
            // produit doit le remettre au canal externe et ne pas l'exposer
            // dans ses journaux ni dans une URL.
            $corps['verification'] = [
                'reference' => $verification['reference'],
                'code' => $verification['code'],
                'expire_le' => $verification['expire_le'],
            ];
        }
        if (is_array($session)) {
            $corps['session'] = [
                'type' => 'Bearer',
                'jeton' => $session['session'],
                'entite' => $session['entite'],
                'assurance' => $session['assurance'],
                'expire_le' => $session['expire_le'],
            ];
        }

        return ['statut' => 201, 'corps' => $corps];
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
