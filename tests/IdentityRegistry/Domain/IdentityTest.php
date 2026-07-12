<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Domain;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Domain\Event\IdentityRegistered;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function test_it_registers_an_active_identity_and_records_the_event(): void
    {
        $registeredAt = new DateTimeImmutable('2026-07-12T00:00:00+00:00');
        $identityId = new IdentityId('GAM-PER-000001');

        $identity = Identity::register(
            id: $identityId,
            type: IdentityType::Person,
            registeredAt: $registeredAt,
        );

        self::assertTrue($identity->id()->equals($identityId));
        self::assertSame(IdentityType::Person, $identity->type());
        self::assertSame(IdentityStatus::Active, $identity->status());
        self::assertEquals($registeredAt, $identity->registeredAt());

        $events = $identity->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(IdentityRegistered::class, $events[0]);
        self::assertSame('identity.registered.v1', $events[0]->eventName());
        self::assertSame([], $identity->releaseEvents());
    }
}
