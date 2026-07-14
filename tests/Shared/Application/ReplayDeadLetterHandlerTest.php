<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Application;

use Gamad\Core\Shared\Application\ReplayDeadLetterHandler;
use Gamad\Core\Shared\Outbox\DeadLetterMessage;
use Gamad\Core\Shared\Outbox\DeadLetterRepository;
use Gamad\Core\Shared\Security\AuthorizationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReplayDeadLetterHandlerTest extends TestCase
{
    public function test_it_rejects_an_unauthorized_actor(): void
    {
        $repository = new class implements DeadLetterRepository {
            public function list(int $limit = 100, int $offset = 0): array { return []; }
            public function find(string $messageId): ?DeadLetterMessage { return null; }
            public function replay(string $messageId): bool { return true; }
        };
        $authorization = new class implements AuthorizationService {
            public function isAllowed(string $actorId, string $permission): bool { return false; }
        };

        $handler = new ReplayDeadLetterHandler($repository, $authorization);

        $this->expectException(RuntimeException::class);
        $handler->replay('GAM-GAT-PER-000001', '11111111-1111-4111-8111-111111111111');
    }

    public function test_it_replays_for_an_authorized_actor(): void
    {
        $repository = new class implements DeadLetterRepository {
            public function list(int $limit = 100, int $offset = 0): array { return []; }
            public function find(string $messageId): ?DeadLetterMessage { return null; }
            public function replay(string $messageId): bool { return true; }
        };
        $authorization = new class implements AuthorizationService {
            public function isAllowed(string $actorId, string $permission): bool
            {
                return $permission === ReplayDeadLetterHandler::PERMISSION;
            }
        };

        self::assertTrue((new ReplayDeadLetterHandler($repository, $authorization))->replay(
            'GAM-GAT-PER-000001',
            '11111111-1111-4111-8111-111111111111',
        ));
    }
}
