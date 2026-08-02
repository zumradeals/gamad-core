<?php

declare(strict_types=1);

namespace Gamad\RegistreFederation;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreProduits\RegistreProduits;

/**
 * Fédération des satellites (CAP-CORE-022).
 *
 * Le Core sait authentifier une personne ; ce module lui apprend à l'OUVRIR
 * sur un produit. Il ne crée aucun compte métier : il établit un lien produit
 * minimal (CAP-CORE-001) et remet au satellite un jeton borné qui ne vaut que
 * pour lui.
 *
 * Quatre bornes gouvernent chaque jeton (documentation, §4 de
 * `docs/02-compte-gamad-et-federation.md`) :
 *
 *   · une audience unique — un jeton GamaDrive est inutilisable par Wasplex ;
 *   · une durée courte, jamais reconductible ;
 *   · des portées explicites, prises dans une liste close ;
 *   · un rattachement à la session Core et au niveau d'assurance qui l'a émis.
 *
 * Le module n'est pas une autorité : il n'écrit aucune règle et ne décide pas
 * lui-même de l'ouverture. CAP-CORE-004 décide en amont, dans la couche
 * applicative, et sa preuve est exigée ici sous forme de `preuve`.
 */
final class Federation
{
    public const CAPACITE = 'CAP-CORE-022';

    private Ctr01 $identites;
    private RegistreProduits $produits;

    /**
     * @param \PDO $index           index technique reconstructible (entités, états) ;
     * @param \PDO $registre        registre persistant des identités et des liens ;
     * @param \PDO $magasinAcces    magasin d'exploitation portant sessions et jetons ;
     * @param \PDO $magasinProduits registre persistant des produits (CAP-CORE-011).
     */
    public function __construct(
        private \PDO $index,
        private \PDO $registre,
        private \PDO $magasinAcces,
        private \PDO $magasinProduits,
        ?Ctr01 $identites = null,
        ?RegistreProduits $produits = null,
    ) {
        $this->identites = $identites ?? new Ctr01($index, $registre);
        $this->produits = $produits ?? new RegistreProduits($index, $registre, $magasinProduits, $this->identites);
        SchemaFederation::migrer($magasinAcces);
    }

    // ------------------------------------------------------------------
    // Lectures

    /**
     * Catalogue des produits connus du Core, lu depuis le registre persistant
     * CAP-CORE-011 — plus depuis un marqueur de texte libre dans l'index.
     *
     * `federable` constate trois faits du registre : le produit existe, son
     * cycle courant est `ACTIF`, et sa fédération est explicitement autorisée
     * (`federation_autorisee`). Un partenaire externe non entériné reste
     * listé, et reste fermé.
     *
     * @return list<array<string,mixed>>
     */
    public function catalogueProduits(): array
    {
        return array_values(array_map(
            static fn (array $produit): array => [
                'reference' => $produit['reference'],
                'libelle' => $produit['nom_affichage'],
                'etat' => $produit['etat'],
                'federable' => $produit['etat'] === 'ACTIF' && $produit['federation_autorisee'] === true,
                'regime' => 'INSCRIT_AU_REGISTRE_PRODUITS',
            ],
            $this->produits->listerProduits(),
        ));
    }

    /**
     * Vue Portail des accès d'une identité : ce que le §6 de la documentation
     * autorise à afficher, et rien de plus. Aucune donnée métier du satellite
     * n'est lue ni restituée.
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreAcces(string $identite, ?string $date = null): array
    {
        $lignes = [];
        foreach ($this->catalogueProduits() as $produit) {
            $reference = (string) $produit['reference'];
            $liens = array_values(array_filter(
                $this->identites->resoudreLiensProduits($identite, $reference, null, $date),
                static fn (array $lien): bool => $lien['etat'] === 'ACTIVE',
            ));
            $lien = $liens[0] ?? null;

            $st = $this->magasinAcces->prepare(
                'SELECT max(emis_le) FROM jeton_federe
                 WHERE identite_reference = ? AND produit_reference = ?'
            );
            $st->execute([$identite, $reference]);
            $derniere = $st->fetchColumn();

            $lignes[] = [
                'produit' => $reference,
                'libelle' => $produit['libelle'],
                'federable' => $produit['federable'],
                'active' => $lien !== null,
                'relation' => $lien['reference'] ?? null,
                'niveau_acces' => $lien['relation_type'] ?? null,
                'assurance' => $lien['assurance'] ?? null,
                'derniere_ouverture' => $derniere === false ? null : $derniere,
            ];
        }

        return $lignes;
    }

    /**
     * Porteurs d'un accès actif à un satellite — la vue transversale que
     * CAP-CORE-001 refuse délibérément de servir à un produit.
     *
     * Elle est donc bornée ici, et par le code : seuls le satellite lui-même et
     * l'autorité d'inscription l'obtiennent. Un satellite qui ouvrirait une
     * session Core ne lit jamais la liste des porteurs d'un autre satellite.
     *
     * @return array<string,mixed> porteurs, ou un refus motivé
     */
    public function resoudrePorteurs(string $produit, string $acteur, ?string $date = null): array
    {
        if ($acteur !== $produit && $acteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus(
                'ACTEUR_INCOMPETENT',
                'la liste des porteurs n’est lisible que par le satellite concerné ou par l’autorité',
            );
        }

        $st = $this->registre->prepare(
            'SELECT DISTINCT identite_reference FROM relation_produit
             WHERE produit_reference = ? ORDER BY identite_reference'
        );
        $st->execute([$produit]);

        $porteurs = [];
        foreach ($st->fetchAll() as $ligne) {
            $identite = (string) $ligne['identite_reference'];
            $lien = $this->lienActif($identite, $produit, $date);
            if ($lien === null) {
                continue;
            }

            $derniere = $this->magasinAcces->prepare(
                'SELECT max(emis_le) FROM jeton_federe
                 WHERE identite_reference = ? AND produit_reference = ?'
            );
            $derniere->execute([$identite, $produit]);
            $ouverture = $derniere->fetchColumn();
            $resolue = $this->identites->resoudreIdentite($identite);

            $porteurs[] = [
                'identite' => $identite,
                'libelle' => $resolue['libelle'] ?? $identite,
                'etat_identite' => $resolue['etat'] ?? null,
                'relation' => $lien['reference'],
                'niveau_acces' => $lien['relation_type'],
                'assurance' => $lien['assurance'],
                'depuis' => $lien['date_debut'],
                'derniere_ouverture' => $ouverture === false ? null : $ouverture,
            ];
        }

        usort($porteurs, static fn (array $a, array $b): int =>
            [$a['libelle'], $a['identite']] <=> [$b['libelle'], $b['identite']]
        );

        return ['produit' => $produit, 'porteurs' => $porteurs];
    }

    // ------------------------------------------------------------------
    // Ouverture

    /**
     * Ouvre un satellite pour une identité : provisionnement idempotent du
     * lien produit, puis émission d'un jeton borné.
     *
     * Le jeton en clair n'est retourné qu'une fois et n'est jamais persisté.
     *
     * @param  array<string,mixed>  $dossier  politique, source, preuve,
     *                                        session_empreinte, et facultatifs
     *                                        relation_type, sujet_local_opaque,
     *                                        duree, correlation_id.
     * @return array<string,mixed>
     */
    public function ouvrir(
        string $identite,
        string $produit,
        string $acteur,
        array $dossier,
    ): array {
        foreach (['politique', 'preuve', 'session_empreinte'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('COMMANDE_NON_GOUVERNEE', "champ `{$champ}` absent");
            }
        }

        // Un porteur ouvre son propre accès ; l'autorité d'inscription peut
        // ouvrir pour autrui. Aucun autre sujet ne le peut, quelle que soit la
        // décision rendue en amont.
        if ($acteur !== $identite && $acteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus(
                'ACTEUR_INCOMPETENT',
                'un accès fédéré s’ouvre pour soi-même ou par l’autorité d’inscription',
            );
        }

        $catalogue = $this->produit($produit);
        if ($catalogue === null) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$produit}` inconnu du Core");
        }
        if ($catalogue['federable'] !== true) {
            return $this->refus(
                'PRODUIT_NON_RECONNU',
                "l’état du produit `{$produit}` ne vaut pas reconnaissance",
            );
        }

        $utilisable = $this->identites->resoudreEtatUtilisable(
            $identite,
            PolitiqueFederation::FINALITE,
        );
        if ($utilisable['utilisable'] !== true) {
            return $this->refus(
                'IDENTITE_NON_UTILISABLE',
                implode(' ; ', $utilisable['exigences_non_satisfaites'] ?? []),
            );
        }

        $duree = (int) ($dossier['duree'] ?? PolitiqueFederation::DUREE_JETON);
        if ($duree < PolitiqueFederation::DUREE_MINIMALE
            || $duree > PolitiqueFederation::DUREE_MAXIMALE) {
            return $this->refus(
                'DUREE_HORS_LIMITES',
                sprintf(
                    'durée hors bornes (%d à %d secondes)',
                    PolitiqueFederation::DUREE_MINIMALE,
                    PolitiqueFederation::DUREE_MAXIMALE,
                ),
            );
        }

        $relationType = (string) ($dossier['relation_type'] ?? PolitiqueFederation::RELATION_PAR_DEFAUT);
        $lien = $this->lienActif($identite, $produit);
        $provisionne = false;

        // Idempotence : rejouer l'ouverture ne crée jamais un second compte
        // local pour la même relation (documentation, §3).
        if ($lien === null) {
            $rattachement = $this->identites->rattacherProduit(
                $identite,
                $produit,
                $relationType,
                [
                    'politique' => $dossier['politique'],
                    'producteur' => $produit,
                    'source' => $dossier['source'] ?? PolitiqueFederation::SOURCE,
                    'preuve' => $dossier['preuve'],
                    'sujet_local_opaque' => $dossier['sujet_local_opaque'] ?? null,
                    'classification' => $dossier['classification'] ?? 'INTERNE',
                ],
            );
            if (isset($rattachement['refus'])) {
                return $rattachement;
            }
            $provisionne = true;
            $lien = [
                'reference' => (string) $rattachement['reference'],
                'relation_type' => (string) $rattachement['relation_type'],
                'assurance' => (string) $rattachement['assurance'],
            ];
        }

        $jeton = 'FED-' . strtoupper(bin2hex(random_bytes(24)));
        $reference = 'JFD-' . strtoupper(bin2hex(random_bytes(12)));
        $emis = date('c');
        $expire = date('c', time() + $duree);

        $this->magasinAcces->prepare(
            'INSERT INTO jeton_federe
             (reference,jeton_empreinte,produit_reference,identite_reference,
              relation_reference,relation_type,portees,niveau_assurance,
              session_empreinte,correlation_id,preuve_reference,emis_le,expire_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $reference,
            $this->empreinte($jeton),
            $produit,
            $identite,
            $lien['reference'],
            $lien['relation_type'],
            json_encode(PolitiqueFederation::PORTEES, JSON_THROW_ON_ERROR),
            $lien['assurance'],
            (string) $dossier['session_empreinte'],
            $this->nullable($dossier['correlation_id'] ?? null),
            (string) $dossier['preuve'],
            $emis,
            $expire,
        ]);

        return [
            'reference' => $reference,
            'jeton' => $jeton,
            'audience' => $produit,
            'identite' => $identite,
            'relation' => $lien['reference'],
            'relation_type' => $lien['relation_type'],
            'portees' => PolitiqueFederation::PORTEES,
            'assurance' => $lien['assurance'],
            'emis_le' => $emis,
            'expire_le' => $expire,
            'provisionne' => $provisionne,
        ];
    }

    // ------------------------------------------------------------------
    // Vérification

    /**
     * Vérifie un jeton présenté par un satellite, et le consomme.
     *
     * Le jeton est à usage unique : il s'échange contre une session locale, et
     * une réponse invalide ne le rend pas rejouable. Toutes les conditions sont
     * revérifiées au moment de la présentation — audience, expiration,
     * révocation, session Core et lien produit — car aucune d'elles n'est
     * acquise par le seul fait de l'émission.
     *
     * @return array<string,mixed>
     */
    public function verifierJeton(
        string $jeton,
        string $produitAppelant,
        ?string $instant = null,
    ): array {
        $maintenant = $instant ?? date('c');

        $this->magasinAcces->beginTransaction();
        try {
            $st = $this->magasinAcces->prepare(
                'SELECT * FROM jeton_federe WHERE jeton_empreinte = ?'
            );
            $st->execute([$this->empreinte($jeton)]);
            $ligne = $st->fetch();

            if ($ligne === false) {
                return $this->fermer('JETON_INCONNU', 'aucun jeton fédéré ne correspond');
            }
            // L'audience est vérifiée avant tout le reste : un satellite ne doit
            // même pas apprendre l'état d'un jeton qui ne lui est pas destiné.
            if (!hash_equals((string) $ligne['produit_reference'], $produitAppelant)) {
                return $this->fermer(
                    'AUDIENCE_ETRANGERE',
                    'ce jeton est destiné à un autre satellite',
                );
            }
            if ($ligne['revoque_le'] !== null) {
                return $this->fermer('JETON_REVOQUE', (string) $ligne['motif_revocation']);
            }
            if ($ligne['consomme_le'] !== null) {
                return $this->fermer('JETON_DEJA_CONSOMME', 'un jeton fédéré ne sert qu’une fois');
            }
            if ($maintenant >= (string) $ligne['expire_le']) {
                return $this->fermer('JETON_EXPIRE', 'la durée du jeton est écoulée');
            }

            $session = $this->magasinAcces->prepare(
                'SELECT s.revoquee_le, s.expire_le, a.etat
                 FROM session_ouverte s
                 JOIN authentificateur a ON a.reference = s.authentificateur_ref
                 WHERE s.jeton_empreinte = ?'
            );
            $session->execute([$ligne['session_empreinte']]);
            $ouverte = $session->fetch();
            if ($ouverte === false
                || $ouverte['revoquee_le'] !== null
                || $ouverte['etat'] !== 'ACTIF'
                || $maintenant >= (string) $ouverte['expire_le']) {
                return $this->fermer(
                    'SESSION_CORE_FERMEE',
                    'la session Core qui a produit ce jeton n’est plus valide',
                );
            }

            $lien = $this->lienActif(
                (string) $ligne['identite_reference'],
                (string) $ligne['produit_reference'],
                substr($maintenant, 0, 10),
            );
            if ($lien === null || $lien['reference'] !== $ligne['relation_reference']) {
                return $this->fermer(
                    'LIEN_PRODUIT_FERME',
                    'le lien produit est clos, suspendu ou remplacé',
                );
            }

            $consommer = $this->magasinAcces->prepare(
                'UPDATE jeton_federe SET consomme_le = ?
                 WHERE reference = ? AND consomme_le IS NULL AND revoque_le IS NULL'
            );
            $consommer->execute([$maintenant, $ligne['reference']]);
            if ($consommer->rowCount() !== 1) {
                return $this->fermer('JETON_DEJA_CONSOMME', 'un jeton fédéré ne sert qu’une fois');
            }
            $this->magasinAcces->commit();

            return [
                'valide' => true,
                'motif' => null,
                'reference' => $ligne['reference'],
                'audience' => $ligne['produit_reference'],
                'identite' => $ligne['identite_reference'],
                'relation' => $ligne['relation_reference'],
                'relation_type' => $ligne['relation_type'],
                'portees' => json_decode((string) $ligne['portees'], true, flags: JSON_THROW_ON_ERROR),
                'assurance' => $ligne['niveau_assurance'],
                'consomme_le' => $maintenant,
            ];
        } catch (\Throwable $e) {
            if ($this->magasinAcces->inTransaction()) {
                $this->magasinAcces->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Révocation

    /**
     * Désactive l'accès d'une identité à un satellite : le lien produit est
     * clos par CAP-CORE-001, et les jetons encore ouverts sont fermés.
     *
     * Clore un accès ne supprime ni l'identité GAMAD ni les données que le
     * satellite détient légalement (documentation, §2).
     *
     * @param  array<string,mixed>  $dossier  politique, source, preuve.
     * @return array<string,mixed>
     */
    public function revoquerAcces(
        string $identite,
        string $produit,
        string $acteur,
        array $dossier,
    ): array {
        if ($acteur !== $identite
            && $acteur !== $produit
            && $acteur !== PolitiqueInscription::AUTORITE_INSCRIPTION) {
            return $this->refus(
                'ACTEUR_INCOMPETENT',
                'seuls le porteur, le satellite concerné ou l’autorité révoquent un accès',
            );
        }

        $lien = $this->lienActif($identite, $produit);
        if ($lien === null) {
            return $this->refus('ACCES_INEXISTANT', 'aucun lien produit actif à révoquer');
        }

        $cloture = $this->identites->cloreRelationProduit(
            (string) $lien['reference'],
            (string) ($dossier['date_fin'] ?? date('Y-m-d')),
            [
                'politique' => $dossier['politique'] ?? PolitiqueFederation::POLITIQUE,
                'producteur' => $produit,
                'source' => $dossier['source'] ?? PolitiqueFederation::SOURCE,
                'preuve' => $dossier['preuve'] ?? '',
            ],
        );
        if (isset($cloture['refus'])) {
            return $cloture;
        }

        $fermes = $this->revoquerJetons(
            'relation_reference = ?',
            [$lien['reference']],
            'accès produit révoqué',
        );

        return [
            'identite' => $identite,
            'produit' => $produit,
            'relation' => $lien['reference'],
            'relation_etat' => $cloture['relation_etat'],
            'date_fin' => $cloture['date_fin'],
            'jetons_fermes' => $fermes,
        ];
    }

    /**
     * Ferme les jetons fédérés produits par une session Core.
     *
     * La jointure de `verifierJeton` suffirait à les rendre inopérants ; cette
     * écriture rend en outre la fermeture lisible dans l'état du magasin, ce
     * qu'une déconnexion globale doit pouvoir montrer.
     */
    public function revoquerJetonsDeSession(string $sessionEmpreinte, string $motif): int
    {
        return self::fermerJetonsDeSession($this->magasinAcces, $sessionEmpreinte, $motif);
    }

    /**
     * Même fermeture, appelable sans câbler l'index ni le registre des
     * identités : la déconnexion globale ne doit dépendre que du magasin
     * d'accès, sans quoi une indisponibilité de l'index laisserait des jetons
     * ouverts.
     */
    public static function fermerJetonsDeSession(
        \PDO $magasinAcces,
        string $sessionEmpreinte,
        string $motif,
    ): int {
        if (!SchemaFederation::presente($magasinAcces)) {
            return 0;
        }

        $st = $magasinAcces->prepare(
            'UPDATE jeton_federe SET revoque_le = ?, motif_revocation = ?
             WHERE session_empreinte = ? AND revoque_le IS NULL AND consomme_le IS NULL'
        );
        $st->execute([date('c'), $motif, $sessionEmpreinte]);

        return $st->rowCount();
    }

    /**
     * Ferme tous les jetons fédérés encore ouverts d'un produit.
     *
     * CAP-CORE-011 appelle ceci lorsqu'un produit est suspendu ou retiré : la
     * fermeture de la fédérabilité au registre ne referme pas, à elle seule,
     * les jetons déjà émis — `verifierJeton()` les refuserait à leur prochaine
     * présentation, mais un accès encore actif ne doit pas attendre cette
     * prochaine présentation pour être fermé.
     */
    public static function revoquerJetonsDuProduit(
        \PDO $magasinAcces,
        string $produit,
        string $motif,
    ): int {
        if (!SchemaFederation::presente($magasinAcces)) {
            return 0;
        }

        $st = $magasinAcces->prepare(
            'UPDATE jeton_federe SET revoque_le = ?, motif_revocation = ?
             WHERE produit_reference = ? AND revoque_le IS NULL AND consomme_le IS NULL'
        );
        $st->execute([date('c'), $motif, $produit]);

        return $st->rowCount();
    }

    public static function empreinteSession(#[\SensitiveParameter] string $session): string
    {
        return hash('sha256', $session);
    }

    // ------------------------------------------------------------------
    // Internes

    /** @return array<string,mixed>|null */
    private function produit(string $reference): ?array
    {
        foreach ($this->catalogueProduits() as $produit) {
            if ($produit['reference'] === $reference) {
                return $produit;
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function lienActif(string $identite, string $produit, ?string $date = null): ?array
    {
        foreach ($this->identites->resoudreLiensProduits($identite, $produit, null, $date) as $lien) {
            if ($lien['etat'] === 'ACTIVE') {
                return $lien;
            }
        }

        return null;
    }

    /** @param list<mixed> $arguments */
    private function revoquerJetons(string $condition, array $arguments, string $motif): int
    {
        $st = $this->magasinAcces->prepare(
            "UPDATE jeton_federe SET revoque_le = ?, motif_revocation = ?
             WHERE {$condition} AND revoque_le IS NULL AND consomme_le IS NULL"
        );
        $st->execute([date('c'), $motif, ...$arguments]);

        return $st->rowCount();
    }

    /** @return array<string,mixed> */
    private function fermer(string $motif, string $detail): array
    {
        if ($this->magasinAcces->inTransaction()) {
            $this->magasinAcces->rollBack();
        }

        return [
            'valide' => false,
            'motif' => $motif,
            'detail' => $detail,
        ];
    }

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
    }

    private function empreinte(#[\SensitiveParameter] string $jeton): string
    {
        return hash('sha256', $jeton);
    }

    private function nullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }
}
