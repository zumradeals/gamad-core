<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application\Exception;

use RuntimeException;

final class IdentityAlreadyExists extends RuntimeException
{
    public static function withId(string $identityId): self
    {
        return new self(sprintf('Identity "%s" already exists.', $identityId));
    }
}
