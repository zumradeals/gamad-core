<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

use DateTimeImmutable;

/**
 * Sub-entity of UserAccount (GENESIS-010 §B) — never an independent aggregate,
 * never constructed or persisted outside its owning UserAccount.
 */
final readonly class AuthenticationMethod
{
    public function __construct(
        private AuthenticationMethodId $id,
        private AuthenticationMethodType $type,
        private string $credentialRef,
        private DateTimeImmutable $addedAt,
    ) {
    }

    public function id(): AuthenticationMethodId
    {
        return $this->id;
    }

    public function type(): AuthenticationMethodType
    {
        return $this->type;
    }

    /** Never the secret itself — a hash or an external reference (ADR-0018 §1). */
    public function credentialRef(): string
    {
        return $this->credentialRef;
    }

    public function addedAt(): DateTimeImmutable
    {
        return $this->addedAt;
    }
}
