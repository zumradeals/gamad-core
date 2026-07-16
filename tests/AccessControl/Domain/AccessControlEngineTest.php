<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Domain;

use Gamad\Core\AccessControl\Domain\AccessControlEngine;
use Gamad\Core\AccessControl\Domain\AccessRequest;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleGrant;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\Shared\Contract\IdentityId;
use PHPUnit\Framework\TestCase;

final class AccessControlEngineTest extends TestCase
{
    public function test_it_allows_when_a_matching_organization_scoped_role_exists(): void
    {
        $engine = new AccessControlEngine();
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $grant = new RoleGrant($role, ['membership:create'], 'GAM-GAT-ORG-000001');

        $decision = $engine->evaluate(
            new AccessRequest(new IdentityId('GAM-GAT-PER-000001'), 'membership:create', new IdentityId('GAM-GAT-ORG-000001')),
            [$grant],
        );

        self::assertTrue($decision->allowed);
        self::assertSame('org_admin', $decision->reason);
    }

    public function test_it_denies_when_no_role_covers_the_action(): void
    {
        $engine = new AccessControlEngine();
        $role = Role::create(RoleId::generate(), 'member_viewer', RoleScope::Organization);
        $grant = new RoleGrant($role, ['membership:read'], 'GAM-GAT-ORG-000001');

        $decision = $engine->evaluate(
            new AccessRequest(new IdentityId('GAM-GAT-PER-000001'), 'membership:create', new IdentityId('GAM-GAT-ORG-000001')),
            [$grant],
        );

        self::assertFalse($decision->allowed);
        self::assertSame('no_matching_role', $decision->reason);
    }

    public function test_it_denies_when_the_role_exists_but_not_in_the_requested_context(): void
    {
        $engine = new AccessControlEngine();
        $role = Role::create(RoleId::generate(), 'org_admin', RoleScope::Organization);
        $grant = new RoleGrant($role, ['membership:create'], 'GAM-GAT-ORG-000002');

        $decision = $engine->evaluate(
            new AccessRequest(new IdentityId('GAM-GAT-PER-000001'), 'membership:create', new IdentityId('GAM-GAT-ORG-000001')),
            [$grant],
        );

        self::assertFalse($decision->allowed);
        self::assertSame('no_matching_role', $decision->reason);
    }

    public function test_it_allows_transversally_for_a_realm_scoped_superadmin_regardless_of_context(): void
    {
        $engine = new AccessControlEngine();
        $role = Role::create(RoleId::generate(), 'superadmin', RoleScope::Realm);
        $grant = new RoleGrant($role, ['membership:create'], 'GAM-GAT-ORG-000001');

        $decision = $engine->evaluate(
            new AccessRequest(new IdentityId('GAM-GAT-PER-000001'), 'membership:create', new IdentityId('GAM-GAT-ORG-999999')),
            [$grant],
        );

        self::assertTrue($decision->allowed);
        self::assertSame('superadmin', $decision->reason);
    }

    public function test_it_denies_when_there_are_no_grants_at_all(): void
    {
        $engine = new AccessControlEngine();

        $decision = $engine->evaluate(
            new AccessRequest(new IdentityId('GAM-GAT-PER-000001'), 'membership:create', new IdentityId('GAM-GAT-ORG-000001')),
            [],
        );

        self::assertFalse($decision->allowed);
        self::assertSame('no_matching_role', $decision->reason);
    }
}
