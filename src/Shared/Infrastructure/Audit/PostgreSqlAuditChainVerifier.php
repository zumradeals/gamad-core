<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Audit;

use DateTimeImmutable;
use Gamad\Core\Shared\Audit\AuditChainVerificationResult;
use Gamad\Core\Shared\Audit\CanonicalJson;
use PDO;

final readonly class PostgreSqlAuditChainVerifier
{
    public function __construct(private PDO $connection)
    {
    }

    public function verify(): AuditChainVerificationResult
    {
        $rows = $this->connection->query(
            'SELECT id, actor_id, action, target, status_code, occurred_at, context, previous_hash, record_hash FROM administrative_audit ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);

        $previousHash = str_repeat('0', 64);
        $verified = 0;

        foreach ($rows as $row) {
            $context = json_decode((string) $row['context'], true, flags: JSON_THROW_ON_ERROR);
            $contextJson = CanonicalJson::encode($context);
            $canonical = implode('|', [
                $previousHash,
                (string) $row['actor_id'],
                (string) $row['action'],
                (string) $row['target'],
                (string) $row['status_code'],
                (new DateTimeImmutable((string) $row['occurred_at']))->format(DATE_ATOM),
                $contextJson,
            ]);
            $calculated = hash('sha256', $canonical);

            if (!hash_equals($previousHash, (string) $row['previous_hash']) || !hash_equals($calculated, (string) $row['record_hash'])) {
                return new AuditChainVerificationResult(false, $verified, $previousHash, (int) $row['id']);
            }

            $previousHash = (string) $row['record_hash'];
            ++$verified;
        }

        return new AuditChainVerificationResult(true, $verified, $previousHash);
    }
}
