<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

final readonly class OutboxPublishReport
{
    public function __construct(
        public int $claimed,
        public int $published,
        public int $retried,
        public int $deadLettered,
    ) {
    }
}
