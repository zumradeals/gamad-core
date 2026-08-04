<?php

declare(strict_types=1);

namespace Gamad\EvenementsSortants;

/**
 * Préparation d'une intention d'événement dans l'outbox du producteur
 * (CAP-CORE-014, partie 3 §3).
 *
 * `preparerEvenement()` ne publie jamais rien sur le réseau : elle insère
 * une ligne dans la transaction métier déjà ouverte par l'appelant. Si cette
 * transaction est annulée, la ligne d'outbox disparaît avec elle ; si elle
 * est validée, la ligne survit et devient publiable par `RelaisOutbox`.
 */
final class OutboxProducteur
{
    /** @param array<string,mixed> $intention @return array<string,mixed> */
    public static function preparerEvenement(\PDO $pdo, array $intention): array
    {
        foreach ([
            'type_evenement', 'contrat_reference', 'contrat_version',
            'source_reference', 'realm_reference', 'finalite_reference',
            'correlation_id', 'survenu_le', 'classification', 'idempotence_reference',
        ] as $champ) {
            if (trim((string) ($intention[$champ] ?? '')) === '') {
                throw new \InvalidArgumentException("Champ obligatoire absent pour l’outbox : {$champ}.");
            }
        }
        $producteurCapacite = self::nullable($intention['producteur_capacite_reference'] ?? null);
        $producteurProduit = self::nullable($intention['producteur_produit_reference'] ?? null);
        if ($producteurCapacite === null && $producteurProduit === null) {
            throw new \InvalidArgumentException('Aucun producteur déclaré (capacité ou produit).');
        }

        $charge = is_array($intention['charge'] ?? null) ? $intention['charge'] : [];
        $chargeJson = json_encode(self::trier($charge), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $chargeEmpreinte = hash('sha256', $chargeJson);

        $idempotence = (string) $intention['idempotence_reference'];
        $existant = $pdo->prepare('SELECT id, etat, evenement_reference FROM evenement_sortant WHERE idempotence_reference = ?');
        $existant->execute([$idempotence]);
        $ligne = $existant->fetch();
        if ($ligne !== false) {
            return ['id' => (int) $ligne['id'], 'idempotence_reference' => $idempotence, 'idempotent' => true, 'etat' => $ligne['etat']];
        }

        $pdo->prepare(
            'INSERT INTO evenement_sortant
             (idempotence_reference,type_evenement,contrat_reference,contrat_version,
              producteur_capacite_reference,producteur_produit_reference,source_reference,realm_reference,
              finalite_reference,sujet_type,sujet_reference,correlation_id,causation_reference,
              survenu_le,classification,charge_json,charge_empreinte,etat,tentatives,cree_le)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'EN_ATTENTE\',0,?)'
        )->execute([
            $idempotence, (string) $intention['type_evenement'], (string) $intention['contrat_reference'], (string) $intention['contrat_version'],
            $producteurCapacite, $producteurProduit, (string) $intention['source_reference'], (string) $intention['realm_reference'],
            (string) $intention['finalite_reference'], self::nullable($intention['sujet_type'] ?? null), self::nullable($intention['sujet_reference'] ?? null),
            (string) $intention['correlation_id'], self::nullable($intention['causation_reference'] ?? null),
            (string) $intention['survenu_le'], (string) $intention['classification'], $chargeJson, $chargeEmpreinte, gmdate('c'),
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'idempotence_reference' => $idempotence,
            'idempotent' => false,
            'etat' => 'EN_ATTENTE',
        ];
    }

    private static function nullable(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' ? null : $t;
    }

    private static function trier(mixed $v): mixed
    {
        if (!is_array($v)) {
            return $v;
        }
        if (!array_is_list($v)) {
            ksort($v);
        }
        foreach ($v as $k => $vv) {
            $v[$k] = self::trier($vv);
        }

        return $v;
    }
}
