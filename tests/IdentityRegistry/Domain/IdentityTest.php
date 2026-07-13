<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Domain;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Domain\Event\IdentityRegistered;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function test_it_registers_an_active_identity_with_distinct_identifiers(): void
    {
        $registeredAt = new DateTimeImmutable('2026-07-12T00:00:00+00:00');
        $identityId = new IdentityId('GAM-PER-000001');
        $internalId = new IdentityInternalId('11111111-1111-4111-8111-111111111111');

        $identity = Identity::register(
            internalId: $internalId,
            id: $identityId,
            type: IdentityType::Person,
            registeredAt: $registeredAt,
        );

        self::assertSame((string) $internalId, (string) $identity->internalId());
        self::assertTrue($identity->id()->equals($identityId));
        self::assertSame(IdentityStatus::Active, $identity->status());
        self::assertInstanceOf(IdentityRegistered::class, $identity->releaseEvents()[0]);
    }
}
