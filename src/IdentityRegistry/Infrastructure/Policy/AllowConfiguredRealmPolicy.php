<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Infrastructure\Policy;

use DomainException;
use Gamad\Core\IdentityRegistry\Application\CoreRealmProvider;

/**
 * ADR-0017 §2/§3 — validates GAMAD_CORE_REALM once, at construction (fail fast),
 * so a misconfigured or empty realm can never silently make it into a minted id.
 */
final readonly class AllowConfiguredRealmPolicy implements CoreRealmProvider
{
    private const PATTERN = '/^[A-Z0-9]{2,6}$/';

    public function __construct(private string $realm)
    {
        if (preg_match(self::PATTERN, $realm) !== 1) {
            throw new DomainException(sprintf('GAMAD_CORE_REALM "%s" is not a valid realm code.', $realm));
        }
    }

    public function realm(): string
    {
        return $this->realm;
    }
}
