<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Infrastructure\Policy;

use DomainException;
use Gamad\Core\IdentityRegistry\Infrastructure\Policy\AllowConfiguredRealmPolicy;
use PHPUnit\Framework\TestCase;

final class AllowConfiguredRealmPolicyTest extends TestCase
{
    public function test_it_accepts_a_valid_realm_code(): void
    {
        self::assertSame('GAT', (new AllowConfiguredRealmPolicy('GAT'))->realm());
    }

    public function test_it_accepts_the_shortest_and_longest_valid_lengths(): void
    {
        self::assertSame('AB', (new AllowConfiguredRealmPolicy('AB'))->realm());
        self::assertSame('ABCDEF', (new AllowConfiguredRealmPolicy('ABCDEF'))->realm());
    }

    public function test_it_rejects_an_empty_realm(): void
    {
        $this->expectException(DomainException::class);

        new AllowConfiguredRealmPolicy('');
    }

    public function test_it_rejects_a_realm_shorter_than_two_characters(): void
    {
        $this->expectException(DomainException::class);

        new AllowConfiguredRealmPolicy('A');
    }

    public function test_it_rejects_a_realm_longer_than_six_characters(): void
    {
        $this->expectException(DomainException::class);

        new AllowConfiguredRealmPolicy('ABCDEFG');
    }

    public function test_it_rejects_a_lowercase_realm(): void
    {
        $this->expectException(DomainException::class);

        new AllowConfiguredRealmPolicy('gat');
    }
}
