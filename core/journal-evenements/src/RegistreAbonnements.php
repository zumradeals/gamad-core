<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreRealms\RegistreRealms;

/**
 * Cycle de vie et filtres fermés des abonnements (CAP-CORE-014).
 *
 * Aucun abonnement universel : la création démarre toujours en
 * `PREPARATION`, sans type, producteur ni realm implicite. L'activation
 * exige au moins un type, un producteur et un realm explicitement déclarés
 * (partie 3 §7.5). Un abonnement ne peut jamais élargir le contrat qu'il
 * consomme : le consommateur doit être déclaré `CONSOMMATEUR` de la version
 * active du contrat référencé par chaque type ajouté.
 */
final class RegistreAbonnements
{
    public function __construct(
        private \PDO $magasin,
        private RegistreContrats $contrats,
        private RegistreRealms $realms,
    ) {
        SchemaEvenements::migrer($this->magasin);
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function creerAbonnement(array $dossier): array
    {
        foreach (['nom', 'realm_reference', 'finalite_reference', 'mode_livraison', 'acteur', 'politique', 'source', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('CHAMP_MANQUANT', "champ obligatoire absent : {$champ}");
            }
        }
        $consommateurCapacite = $this->nullable($dossier['consommateur_capacite_reference'] ?? null);
        $consommateurProduit = $this->nullable($dossier['consommateur_produit_reference'] ?? null);
        $consommateur = $consommateurCapacite ?? $consommateurProduit;
        if ($consommateur === null) {
            return $this->refus('CONSOMMATEUR_ABSENT', 'aucun consommateur déclaré (capacité ou produit)');
        }
        if ($consommateurCapacite !== null && $consommateurProduit !== null) {
            return $this->refus('CONSOMMATEUR_AMBIGU', 'un seul consommateur principal est autorisé');
        }
        if (!in_array((string) $dossier['mode_livraison'], PolitiqueEvenements::MODES_LIVRAISON, true)) {
            return $this->refus('MODE_INCONNU', 'mode de livraison hors liste close');
        }
        $etatRealm = $this->realms->resoudreEtat((string) $dossier['realm_reference']);
        if ($etatRealm === null || $etatRealm['etat'] !== 'ACTIF') {
            return $this->refus('REALM_INACTIF', 'realm non actif');
        }

        $reference = (string) ($dossier['reference'] ?? ('ABN-GAMAD-' . strtoupper(bin2hex(random_bytes(10)))));
        $existe = $this->magasin->prepare('SELECT 1 FROM abonnement_evenement WHERE reference = ?');
        $existe->execute([$reference]);
        if ($existe->fetchColumn() !== false) {
            return $this->refus('REFERENCE_DEJA_UTILISEE', "référence `{$reference}` déjà utilisée");
        }

        $creeLe = gmdate('c');
        $tailleLotMax = min((int) ($dossier['taille_lot_max'] ?? 50), PolitiqueEvenements::TAILLE_LOT_MAX);
        $dureeBail = min((int) ($dossier['duree_bail_secondes'] ?? PolitiqueEvenements::BAIL_SECONDES_DEFAUT), PolitiqueEvenements::BAIL_SECONDES_MAX);
        $tentativesMax = min((int) ($dossier['tentatives_max'] ?? PolitiqueEvenements::TENTATIVES_MAX_DEFAUT), PolitiqueEvenements::TENTATIVES_MAX_PLAFOND);

        $this->transaction(function () use (
            $reference, $dossier, $consommateurCapacite, $consommateurProduit, $consommateur,
            $tailleLotMax, $dureeBail, $tentativesMax, $creeLe,
        ): void {
            $this->magasin->prepare(
                'INSERT INTO abonnement_evenement
                 (reference,nom,consommateur_capacite_reference,consommateur_produit_reference,consommateur_reference,
                  organisation_reference,realm_reference,finalite_reference,mode_livraison,taille_lot_max,
                  duree_bail_secondes,tentatives_max,cree_par_reference,source_reference,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $reference, (string) $dossier['nom'], $consommateurCapacite, $consommateurProduit, $consommateur,
                $this->nullable($dossier['organisation_reference'] ?? null),
                (string) $dossier['realm_reference'], (string) $dossier['finalite_reference'],
                (string) $dossier['mode_livraison'], $tailleLotMax, $dureeBail, $tentativesMax,
                (string) $dossier['acteur'], (string) $dossier['source'], $creeLe,
            ]);
            $this->inscrireCycle($reference, 'PREPARATION', (string) $dossier['acteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));
            $this->magasin->prepare(
                'INSERT INTO curseur_abonnement(abonnement_reference,derniere_sequence_contigue_accusee,mis_a_jour_le) VALUES(?,0,?)'
            )->execute([$reference, $creeLe]);
        });

        return ['reference' => $reference, 'etat' => 'PREPARATION'];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function ajouterTypeAbonnement(string $abonnement, string $contratReference, string $type, array $dossier): array
    {
        $g = $this->exigerPreparation($abonnement);
        if (isset($g['refus'])) {
            return $g;
        }
        $versionActive = $this->contrats->resoudreVersionActive($contratReference);
        if ($versionActive === null || $versionActive['etat'] !== 'ACTIVE') {
            return $this->refus('CONTRAT_INACTIF', "aucune version active pour `{$contratReference}`");
        }
        $abo = $this->ligneAbonnement($abonnement);
        $partieType = $abo['consommateur_capacite_reference'] !== null ? 'CAPACITE' : 'PRODUIT';
        $consommateur = $abo['consommateur_reference'];
        $declare = array_filter(
            $versionActive['parties'] ?? [],
            static fn (array $p): bool => $p['role'] === 'CONSOMMATEUR' && $p['partie_type'] === $partieType && $p['partie_reference'] === $consommateur,
        );
        if ($declare === []) {
            return $this->refus('CONSOMMATEUR_NON_DECLARE', "`{$consommateur}` n'est pas déclaré CONSOMMATEUR de `{$contratReference}`");
        }
        $compte = (int) $this->magasin->query(
            'SELECT COUNT(*) FROM abonnement_type_evenement WHERE abonnement_reference = ' . $this->magasin->quote($abonnement)
        )->fetchColumn();
        if ($compte >= PolitiqueEvenements::TYPES_MAX_PAR_ABONNEMENT) {
            return $this->refus('LIMITE_ATTEINTE', 'nombre maximal de types atteint');
        }

        $existant = $this->magasin->prepare(
            'SELECT 1 FROM abonnement_type_evenement WHERE abonnement_reference = ? AND contrat_reference = ? AND type_evenement = ?'
        );
        $existant->execute([$abonnement, $contratReference, $type]);
        if ($existant->fetchColumn() !== false) {
            return ['abonnement' => $abonnement, 'type' => $type, 'idempotent' => true];
        }

        $this->magasin->prepare(
            'INSERT INTO abonnement_type_evenement(abonnement_reference,contrat_reference,version_contrainte,type_evenement,cree_le)
             VALUES(?,?,?,?,?)'
        )->execute([$abonnement, $contratReference, $this->nullable($dossier['version_contrainte'] ?? null), $type, gmdate('c')]);

        return ['abonnement' => $abonnement, 'type' => $type, 'idempotent' => false];
    }

    /** @return array<string,mixed> */
    public function ajouterProducteurAbonnement(string $abonnement, string $producteurReference): array
    {
        $g = $this->exigerPreparation($abonnement);
        if (isset($g['refus'])) {
            return $g;
        }
        if ($producteurReference === '' || str_contains($producteurReference, '*')) {
            return $this->refus('PRODUCTEUR_INVALIDE', 'aucun joker n’est autorisé dans une référence de producteur');
        }
        $compte = (int) $this->magasin->query(
            'SELECT COUNT(*) FROM abonnement_producteur WHERE abonnement_reference = ' . $this->magasin->quote($abonnement)
        )->fetchColumn();
        if ($compte >= PolitiqueEvenements::PRODUCTEURS_MAX_PAR_ABONNEMENT) {
            return $this->refus('LIMITE_ATTEINTE', 'nombre maximal de producteurs atteint');
        }
        $existant = $this->magasin->prepare(
            'SELECT 1 FROM abonnement_producteur WHERE abonnement_reference = ? AND producteur_reference = ?'
        );
        $existant->execute([$abonnement, $producteurReference]);
        if ($existant->fetchColumn() !== false) {
            return ['abonnement' => $abonnement, 'producteur' => $producteurReference, 'idempotent' => true];
        }
        $this->magasin->prepare(
            'INSERT INTO abonnement_producteur(abonnement_reference,producteur_reference,cree_le) VALUES(?,?,?)'
        )->execute([$abonnement, $producteurReference, gmdate('c')]);

        return ['abonnement' => $abonnement, 'producteur' => $producteurReference, 'idempotent' => false];
    }

    /** @return array<string,mixed> */
    public function ajouterRealmAbonnement(string $abonnement, string $realmReference, string $portee = 'EXACT'): array
    {
        $g = $this->exigerPreparation($abonnement);
        if (isset($g['refus'])) {
            return $g;
        }
        if (!in_array($portee, PolitiqueEvenements::PORTEES_REALM, true)) {
            return $this->refus('PORTEE_INCONNUE', 'portée hors liste close');
        }
        $etat = $this->realms->resoudreEtat($realmReference);
        if ($etat === null || $etat['etat'] !== 'ACTIF') {
            return $this->refus('REALM_INACTIF', "realm `{$realmReference}` non actif");
        }
        $compte = (int) $this->magasin->query(
            'SELECT COUNT(*) FROM abonnement_realm WHERE abonnement_reference = ' . $this->magasin->quote($abonnement)
        )->fetchColumn();
        if ($compte >= PolitiqueEvenements::REALMS_MAX_PAR_ABONNEMENT) {
            return $this->refus('LIMITE_ATTEINTE', 'nombre maximal de realms atteint');
        }
        $existant = $this->magasin->prepare(
            'SELECT 1 FROM abonnement_realm WHERE abonnement_reference = ? AND realm_reference = ?'
        );
        $existant->execute([$abonnement, $realmReference]);
        if ($existant->fetchColumn() !== false) {
            return ['abonnement' => $abonnement, 'realm' => $realmReference, 'idempotent' => true];
        }
        $this->magasin->prepare(
            'INSERT INTO abonnement_realm(abonnement_reference,realm_reference,portee,cree_le) VALUES(?,?,?,?)'
        )->execute([$abonnement, $realmReference, $portee, gmdate('c')]);

        return ['abonnement' => $abonnement, 'realm' => $realmReference, 'idempotent' => false];
    }

    /**
     * Seuls les paramètres explicitement révisables se modifient, et
     * seulement tant que l'abonnement reste en `PREPARATION` (partie 4 §5) :
     * le nom, l'organisation déclarée et les bornes de lot/bail/tentatives
     * (toujours plafonnées par `PolitiqueEvenements`). Types, producteurs,
     * realms et cycle de vie ont leurs propres commandes dédiées.
     *
     * @param array<string,mixed> $donnees
     * @return array<string,mixed>
     */
    public function modifierAbonnement(string $abonnement, array $donnees): array
    {
        $g = $this->exigerPreparation($abonnement);
        if (isset($g['refus'])) {
            return $g;
        }

        $champs = [];
        $args = [];
        if (array_key_exists('nom', $donnees) && trim((string) $donnees['nom']) !== '') {
            $champs[] = 'nom = ?';
            $args[] = (string) $donnees['nom'];
        }
        if (array_key_exists('organisation_reference', $donnees)) {
            $champs[] = 'organisation_reference = ?';
            $args[] = $this->nullable($donnees['organisation_reference']);
        }
        if (array_key_exists('taille_lot_max', $donnees)) {
            $champs[] = 'taille_lot_max = ?';
            $args[] = min((int) $donnees['taille_lot_max'], PolitiqueEvenements::TAILLE_LOT_MAX);
        }
        if (array_key_exists('duree_bail_secondes', $donnees)) {
            $champs[] = 'duree_bail_secondes = ?';
            $args[] = min((int) $donnees['duree_bail_secondes'], PolitiqueEvenements::BAIL_SECONDES_MAX);
        }
        if (array_key_exists('tentatives_max', $donnees)) {
            $champs[] = 'tentatives_max = ?';
            $args[] = min((int) $donnees['tentatives_max'], PolitiqueEvenements::TENTATIVES_MAX_PLAFOND);
        }
        if ($champs === []) {
            return $this->refus('AUCUNE_MODIFICATION', 'aucun champ révisable fourni');
        }

        $args[] = $abonnement;
        $this->magasin->prepare(
            'UPDATE abonnement_evenement SET ' . implode(', ', $champs) . ' WHERE reference = ?'
        )->execute($args);

        return $this->resoudreAbonnement($abonnement) ?? [];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function activerAbonnement(string $abonnement, array $dossier): array
    {
        foreach (['acteur', 'politique', 'preuve'] as $champ) {
            if (trim((string) ($dossier[$champ] ?? '')) === '') {
                return $this->refus('CHAMP_MANQUANT', "champ obligatoire absent : {$champ}");
            }
        }
        $etat = $this->etatCourant($abonnement);
        if ($etat === null) {
            return $this->refus('ABONNEMENT_INCONNU', "abonnement `{$abonnement}` inconnu");
        }
        if ($etat === 'ACTIF') {
            return ['abonnement' => $abonnement, 'etat' => 'ACTIF', 'idempotent' => true];
        }
        if ($etat !== 'PREPARATION') {
            return $this->refus('ETAT_INCOMPATIBLE', "un abonnement `{$etat}` ne s’active pas directement");
        }
        $nbTypes = (int) $this->magasin->query('SELECT COUNT(*) FROM abonnement_type_evenement WHERE abonnement_reference = ' . $this->magasin->quote($abonnement))->fetchColumn();
        if ($nbTypes === 0) {
            return $this->refus('AUCUN_TYPE', 'activation sans type refusée');
        }
        $nbProducteurs = (int) $this->magasin->query('SELECT COUNT(*) FROM abonnement_producteur WHERE abonnement_reference = ' . $this->magasin->quote($abonnement))->fetchColumn();
        if ($nbProducteurs === 0) {
            return $this->refus('AUCUN_PRODUCTEUR', 'activation sans producteur refusée');
        }
        $nbRealms = (int) $this->magasin->query('SELECT COUNT(*) FROM abonnement_realm WHERE abonnement_reference = ' . $this->magasin->quote($abonnement))->fetchColumn();
        if ($nbRealms === 0) {
            return $this->refus('AUCUN_REALM', 'activation sans realm refusée');
        }

        $this->inscrireCycle($abonnement, 'ACTIF', (string) $dossier['acteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

        return ['abonnement' => $abonnement, 'etat' => 'ACTIF', 'idempotent' => false];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function suspendreAbonnement(string $abonnement, array $dossier): array
    {
        $etat = $this->etatCourant($abonnement);
        if ($etat === null) {
            return $this->refus('ABONNEMENT_INCONNU', "abonnement `{$abonnement}` inconnu");
        }
        if ($etat === 'SUSPENDU') {
            return ['abonnement' => $abonnement, 'etat' => 'SUSPENDU', 'idempotent' => true];
        }
        if ($etat !== 'ACTIF') {
            return $this->refus('ETAT_INCOMPATIBLE', "seul un abonnement ACTIF se suspend (état actuel `{$etat}`)");
        }
        $this->inscrireCycle($abonnement, 'SUSPENDU', (string) $dossier['acteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

        return ['abonnement' => $abonnement, 'etat' => 'SUSPENDU', 'idempotent' => false];
    }

    /** @param array<string,mixed> $dossier @return array<string,mixed> */
    public function retirerAbonnement(string $abonnement, array $dossier): array
    {
        $etat = $this->etatCourant($abonnement);
        if ($etat === null) {
            return $this->refus('ABONNEMENT_INCONNU', "abonnement `{$abonnement}` inconnu");
        }
        if ($etat === 'RETIRE') {
            return ['abonnement' => $abonnement, 'etat' => 'RETIRE', 'idempotent' => true];
        }
        $this->inscrireCycle($abonnement, 'RETIRE', (string) $dossier['acteur'], (string) $dossier['politique'], (string) $dossier['preuve'], $this->nullable($dossier['correlation_id'] ?? null));

        return ['abonnement' => $abonnement, 'etat' => 'RETIRE', 'idempotent' => false];
    }

    // ------------------------------------------------------------------
    // Lectures

    /** @return array<string,mixed>|null */
    public function resoudreAbonnement(string $reference): ?array
    {
        $ligne = $this->ligneAbonnement($reference);
        if ($ligne === null) {
            return null;
        }

        return $ligne + ['etat' => $this->etatCourant($reference)];
    }

    /** @return list<array<string,mixed>> */
    public function listerAbonnements(?string $consommateur = null): array
    {
        $sql = 'SELECT reference FROM abonnement_evenement';
        $args = [];
        if ($consommateur !== null) {
            $sql .= ' WHERE consommateur_reference = ?';
            $args[] = $consommateur;
        }
        $sql .= ' ORDER BY reference';
        $st = $this->magasin->prepare($sql);
        $st->execute($args);

        return array_map(fn (array $r): array => $this->resoudreAbonnement((string) $r['reference']) ?? [], $st->fetchAll());
    }

    /**
     * Types déclarés (fiche partie 4 §9 — écran d'abonnement).
     *
     * @return list<array<string,mixed>>
     */
    public function resoudreTypes(string $abonnement): array
    {
        $st = $this->magasin->prepare(
            'SELECT contrat_reference, version_contrainte, type_evenement, cree_le
             FROM abonnement_type_evenement WHERE abonnement_reference = ? ORDER BY id'
        );
        $st->execute([$abonnement]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function resoudreProducteurs(string $abonnement): array
    {
        $st = $this->magasin->prepare(
            'SELECT producteur_reference, cree_le FROM abonnement_producteur WHERE abonnement_reference = ? ORDER BY id'
        );
        $st->execute([$abonnement]);

        return $st->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function resoudreRealmsAbonnement(string $abonnement): array
    {
        $st = $this->magasin->prepare(
            'SELECT realm_reference, portee, cree_le FROM abonnement_realm WHERE abonnement_reference = ? ORDER BY id'
        );
        $st->execute([$abonnement]);

        return $st->fetchAll();
    }

    public function etatCourant(string $abonnement): ?string
    {
        $st = $this->magasin->prepare(
            'SELECT etat FROM abonnement_cycle WHERE abonnement_reference = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$abonnement]);
        $v = $st->fetchColumn();

        return $v === false ? null : (string) $v;
    }

    // ------------------------------------------------------------------
    // Internes

    /** @return array<string,mixed>|null */
    private function ligneAbonnement(string $reference): ?array
    {
        $st = $this->magasin->prepare('SELECT * FROM abonnement_evenement WHERE reference = ?');
        $st->execute([$reference]);
        $l = $st->fetch();

        return $l === false ? null : $l;
    }

    /** @return array<string,mixed> */
    private function exigerPreparation(string $abonnement): array
    {
        $etat = $this->etatCourant($abonnement);
        if ($etat === null) {
            return $this->refus('ABONNEMENT_INCONNU', "abonnement `{$abonnement}` inconnu");
        }
        if ($etat !== 'PREPARATION') {
            return $this->refus('ETAT_INCOMPATIBLE', "modification possible seulement en PREPARATION (état actuel `{$etat}`)");
        }

        return [];
    }

    private function inscrireCycle(string $abonnement, string $etat, string $acteur, string $politique, string $preuve, ?string $correlation): void
    {
        $this->magasin->prepare(
            'INSERT INTO abonnement_cycle(abonnement_reference,etat,date_effet,acteur_reference,politique_reference,preuve_reference,correlation_id,cree_le)
             VALUES(?,?,?,?,?,?,?,?)'
        )->execute([$abonnement, $etat, gmdate('c'), $acteur, $politique, $preuve, $correlation, gmdate('c')]);
    }

    private function transaction(callable $fn): void
    {
        $propre = !$this->magasin->inTransaction();
        if ($propre) {
            $this->magasin->beginTransaction();
        }
        try {
            $fn();
            if ($propre) {
                $this->magasin->commit();
            }
        } catch (\Throwable $e) {
            if ($propre && $this->magasin->inTransaction()) {
                $this->magasin->rollBack();
            }
            throw $e;
        }
    }

    private function nullable(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' ? null : $t;
    }

    /** @return array{refus:string,detail:string} */
    private function refus(string $code, string $detail): array
    {
        return ['refus' => $code, 'detail' => $detail];
    }
}
