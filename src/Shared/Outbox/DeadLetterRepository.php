<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

interface DeadLetterRepository
{
    /** @return list<DeadLetterMessage> */
    public function list(int $limit = 100, int $offset = 0): array;

    public function find(string $messageId): ?DeadLetterMessage;

    public function replay(string $messageId): bool;
}
