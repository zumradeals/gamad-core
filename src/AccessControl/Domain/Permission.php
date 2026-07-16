<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Domain;

/**
 * GENESIS-014 §B — not an aggregate: no lifecycle (no suspend/reactivate),
 * no sub-entities. A misnamed permission is deprecated and replaced by a
 * new one, never edited in place, so this value object is immutable.
 */
final readonly class Permission
{
    public function __construct(
        public PermissionId $id,
        public string $name,
        public string $description,
    ) {
    }
}
