<?php

declare(strict_types=1);

namespace Gamad\JournalEvenements;

/**
 * Évalue le routage d'un événement accepté vers les abonnements actifs.
 *
 * N'évalue que des critères explicites et fermés (partie 3 §6) : aucune
 * ressemblance de texte, aucun préfixe non déclaré, aucun realm parent
 * omniscient, aucun `null` signifiant « tous ». L'absence d'une condition
 * vaut non-routage. Un événement sans abonnement correspondant n'est jamais
 * perdu : il reste dans `evenement_commun`.
 *
 * La décision `CAP-CORE-004` favorable (partie 3 §6, condition 10) n'est pas
 * réévaluée ici à chaque diffusion : elle a gouverné la création et
 * l'activation de l'abonnement, puis gouverne à nouveau chaque lecture PULL
 * dans `LivreurEvenements`. Cette classe ne fait que faire correspondre des
 * filtres déjà autorisés, jamais accorder une permission nouvelle.
 */
final class RouteurEvenements
{
    public function __construct(private \PDO $magasin)
    {
    }

    /** @param array<string,mixed> $contenu */
    public function distribuer(string $evenementReference, int $sequenceId, array $contenu, string $disponibleLe): void
    {
        foreach ($this->abonnementsActifsCorrespondants($contenu) as $abonnement) {
            $reference = 'LIV-GAMAD-' . strtoupper(bin2hex(random_bytes(10)));
            $this->magasin->prepare(
                'INSERT INTO livraison_evenement
                 (reference,abonnement_reference,evenement_reference,sequence_evenement,etat,disponible_le,cree_le)
                 VALUES(?,?,?,?,\'DISPONIBLE\',?,?)'
            )->execute([
                $reference, $abonnement, $evenementReference, $sequenceId, $disponibleLe, $disponibleLe,
            ]);
        }
    }

    /** @param array<string,mixed> $contenu @return list<string> */
    private function abonnementsActifsCorrespondants(array $contenu): array
    {
        $actifs = $this->magasin->query(
            "SELECT a.reference, a.realm_reference AS abonnement_realm_defaut, a.finalite_reference,
                    a.consommateur_produit_reference, a.consommateur_capacite_reference
             FROM abonnement_evenement a
             WHERE a.reference IN (
                 SELECT ac1.abonnement_reference FROM abonnement_cycle ac1
                 WHERE ac1.etat = 'ACTIF' AND ac1.id = (
                     SELECT id FROM abonnement_cycle ac2
                     WHERE ac2.abonnement_reference = ac1.abonnement_reference
                     ORDER BY id DESC LIMIT 1
                 )
             )"
        )->fetchAll();

        $correspondants = [];
        foreach ($actifs as $abonnement) {
            $reference = (string) $abonnement['reference'];

            if ($contenu['classification'] === 'SECRET_CORE' && $abonnement['consommateur_produit_reference'] !== null) {
                continue;
            }
            if ((string) $abonnement['finalite_reference'] !== (string) $contenu['finalite_reference']) {
                continue;
            }
            if (!$this->possedeType($reference, (string) $contenu['contrat_reference'], (string) $contenu['type_evenement'], (string) $contenu['contrat_version'])) {
                continue;
            }
            if (!$this->possedeProducteur($reference, (string) $contenu['producteur_reference'])) {
                continue;
            }
            if (!$this->possedeRealm($reference, (string) $contenu['realm_reference'])) {
                continue;
            }

            $correspondants[] = $reference;
        }

        return $correspondants;
    }

    private function possedeType(string $abonnement, string $contratReference, string $type, string $version): bool
    {
        $st = $this->magasin->prepare(
            'SELECT version_contrainte FROM abonnement_type_evenement
             WHERE abonnement_reference = ? AND contrat_reference = ? AND type_evenement = ?'
        );
        $st->execute([$abonnement, $contratReference, $type]);
        $ligne = $st->fetch();
        if ($ligne === false) {
            return false;
        }
        $contrainte = $ligne['version_contrainte'];

        return $contrainte === null || $contrainte === '' || $contrainte === $version;
    }

    private function possedeProducteur(string $abonnement, string $producteurReference): bool
    {
        $st = $this->magasin->prepare(
            'SELECT 1 FROM abonnement_producteur WHERE abonnement_reference = ? AND producteur_reference = ?'
        );
        $st->execute([$abonnement, $producteurReference]);

        return $st->fetchColumn() !== false;
    }

    private function possedeRealm(string $abonnement, string $realmReference): bool
    {
        // Chaque realm autorisé est enregistré explicitement (`ajouterRealmAbonnement`) :
        // un realm parent ne donne jamais accès implicite à ses descendants ici,
        // même marqué `DESCENDANTS_EXPLICITES` — cette portée documente
        // l'intention de la commande d'ajout, pas une résolution d'arbre au
        // moment du routage (partie 2 §9).
        $st = $this->magasin->prepare(
            'SELECT 1 FROM abonnement_realm WHERE abonnement_reference = ? AND realm_reference = ?'
        );
        $st->execute([$abonnement, $realmReference]);

        return $st->fetchColumn() !== false;
    }
}
