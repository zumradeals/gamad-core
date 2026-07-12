<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Audit;

final readonly class AuditChainVerificationResult
{
    public function __construct(
        public bool $valid,
        public int $verifiedRecords,
        public string $headHash,
        public ?int $failedRecordId = null,
    ) {
    }
}
