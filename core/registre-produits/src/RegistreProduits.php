<?php

declare(strict_types=1);

namespace Gamad\RegistreProduits;

use Gamad\EvenementsSortants\OutboxProducteur;
use Gamad\EvenementsSortants\SchemaOutbox;
use Gamad\RegistreIdentites\Ctr01;

/**
 * Registre opérationnel des produits (CAP-CORE-011).
 *
 * Un produit du Core n'est plus un simple constat dérivé de l'index
 * documentaire : il possède une fiche persistante, un cycle de vie en ajout
 * seul, des environnements versionnés, et une fédérabilité qui ne dépend plus
 * d'un marqueur textuel libre.
 *
 * Ce module possède la fiche opérationnelle du produit — pas ses comptes
 * locaux, pas ses abonnements, pas ses secrets. Il ne décide rien lui-même :
 * la décision d'autoriser une commande vient de CAP-CORE-004, dans la couche
 * applicative ; ce module conserve seulement ses propres bornes, pour rester
 * sûr même si une politique est mal écrite ailleurs.
 */
final class RegistreProduits
{
    public const CAPACITE = 'CAP-CORE-011';

    public function __construct(
        private \PDO $index,
        private \PDO $registreIdentites,
        private \PDO $magasin,
        private ?Ctr01 $identites = null,
    ) {
        $this->identites ??= new Ctr01($index, $registreIdentites);
        SchemaProduits::migrer($this->magasin);
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreProduit(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM produit WHERE reference = ?');
        $st->execute([$reference]);
        $p = $st->fetch();
        if ($p === false) {
            return null;
        }

        return $this->projeter($p);
    }

    /**
     * @param array{etat?:string,type_produit?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public function listerProduits(array $filtres = []): array
    {
        $sql = 'SELECT * FROM produit';
        $conditions = [];
        $args = [];
        if (isset($filtres['type_produit'])) {
            $conditions[] = 'type_produit = ?';
            $args[] = $filtres['type_produit'];
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        $lignes = array_map(fn (array $p): array => $this->projeter($p), $st->fetchAll());
        if (isset($filtres['etat'])) {
            $lignes = array_values(array_filter(
                $lignes,
                static fn (array $l): bool => $l['etat'] === $filtres['etat'],
            ));
        }

        return $lignes;
    }

    /** @return array<string,mixed>|null */
    public function resoudreEtat(string $reference, ?string $date = null): ?array
    {
        $cycle = $this->dernierCycle($reference, $date);

        return $cycle === null ? null : [
            'reference' => $reference,
            'etat' => $cycle['etat'],
            'date_effet' => $cycle['date_effet'],
            'motif' => $cycle['motif'],
            'acteur_reference' => $cycle['acteur_reference'],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function resoudreHistorique(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM produit_cycle WHERE produit_reference = ? ORDER BY date_effet, id'
        );
        $st->execute([$reference]);

        return array_values($st->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function listerProduitsActifs(): array
    {
        return array_values(array_filter(
            $this->listerProduits(),
            static fn (array $p): bool => $p['etat'] === 'ACTIF',
        ));
    }

    /**
     * Catalogue fédérable : produits existants, ACTIF, dont la fédération est
     * explicitement autorisée. La correspondance d'audience et d'environnement
     * précis restent du ressort de `verifierUtilisablePourFederation()`.
     *
     * @return list<array<string,mixed>>
     */
    public function listerProduitsFederables(): array
    {
        return array_values(array_filter(
            $this->listerProduits(),
            static fn (array $p): bool => $p['etat'] === 'ACTIF' && $p['federation_autorisee'] === true,
        ));
    }

    /** @return list<array<string,mixed>> */
    public function resoudreEnvironnements(string $reference): array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM produit_environnement WHERE produit_reference = ?
             ORDER BY environnement, date_debut, id'
        );
        $st->execute([$reference]);

        return array_map(
            fn (array $e): array => $this->projeterEnvironnement($e),
            $st->fetchAll(),
        );
    }

    /** @return array<string,mixed>|null */
    public function resoudreEnvironnementActif(string $reference, string $environnement): ?array
    {
        $st = $this->magasin->prepare(
            'SELECT * FROM produit_environnement
             WHERE produit_reference = ? AND environnement = ? AND actif = 1
             ORDER BY date_debut DESC, id DESC LIMIT 1'
        );
        $st->execute([$reference, $environnement]);
        $e = $st->fetch();

        return $e === false ? null : $this->projeterEnvironnement($e);
    }

    /**
     * Le produit, s'il existe, dont un environnement actif porte cette
     * audience. Une audience n'appartient jamais à deux produits actifs.
     *
     * @return array<string,mixed>|null
     */
    public function verifierAudience(string $audience): ?array
    {
        $st = $this->magasin->prepare(
            'SELECT produit_reference, environnement FROM produit_environnement
             WHERE audience_federation = ? AND actif = 1
             ORDER BY date_debut DESC, id DESC LIMIT 1'
        );
        $st->execute([$audience]);
        $e = $st->fetch();

        return $e === false ? null : [
            'audience' => $audience,
            'produit' => $e['produit_reference'],
            'environnement' => $e['environnement'],
        ];
    }

    /**
     * Un produit n'est utilisable par CAP-CORE-022 que si les trois
     * conditions du registre sont réunies : il existe, il est ACTIF, et sa
     * fédération est explicitement autorisée. La correspondance d'environnement
     * et d'audience, quand `$environnement` est fourni, s'y ajoute.
     *
     * @return array{utilisable:bool,motif:string,produit?:array<string,mixed>}
     */
    public function verifierUtilisablePourFederation(
        string $reference,
        ?string $environnement = null,
    ): array {
        $produit = $this->resoudreProduit($reference);
        if ($produit === null) {
            return ['utilisable' => false, 'motif' => 'PRODUIT_INCONNU'];
        }
        if ($produit['etat'] !== 'ACTIF') {
            return ['utilisable' => false, 'motif' => 'PRODUIT_NON_ACTIF', 'produit' => $produit];
        }
        if ($produit['federation_autorisee'] !== true) {
            return ['utilisable' => false, 'motif' => 'FEDERATION_NON_AUTORISEE', 'produit' => $produit];
        }
        if ($environnement !== null) {
            $env = $this->resoudreEnvironnementActif($reference, $environnement);
            if ($env === null) {
                return ['utilisable' => false, 'motif' => 'ENVIRONNEMENT_INACTIF', 'produit' => $produit];
            }
        }

        return ['utilisable' => true, 'motif' => 'SATISFAITE', 'produit' => $produit];
    }

    // ------------------------------------------------------------------
    // Commandes gouvernées

    /**
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function inscrireProduit(array $dossier): array
    {
        $controle = $this->controlerInscription($dossier);
        if (isset($controle['refus'])) {
            return $controle;
        }

        $reference = trim((string) $dossier['reference']);
        $identite = trim((string) $dossier['identite_reference']);
        $nomCanonique = trim((string) $dossier['nom_canonique']);
        $nomAffichage = trim((string) $dossier['nom_affichage']);
        $type = (string) $dossier['type_produit'];
        $proprietaire = trim((string) $dossier['proprietaire_reference']);
        $source = (string) $dossier['source'];
        $producteur = (string) $dossier['producteur'];
        $politique = (string) $dossier['politique'];
        $preuve = (string) $dossier['preuve'];
        $date = (string) ($dossier['date'] ?? date('Y-m-d'));

        return $this->transaction(function () use (
            $reference, $identite, $nomCanonique, $nomAffichage, $type,
            $proprietaire, $source, $producteur, $politique, $preuve, $date,
        ): array {
            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO produit
                 (reference,identite_reference,nom_canonique,nom_affichage,type_produit,
                  proprietaire_reference,source_reference,federation_autorisee,
                  politique_inscription,producteur,preuve_reference,cree_le,modifie_le)
                 VALUES(?,?,?,?,?,?,?,0,?,?,?,?,?)'
            )->execute([
                $reference, $identite, $nomCanonique, $nomAffichage, $type,
                $proprietaire, $source, $politique, $producteur, $preuve,
                $maintenant, $maintenant,
            ]);
            $this->inscrireCycle($reference, 'PREPARATION', $date, null, $producteur, $preuve, null);

            return [
                'reference' => $reference,
                'identite_reference' => $identite,
                'etat' => 'PREPARATION',
                'federation_autorisee' => false,
            ];
        });
    }

    /**
     * Seules les métadonnées non immuables changent : jamais la référence, ni
     * l'identité canonique associée.
     *
     * @param array<string,mixed> $dossier
     * @return array<string,mixed>
     */
    public function modifierProduit(string $reference, array $dossier): array
    {
        if (isset($dossier['reference']) || isset($dossier['identite_reference'])) {
            return $this->refus('CHAMP_IMMUABLE', 'la référence et l’identité canonique ne se modifient jamais');
        }
        $produit = $this->ligneProduit($reference);
        if ($produit === null) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $champs = [];
        $valeurs = [];
        if (isset($dossier['nom_canonique'])) {
            $champs[] = 'nom_canonique = ?';
            $valeurs[] = trim((string) $dossier['nom_canonique']);
        }
        if (isset($dossier['nom_affichage'])) {
            $champs[] = 'nom_affichage = ?';
            $valeurs[] = trim((string) $dossier['nom_affichage']);
        }
        if (isset($dossier['type_produit'])) {
            if (!in_array($dossier['type_produit'], PolitiqueProduits::TYPES_PRODUIT, true)) {
                return $this->refus('TYPE_INCONNU', 'type_produit hors liste close');
            }
            $champs[] = 'type_produit = ?';
            $valeurs[] = (string) $dossier['type_produit'];
        }
        if (isset($dossier['proprietaire_reference'])) {
            $champs[] = 'proprietaire_reference = ?';
            $valeurs[] = trim((string) $dossier['proprietaire_reference']);
        }
        if (isset($dossier['federation_autorisee'])) {
            $champs[] = 'federation_autorisee = ?';
            $valeurs[] = ((bool) $dossier['federation_autorisee']) ? 1 : 0;
        }
        if ($champs === []) {
            return $this->refus('DOSSIER_VIDE', 'aucune métadonnée modifiable fournie');
        }

        $champs[] = 'modifie_le = ?';
        $valeurs[] = gmdate('c');
        $valeurs[] = $reference;

        return $this->transaction(function () use ($champs, $valeurs, $reference): array {
            $this->magasin->prepare(
                'UPDATE produit SET ' . implode(', ', $champs) . ' WHERE reference = ?'
            )->execute($valeurs);

            return $this->resoudreProduit($reference) ?? [];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerProduit(string $reference, array $dossier): array
    {
        $produit = $this->ligneProduit($reference);
        if ($produit === null) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        $producteur = (string) $dossier['producteur'];
        if ($producteur === $reference) {
            return $this->refus('AUTO_ACTIVATION_INTERDITE', 'un produit ne peut jamais s’auto-activer');
        }
        if (trim((string) $produit['proprietaire_reference']) === '') {
            return $this->refus('PROPRIETAIRE_ABSENT', 'aucun propriétaire déclaré');
        }
        if (trim((string) $produit['source_reference']) === '') {
            return $this->refus('SOURCE_ABSENTE', 'aucune source déclarée');
        }

        $cycle = $this->dernierCycle($reference);
        $etat = $cycle['etat'] ?? 'PREPARATION';
        if ($etat === 'ACTIF') {
            // Idempotent : rejouer une activation déjà acquise ne produit
            // aucune seconde ligne de cycle.
            return ['reference' => $reference, 'etat' => 'ACTIF', 'idempotent' => true];
        }
        if (!in_array($etat, PolitiqueProduits::ETATS_ACTIVABLES, true)) {
            return $this->refus('ETAT_INCOMPATIBLE', "un produit `{$etat}` ne s’active pas directement");
        }
        // Un environnement de production actif n'est pas exigé ici : aucune
        // URL n'est inventée pour un satellite qui n'en a pas encore déclaré.
        // `declarerEnvironnement()` reste la seule source de cette donnée, et
        // `verifierUtilisablePourFederation($ref, $environnement)` la vérifie
        // pour l'appelant qui la requiert.

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use ($reference, $date, $motif, $producteur, $preuve, $dossier): array {
            $this->inscrireCycle(
                $reference, 'ACTIF', $date, $motif, $producteur, $preuve,
                $this->nullable($dossier['correlation_id'] ?? null),
            );
            $this->toucher($reference);
            $this->publierEvenementCycle($reference, 'PRODUIT_ACTIVE', 'ACTIF', $dossier);

            return ['reference' => $reference, 'etat' => 'ACTIF', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreProduit(string $reference, array $dossier): array
    {
        $produit = $this->ligneProduit($reference);
        if ($produit === null) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $cycle = $this->dernierCycle($reference);
        $etat = $cycle['etat'] ?? 'PREPARATION';
        if ($etat === 'SUSPENDU') {
            return ['reference' => $reference, 'etat' => 'SUSPENDU', 'idempotent' => true];
        }
        if ($etat !== 'ACTIF') {
            return $this->refus('ETAT_INCOMPATIBLE', "seul un produit ACTIF se suspend (état actuel `{$etat}`)");
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use ($reference, $date, $motif, $producteur, $preuve, $dossier): array {
            $this->inscrireCycle(
                $reference, 'SUSPENDU', $date, $motif, $producteur, $preuve,
                $this->nullable($dossier['correlation_id'] ?? null),
            );
            $this->toucher($reference);
            $this->publierEvenementCycle($reference, 'PRODUIT_SUSPENDU', 'SUSPENDU', $dossier);

            return ['reference' => $reference, 'etat' => 'SUSPENDU', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerProduit(string $reference, array $dossier): array
    {
        $produit = $this->ligneProduit($reference);
        if ($produit === null) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $cycle = $this->dernierCycle($reference);
        $etat = $cycle['etat'] ?? 'PREPARATION';
        if ($etat === 'RETIRE') {
            // Retrait irréversible : rejouer la commande ne crée pas de
            // seconde ligne et ne réutilise jamais la référence pour autre chose.
            return ['reference' => $reference, 'etat' => 'RETIRE', 'idempotent' => true];
        }

        $date = (string) ($dossier['date'] ?? date('Y-m-d'));
        $motif = $this->nullable($dossier['motif'] ?? null);
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use ($reference, $date, $motif, $producteur, $preuve, $dossier): array {
            $this->inscrireCycle(
                $reference, 'RETIRE', $date, $motif, $producteur, $preuve,
                $this->nullable($dossier['correlation_id'] ?? null),
            );
            $this->toucher($reference);
            $this->publierEvenementCycle($reference, 'PRODUIT_RETIRE', 'RETIRE', $dossier);

            return ['reference' => $reference, 'etat' => 'RETIRE', 'idempotent' => false];
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function declarerEnvironnement(string $reference, array $dossier): array
    {
        $produit = $this->ligneProduit($reference);
        if ($produit === null) {
            return $this->refus('PRODUIT_INCONNU', "produit `{$reference}` inconnu");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }

        $environnement = (string) ($dossier['environnement'] ?? '');
        if (!in_array($environnement, PolitiqueProduits::ENVIRONNEMENTS, true)) {
            return $this->refus('ENVIRONNEMENT_INCONNU', 'environnement hors liste close');
        }
        $apiBaseUrl = trim((string) ($dossier['api_base_url'] ?? ''));
        $healthUrl = $this->nullable($dossier['health_url'] ?? null);
        $audience = trim((string) ($dossier['audience_federation'] ?? ''));
        if ($apiBaseUrl === '' || $audience === '') {
            return $this->refus('DOSSIER_INCOMPLET', 'api_base_url et audience_federation sont obligatoires');
        }
        if (!$this->urlValide($apiBaseUrl, $environnement === 'PRODUCTION')) {
            return $this->refus(
                'URL_INVALIDE',
                $environnement === 'PRODUCTION'
                    ? 'api_base_url doit être une URL HTTPS en production'
                    : 'api_base_url doit être une URL valide',
            );
        }
        if ($healthUrl !== null && !$this->urlValide($healthUrl, $environnement === 'PRODUCTION')) {
            return $this->refus(
                'URL_INVALIDE',
                $environnement === 'PRODUCTION'
                    ? 'health_url doit être une URL HTTPS en production'
                    : 'health_url doit être une URL valide',
            );
        }

        $audienceOccupee = $this->verifierAudience($audience);
        if ($audienceOccupee !== null && $audienceOccupee['produit'] !== $reference) {
            return $this->refus(
                'AUDIENCE_DEJA_UTILISEE',
                "l’audience `{$audience}` appartient déjà au produit actif `{$audienceOccupee['produit']}`",
            );
        }

        $date = (string) ($dossier['date_debut'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_debut doit suivre YYYY-MM-DD');
        }
        $source = (string) $dossier['source'];
        $producteur = (string) $dossier['producteur'];
        $preuve = (string) $dossier['preuve'];

        return $this->transaction(function () use (
            $reference, $environnement, $apiBaseUrl, $healthUrl, $audience,
            $date, $source, $producteur, $preuve,
        ): array {
            // Une URL modifiée clôt l'ancienne version active du même
            // environnement plutôt que de la réécrire.
            $precedent = $this->magasin->prepare(
                'SELECT id FROM produit_environnement
                 WHERE produit_reference = ? AND environnement = ? AND actif = 1'
            );
            $precedent->execute([$reference, $environnement]);
            foreach ($precedent->fetchAll() as $ligne) {
                $this->magasin->prepare(
                    'UPDATE produit_environnement SET actif = 0, date_fin = ? WHERE id = ?'
                )->execute([$date, $ligne['id']]);
            }

            $maintenant = gmdate('c');
            $this->magasin->prepare(
                'INSERT INTO produit_environnement
                 (produit_reference,environnement,api_base_url,health_url,audience_federation,
                  actif,date_debut,date_fin,source_reference,producteur,preuve_reference,cree_le)
                 VALUES(?,?,?,?,?,1,?,NULL,?,?,?,?)'
            )->execute([
                $reference, $environnement, $apiBaseUrl, $healthUrl, $audience,
                $date, $source, $producteur, $preuve, $maintenant,
            ]);
            $id = (int) $this->magasin->lastInsertId();
            $this->toucher($reference);

            return $this->projeterEnvironnement($this->ligneEnvironnement($id) ?? []);
        });
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function fermerEnvironnement(string $reference, int $id, array $dossier): array
    {
        $ligne = $this->ligneEnvironnement($id);
        if ($ligne === null || (string) $ligne['produit_reference'] !== $reference) {
            return $this->refus('ENVIRONNEMENT_INCONNU', "environnement `{$id}` inconnu pour `{$reference}`");
        }
        $g = $this->controlerGouvernance($dossier);
        if (isset($g['refus'])) {
            return $g;
        }
        if ((int) $ligne['actif'] === 0) {
            return ['id' => $id, 'reference' => $reference, 'actif' => false, 'idempotent' => true];
        }

        $date = (string) ($dossier['date_fin'] ?? date('Y-m-d'));
        if (!$this->dateValide($date)) {
            return $this->refus('DATE_INVALIDE', 'date_fin doit suivre YYYY-MM-DD');
        }

        return $this->transaction(function () use ($id, $date, $reference): array {
            $this->magasin->prepare(
                'UPDATE produit_environnement SET actif = 0, date_fin = ? WHERE id = ?'
            )->execute([$date, $id]);
            $this->toucher($reference);

            return ['id' => $id, 'reference' => $reference, 'actif' => false, 'idempotent' => false];
        });
    }

    // ------------------------------------------------------------------
    // Internes

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    private function controlerInscription(array $dossier): array
    {
        foreach ([
            'reference', 'identite_reference', 'nom_canonique', 'nom_affichage',
            'type_produit', 'proprietaire_reference', 'source', 'producteur',
            'politique', 'preuve',
        ] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('DOSSIER_INCOMPLET', "champ `{$champ}` absent");
            }
        }
        $reference = trim((string) $dossier['reference']);
        $identite = trim((string) $dossier['identite_reference']);
        $type = (string) $dossier['type_produit'];

        if (!in_array($type, PolitiqueProduits::TYPES_PRODUIT, true)) {
            return $this->refus('TYPE_INCONNU', 'type_produit hors liste close');
        }
        if ($this->ligneProduit($reference) !== null) {
            return $this->refus('REFERENCE_DEJA_UTILISEE', "la référence `{$reference}` est déjà inscrite");
        }
        $existante = $this->magasin->prepare('SELECT reference FROM produit WHERE identite_reference = ?');
        $existante->execute([$identite]);
        $autreReference = $existante->fetchColumn();
        if ($autreReference !== false) {
            return $this->refus(
                'IDENTITE_DEJA_LIEE',
                "l’identité `{$identite}` est déjà attachée au produit `{$autreReference}`",
            );
        }
        $identiteResolue = $this->identites->resoudreIdentite($identite);
        if ($identiteResolue === null) {
            return $this->refus('IDENTITE_INCONNUE', "l’identité canonique `{$identite}` n’existe pas");
        }
        if (($identiteResolue['type'] ?? null) !== 'produit') {
            return $this->refus('IDENTITE_TYPE_INVALIDE', "l’identité `{$identite}` n’est pas de type produit");
        }
        if (isset($dossier['date']) && !$this->dateValide((string) $dossier['date'])) {
            return $this->refus('DATE_INVALIDE', 'date doit suivre YYYY-MM-DD');
        }

        return ['valide' => true];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    private function controlerGouvernance(array $dossier): array
    {
        foreach (['politique', 'producteur', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('COMMANDE_NON_GOUVERNEE', "champ `{$champ}` absent");
            }
        }

        return ['valide' => true];
    }

    /** @return array<string,mixed>|null */
    private function ligneProduit(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM produit WHERE reference = ?');
        $st->execute([$reference]);
        $p = $st->fetch();

        return $p === false ? null : $p;
    }

    /** @return array<string,mixed>|null */
    private function ligneEnvironnement(int $id): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM produit_environnement WHERE id = ?');
        $st->execute([$id]);
        $e = $st->fetch();

        return $e === false ? null : $e;
    }

    /** @return array<string,mixed>|null */
    private function dernierCycle(string $reference, ?string $date = null): ?array
    {
        $sql = 'SELECT * FROM produit_cycle WHERE produit_reference = ?';
        $args = [$reference];
        if ($date !== null) {
            $sql .= ' AND date_effet <= ?';
            $args[] = $date;
        }
        $sql .= ' ORDER BY date_effet DESC, id DESC LIMIT 1';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);
        $c = $st->fetch();

        return $c === false ? null : $c;
    }

    private function inscrireCycle(
        string $reference,
        string $etat,
        string $date,
        ?string $motif,
        string $acteur,
        string $preuve,
        ?string $correlation,
    ): void {
        if (!in_array($etat, PolitiqueProduits::ETATS_CYCLE, true)) {
            throw new \LogicException("état `{$etat}` hors liste close");
        }
        $this->magasin->prepare(
            'INSERT INTO produit_cycle
             (produit_reference,etat,date_effet,motif,acteur_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([$reference, $etat, $date, $motif, $acteur, $preuve, $correlation, gmdate('c')]);
    }

    private function toucher(string $reference): void
    {
        $this->magasin->prepare('UPDATE produit SET modifie_le = ? WHERE reference = ?')
            ->execute([gmdate('c'), $reference]);
    }

    /**
     * Prépare, dans la même transaction que la mutation métier, un événement
     * commun de cycle de vie produit destiné à CAP-CORE-014
     * (`core/evenements-sortants`).
     *
     * Facultatif et explicite : n'agit que si l'appelant fournit
     * `realm_reference` dans le dossier de gouvernance — CAP-CORE-011 ne
     * décide pas lui-même dans quel realm un produit publie ses faits. En
     * l'absence de cette information, aucun événement n'est préparé et aucun
     * comportement existant n'est modifié (partie 3 §3 : « pour les
     * événements non critiques, le caractère obligatoire ou facultatif doit
     * être explicitement défini » — ici, facultatif par absence de realm).
     * Une fois le realm fourni, la préparation devient un invariant de cet
     * appel précis : si elle échoue, toute la transaction est annulée.
     *
     * @param array<string,mixed> $dossier
     */
    private function publierEvenementCycle(string $reference, string $typeEvenement, string $nouvelEtat, array $dossier): void
    {
        $realm = $this->nullable($dossier['realm_reference'] ?? null);
        if ($realm === null) {
            return;
        }

        $suffixeContrat = match ($nouvelEtat) {
            'ACTIF' => 'ACTIVE',
            default => $nouvelEtat,
        };

        SchemaOutbox::migrer($this->magasin);
        OutboxProducteur::preparerEvenement($this->magasin, [
            'type_evenement' => $typeEvenement,
            'contrat_reference' => 'EVT-GAMAD-PRODUIT-' . $suffixeContrat,
            'contrat_version' => (string) ($dossier['contrat_version_evenement'] ?? '1.0.0'),
            'producteur_capacite_reference' => self::CAPACITE,
            'source_reference' => (string) ($dossier['source_evenement'] ?? PolitiqueProduits::SOURCE_EVENEMENTS_REFERENCE),
            'realm_reference' => $realm,
            'finalite_reference' => (string) ($dossier['finalite_evenement'] ?? PolitiqueProduits::FINALITE_EVENEMENTS_DEFAUT),
            'sujet_type' => 'PRODUIT',
            'sujet_reference' => $reference,
            'correlation_id' => (string) ($dossier['correlation_id'] ?? ('COR-GAMAD-' . strtoupper(bin2hex(random_bytes(8))))),
            'causation_reference' => $this->nullable($dossier['preuve'] ?? null),
            'survenu_le' => gmdate('c'),
            'classification' => (string) ($dossier['classification_evenement'] ?? 'INTERNE'),
            'idempotence_reference' => 'IDEMP-GAMAD-' . strtoupper(bin2hex(random_bytes(12))),
            'charge' => ['produit_reference' => $reference, 'nouvel_etat' => $nouvelEtat],
        ]);
    }

    /** @param array<string,mixed> $p @return array<string,mixed> */
    private function projeter(array $p): array
    {
        $cycle = $this->dernierCycle((string) $p['reference']);

        return [
            'reference' => $p['reference'],
            'identite_reference' => $p['identite_reference'],
            'nom_canonique' => $p['nom_canonique'],
            'nom_affichage' => $p['nom_affichage'],
            'type_produit' => $p['type_produit'],
            'proprietaire_reference' => $p['proprietaire_reference'],
            'source_reference' => $p['source_reference'],
            'federation_autorisee' => (bool) $p['federation_autorisee'],
            'etat' => $cycle['etat'] ?? 'PREPARATION',
            'depuis' => $cycle['date_effet'] ?? null,
            'cree_le' => $p['cree_le'],
            'modifie_le' => $p['modifie_le'],
        ];
    }

    /** @param array<string,mixed> $e @return array<string,mixed> */
    private function projeterEnvironnement(array $e): array
    {
        return [
            'id' => (int) $e['id'],
            'produit_reference' => $e['produit_reference'],
            'environnement' => $e['environnement'],
            'api_base_url' => $e['api_base_url'],
            'health_url' => $e['health_url'],
            'audience_federation' => $e['audience_federation'],
            'actif' => (bool) $e['actif'],
            'date_debut' => $e['date_debut'],
            'date_fin' => $e['date_fin'],
        ];
    }

    private function urlValide(string $url, bool $httpsObligatoire): bool
    {
        $p = parse_url($url);
        if ($p === false || !isset($p['scheme'], $p['host']) || $p['host'] === '') {
            return false;
        }
        if (!in_array($p['scheme'], ['http', 'https'], true)) {
            return false;
        }

        return !$httpsObligatoire || $p['scheme'] === 'https';
    }

    private function dateValide(string $date): bool
    {
        $valeur = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $valeur !== false && $valeur->format('Y-m-d') === $date;
    }

    private function nullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    /** @return array<string,mixed> */
    private function refus(string $motif, string $detail): array
    {
        return ['refus' => $motif, 'detail' => $detail];
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function transaction(callable $operation): mixed
    {
        $propre = !$this->magasin->inTransaction();
        if ($propre) {
            $this->magasin->beginTransaction();
        }
        try {
            $resultat = $operation();
            if ($propre) {
                $this->magasin->commit();
            }

            return $resultat;
        } catch (\Throwable $e) {
            if ($propre && $this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }
}
