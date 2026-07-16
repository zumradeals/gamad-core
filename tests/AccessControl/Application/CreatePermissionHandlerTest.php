<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Application;

use Gamad\Core\AccessControl\Application\Command\CreatePermission;
use Gamad\Core\AccessControl\Application\Command\CreatePermissionHandler;
use Gamad\Core\AccessControl\Application\Exception\PermissionAlreadyExists;
use Gamad\Core\AccessControl\Infrastructure\Persistence\InMemoryPermissionRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CreatePermissionHandlerTest extends TestCase
{
    public function test_it_creates_a_permission(): void
    {
        $permissions = new InMemoryPermissionRepository();
        $handler = new CreatePermissionHandler($permissions);

        $permission = $handler(new CreatePermission('identity:read', 'Read an identity'));

        self::assertSame('identity:read', $permission->name);
        self::assertNotNull($permissions->findByName('identity:read'));
    }

    public function test_it_rejects_a_malformed_permission_name(): void
    {
        $handler = new CreatePermissionHandler(new InMemoryPermissionRepository());

        $this->expectException(InvalidArgumentException::class);

        $handler(new CreatePermission('not a permission', 'Invalid'));
    }

    public function test_it_rejects_a_duplicate_name(): void
    {
        $permissions = new InMemoryPermissionRepository();
        $handler = new CreatePermissionHandler($permissions);
        $handler(new CreatePermission('identity:read', 'Read an identity'));

        $this->expectException(PermissionAlreadyExists::class);

        $handler(new CreatePermission('identity:read', 'Duplicate'));
    }
}
