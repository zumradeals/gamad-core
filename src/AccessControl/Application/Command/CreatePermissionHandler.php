<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application\Command;

use Gamad\Core\AccessControl\Application\Exception\PermissionAlreadyExists;
use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\PermissionRepository;
use InvalidArgumentException;

/**
 * Permission has no lifecycle or events (GENESIS-014 §B) — a plain save,
 * no persister/outbox/transaction manager involved, unlike Role or
 * RoleAssignment.
 */
final readonly class CreatePermissionHandler
{
    // Hyphens are allowed (GENESIS-013 §3.5 defines "dead-letter:replay").
    private const NAME_PATTERN = '/^[a-z][a-z_-]*(:[a-z][a-z_-]*){1,2}$/';

    public function __construct(private PermissionRepository $permissions)
    {
    }

    public function __invoke(CreatePermission $command): Permission
    {
        if (preg_match(self::NAME_PATTERN, $command->name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid permission name "%s" — expected "domain:action" or "domain:object:action".', $command->name));
        }

        if ($this->permissions->findByName($command->name) !== null) {
            throw PermissionAlreadyExists::withName($command->name);
        }

        $permission = new Permission(PermissionId::generate(), $command->name, $command->description);
        $this->permissions->save($permission);

        return $permission;
    }
}
