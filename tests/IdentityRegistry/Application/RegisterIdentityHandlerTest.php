<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Application;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentity;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\Exception\IdentityAlreadyExists;
use Gamad\Core\IdentityRegistry\Domain\Event\IdentityRegistered;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\InMemoryIdentityRepository;
use PHPUnit\Framework\TestCase;

final class RegisterIdentityHandlerTest extends TestCase
{
    public function test_it_registers_and_persists_an_identity(): void
    {
        $repository = new InMemoryIdentityRepository();
        $handler = new RegisterIdentityHandler($repository);
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
        self::assertSame(IdentityType::Organization, $identity->type());

        $events = $identity->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(IdentityRegistered::class, $events[0]);
    }

    public function test_it_rejects_a_duplicate_identity_identifier(): void
    {
        $repository = new InMemoryIdentityRepository();
        $handler = new RegisterIdentityHandler($repository);
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
