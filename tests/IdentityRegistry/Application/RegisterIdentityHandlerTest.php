<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Application;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Application\AllocatedIdentityIdentifier;
use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentity;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\IdentityIdentifierAuthority;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\InMemoryIdentityRepository;
use Gamad\Core\IdentityRegistry\Infrastructure\Policy\AllowConfiguredIdentityTypesPolicy;
use Gamad\Core\IdentityRegistry\Infrastructure\Policy\AllowConfiguredRealmPolicy;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Infrastructure\AccessControl\PermissiveAccessControlGateway;
use Gamad\Core\Shared\Infrastructure\Metrics\InMemoryMetricsCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class RegisterIdentityHandlerTest extends TestCase
{
    public function test_it_allocates_persists_emits_and_measures_registration(): void
    {
        $repository = new InMemoryIdentityRepository();
        $outbox = new InMemoryOutboxRepository();
        $metrics = new InMemoryMetricsCollector();
        $authority = new class implements IdentityIdentifierAuthority {
            public function allocate(IdentityType $type, string $realm): AllocatedIdentityIdentifier
            {
                return new AllocatedIdentityIdentifier(
                    new IdentityInternalId('11111111-1111-4111-8111-111111111111'),
                    new IdentityId('GAM-GAT-ORG-000001'),
                );
            }
        };
        $handler = new RegisterIdentityHandler(
            identifiers: $authority,
            policy: new AllowConfiguredIdentityTypesPolicy([IdentityType::Organization]),
            persister: new AtomicIdentityPersister(
                identities: $repository,
                outbox: $outbox,
                events: new DomainEventCollector(),
                transactions: new SynchronousTransactionManager(),
            ),
            metrics: $metrics,
            realm: new AllowConfiguredRealmPolicy('GAT'),
            accessControl: new PermissiveAccessControlGateway(),
        );

        $identity = $handler(new RegisterIdentity(
            identityType: IdentityType::Organization,
            actorId: 'GAM-GAT-PER-000001',
            registeredAt: new DateTimeImmutable('2026-07-12T08:00:00+00:00'),
        ));

        self::assertSame('GAM-GAT-ORG-000001', (string) $identity->id());
        self::assertSame(IdentityStatus::Active, $identity->status());
        self::assertTrue($repository->exists($identity->id()));
        self::assertCount(1, $outbox->messages);
        self::assertSame(1, $metrics->counters['gamad_identity_registered_total:identity_type=organization']);
    }
}
