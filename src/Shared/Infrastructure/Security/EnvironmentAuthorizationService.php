<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Security;

use Gamad\Core\Shared\Security\AuthorizationService;

final readonly class EnvironmentAuthorizationService implements AuthorizationService
{
    /** @param array<string, list<string>> $permissionsByActor */
    public function __construct(private array $permissionsByActor)
    {
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self(is_array($decoded) ? $decoded : []);
    }

    public function isAllowed(string $actorId, string $permission): bool
    {
        $permissions = $this->permissionsByActor[$actorId] ?? [];

        return in_array($permission, $permissions, true);
    }
}
