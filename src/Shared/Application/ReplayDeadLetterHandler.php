<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Application;

use Gamad\Core\Shared\Outbox\DeadLetterRepository;
use Gamad\Core\Shared\Security\AuthorizationService;
use RuntimeException;

final readonly class ReplayDeadLetterHandler
{
    public const PERMISSION = 'core.outbox.dead_letter.replay';

    public function __construct(
        private DeadLetterRepository $deadLetters,
        private AuthorizationService $authorization,
    ) {
    }

    public function replay(string $actorId, string $messageId): bool
    {
        if (!$this->authorization->isAllowed($actorId, self::PERMISSION)) {
            throw new RuntimeException('Actor is not authorized to replay dead-letter messages.');
        }

        return $this->deadLetters->replay($messageId);
    }
}
