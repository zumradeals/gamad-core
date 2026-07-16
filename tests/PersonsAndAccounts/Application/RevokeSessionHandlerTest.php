<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Application;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\Command\RevokeSession;
use Gamad\Core\PersonsAndAccounts\Application\Command\RevokeSessionHandler;
use Gamad\Core\PersonsAndAccounts\Application\Exception\SessionNotFound;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemorySessionRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Infrastructure\AccessControl\PermissiveAccessControlGateway;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class RevokeSessionHandlerTest extends TestCase
{
    public function test_it_revokes_an_active_session(): void
    {
        $sessions = new InMemorySessionRepository();
        $session = Session::issue(
            SessionId::generate(),
            UserAccountId::generate(),
            AuthenticationMethodId::generate(),
            hash('sha256', 'raw-token'),
            new DateTimeImmutable('+1 hour'),
        );
        $session->releaseEvents();
        $sessions->save($session);
        $outbox = new InMemoryOutboxRepository();
        $handler = new RevokeSessionHandler(
            sessions: $sessions,
            persister: new AtomicSessionPersister($sessions, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
            accessControl: new PermissiveAccessControlGateway(),
        );

        $handler(new RevokeSession((string) $session->id()));

        self::assertTrue($sessions->findById($session->id())?->isRevoked());
        self::assertCount(1, $outbox->messages);
    }

    public function test_it_rejects_an_unknown_session(): void
    {
        $sessions = new InMemorySessionRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new RevokeSessionHandler(
            sessions: $sessions,
            persister: new AtomicSessionPersister($sessions, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
            accessControl: new PermissiveAccessControlGateway(),
        );

        $this->expectException(SessionNotFound::class);

        $handler(new RevokeSession('11111111-1111-4111-8111-111111111111'));
    }
}
