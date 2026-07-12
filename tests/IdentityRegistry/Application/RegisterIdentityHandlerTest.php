<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Application;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentity;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\Exception\IdentityAlreadyExists;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\InMemoryIdentityRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class RegisterIdentityHandlerTest extends TestCase
{
    public function test_it_persists_identity_and_outbox_message_atomically(): void
    {
        $repository = new InMemoryIdentityRepository();
        $outbox = new InMemoryOutboxRepository();
        $persister = new AtomicIdentityPersister(
            identities: $repository,
            outbox: $outbox,
            events: new DomainEventCollector(),
            transactions: new SynchronousTransactionManager(),
        );
        $handler = new RegisterIdentityHandler($repository, $persister);
        $identityId = new IdentityId('GAM-ORG-000001');
        $registeredAt = new DateTimeImmutable('2026-07-12T08:00:00+00:00');

        $identity = $handler(new RegisterIdentity(
            identityId: $identityId,
            identityType: IdentityType::Organization,
            registeredAt: $registeredAt,
        ));

        self::assertTrue($repository->exists($identityId));
        self::assertSame($identity, $repository->findById($identityId));
        self::assertSame(IdentityStatus::Active, $identity->status());
        self::assertCount(1, $outbox->messages);
        self::assertSame('identity.registered.v1', $outbox->messages[0]->eventName);
        self::assertSame((string) $identityId, $outbox->messages[0]->aggregateId);
        self::assertSame([], $identity->recordedEvents());
    }

    public function test_it_rejects_a_duplicate_identity_identifier(): void
    {
        $repository = new InMemoryIdentityRepository();
        $outbox = new InMemoryOutboxRepository();
        $persister = new AtomicIdentityPersister(
            identities: $repository,
            outbox: $outbox,
            events: new DomainEventCollector(),
            transactions: new SynchronousTransactionManager(),
        );
        $handler = new RegisterIdentityHandler($repository, $persister);
        $command = new RegisterIdentity(
            identityId: new IdentityId('GAM-PER-000002'),
            identityType: IdentityType::Person,
            registeredAt: new DateTimeImmutable('2026-07-12T08:05:00+00:00'),
        );

        $handler($command);

        $this->expectException(IdentityAlreadyExists::class);
        $handler($command);
    }
}
