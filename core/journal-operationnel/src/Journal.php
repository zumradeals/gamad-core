<?php

declare(strict_types=1);

namespace Gamad\JournalOperationnel;

/**
 * Journal append-only avec chaînage SHA-256.
 *
 * Les données sensibles sont supprimées avant sérialisation. L'empreinte
 * fournie à l'appelant constitue une preuve de présence et d'ordre, pas une
 * signature d'origine.
 */
final class Journal
{
    private const RACINE = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $evenement
     * @return array<string,mixed>
     */
    public function enregistrer(array $evenement): array
    {
        foreach (['categorie', 'type'] as $champ) {
            if (trim((string) ($evenement[$champ] ?? '')) === '') {
                throw new \InvalidArgumentException("Champ obligatoire absent : {$champ}.");
            }
        }

        $reference = 'EVT-OP-' . strtoupper(bin2hex(random_bytes(10)));
        $correlation = trim((string) ($evenement['correlation_id'] ?? ''));
        if ($correlation === '') {
            $correlation = 'COR-' . strtoupper(bin2hex(random_bytes(8)));
        }
        $creeLe = (string) ($evenement['cree_le'] ?? gmdate('c'));
        $donnees = $this->nettoyer($evenement['donnees'] ?? []);

        $propreTransaction = !$this->pdo->inTransaction();
        $transactionSqlite = $propreTransaction && $this->driver() === 'sqlite';
        if ($propreTransaction) {
            if ($transactionSqlite) {
                // Sérialise l'allocation de la tête de chaîne entre processus.
                $this->pdo->exec('BEGIN IMMEDIATE');
            } else {
                $this->pdo->beginTransaction();
            }
        }

        try {
            if ($this->driver() === 'pgsql') {
                $this->pdo->exec("SELECT pg_advisory_xact_lock(714014)");
            }

            $precedente = $this->pdo->query(
                'SELECT empreinte FROM evenement_operationnel ORDER BY sequence_id DESC LIMIT 1'
            )->fetchColumn();
            $precedente = $precedente === false ? null : (string) $precedente;

            $contenu = [
                'reference' => $reference,
                'categorie' => (string) $evenement['categorie'],
                'type' => (string) $evenement['type'],
                'acteur' => $this->nullable($evenement['acteur'] ?? null),
                'action' => $this->nullable($evenement['action'] ?? null),
                'ressource' => $this->nullable($evenement['ressource'] ?? null),
                'decision' => $this->nullable($evenement['decision'] ?? null),
                'motif' => $this->nullable($evenement['motif'] ?? null),
                'correlation_id' => $correlation,
                'donnees' => $donnees,
                'empreinte_precedente' => $precedente,
                'cree_le' => $creeLe,
            ];
            $empreinte = hash(
                'sha256',
                ($precedente ?? self::RACINE) . "\n" . $this->jsonCanonique($contenu),
            );

            $st = $this->pdo->prepare(
                'INSERT INTO evenement_operationnel
                 (reference,categorie,type_evenement,acteur,action,ressource,decision,motif,
                  correlation_id,donnees,empreinte_precedente,empreinte,cree_le)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $reference,
                $contenu['categorie'],
                $contenu['type'],
                $contenu['acteur'],
                $contenu['action'],
                $contenu['ressource'],
                $contenu['decision'],
                $contenu['motif'],
                $correlation,
                $this->jsonCanonique($donnees),
                $precedente,
                $empreinte,
                $creeLe,
            ]);

            if ($propreTransaction) {
                if ($transactionSqlite) {
                    $this->pdo->exec('COMMIT');
                } else {
                    $this->pdo->commit();
                }
            }

            return [
                'reference' => $reference,
                'correlation_id' => $correlation,
                'empreinte' => $empreinte,
                'algorithme' => 'sha256',
                'signee' => false,
                'cree_le' => $creeLe,
            ];
        } catch (\Throwable $e) {
            if ($propreTransaction) {
                if ($transactionSqlite) {
                    try {
                        $this->pdo->exec('ROLLBACK');
                    } catch (\Throwable) {
                        // La transaction a pu être annulée par SQLite lui-même.
                    }
                } elseif ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            }
            throw $e;
        }
    }

    /** @return array{valide:bool,evenements:int,ecarts:list<string>,tete:?string} */
    public function verifierIntegrite(): array
    {
        $precedente = null;
        $ecarts = [];
        $nombre = 0;

        $lignes = $this->pdo->query(
            'SELECT reference,categorie,type_evenement,acteur,action,ressource,decision,motif,
                    correlation_id,donnees,empreinte_precedente,empreinte,cree_le
             FROM evenement_operationnel ORDER BY sequence_id'
        )->fetchAll();

        foreach ($lignes as $ligne) {
            $nombre++;
            $contenu = [
                'reference' => $ligne['reference'],
                'categorie' => $ligne['categorie'],
                'type' => $ligne['type_evenement'],
                'acteur' => $ligne['acteur'],
                'action' => $ligne['action'],
                'ressource' => $ligne['ressource'],
                'decision' => $ligne['decision'],
                'motif' => $ligne['motif'],
                'correlation_id' => $ligne['correlation_id'],
                'donnees' => json_decode((string) $ligne['donnees'], true, flags: JSON_THROW_ON_ERROR),
                'empreinte_precedente' => $ligne['empreinte_precedente'],
                'cree_le' => $ligne['cree_le'],
            ];
            $attendue = hash(
                'sha256',
                ($precedente ?? self::RACINE) . "\n" . $this->jsonCanonique($contenu),
            );
            if ($ligne['empreinte_precedente'] !== $precedente) {
                $ecarts[] = "chaînage rompu à {$ligne['reference']}";
            }
            if (!hash_equals($attendue, (string) $ligne['empreinte'])) {
                $ecarts[] = "empreinte invalide à {$ligne['reference']}";
            }
            $precedente = (string) $ligne['empreinte'];
        }

        return [
            'valide' => $ecarts === [],
            'evenements' => $nombre,
            'ecarts' => $ecarts,
            'tete' => $precedente,
        ];
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    private function nullable(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    private function nettoyer(mixed $valeur, ?string $cle = null): mixed
    {
        if ($cle !== null && preg_match(
            '/(?:secret|password|mot_de_passe|authorization|cookie|session|token|empreinte_secret)/i',
            $cle,
        )) {
            return '[EXPURGÉ]';
        }
        if (!is_array($valeur)) {
            return is_scalar($valeur) || $valeur === null ? $valeur : (string) $valeur;
        }

        $propre = [];
        foreach ($valeur as $k => $v) {
            $propre[$k] = $this->nettoyer($v, is_string($k) ? $k : null);
        }

        return $propre;
    }

    private function jsonCanonique(mixed $valeur): string
    {
        $normalisee = $this->trier($valeur);

        return json_encode(
            $normalisee,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function trier(mixed $valeur): mixed
    {
        if (!is_array($valeur)) {
            return $valeur;
        }
        if (!array_is_list($valeur)) {
            ksort($valeur);
        }
        foreach ($valeur as $k => $v) {
            $valeur[$k] = $this->trier($v);
        }

        return $valeur;
    }
}
