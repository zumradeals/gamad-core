<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Http;

use Gamad\Core\Shared\Http\RouteDefinition;

final readonly class AccessControlRoutes
{
    /**
     * `requiredScopes` here is AccessControlHttpKernel's own convention for
     * "a valid session is required" — every route requires one (Task 7: no
     * route in this context is public). The finer-grained permission check
     * happens inside the controller (or, for assignRole, inside
     * AssignRoleHandler itself).
     *
     * @return list<RouteDefinition>
     */
    public static function forController(AccessControlHttpController $controller): array
    {
        return [
            new RouteDefinition('POST', '/permissions', ['session'], $controller->createPermission(...), 'createPermission'),
            new RouteDefinition('GET', '/permissions', ['session'], $controller->listPermissions(...), 'listPermissions'),
            new RouteDefinition('POST', '/roles', ['session'], $controller->createRole(...), 'createRole'),
            new RouteDefinition('GET', '/roles', ['session'], $controller->listRoles(...), 'listRoles'),
            new RouteDefinition('POST', '/roles/{roleId}/permissions', ['session'], $controller->addPermissionToRole(...), 'addPermissionToRole'),
            new RouteDefinition('POST', '/role-assignments', ['session'], $controller->assignRole(...), 'assignRole'),
            new RouteDefinition('DELETE', '/role-assignments/{assignmentId}', ['session'], $controller->revokeRole(...), 'revokeRole'),
            new RouteDefinition('POST', '/access/evaluate', ['session'], $controller->evaluateAccess(...), 'evaluateAccess'),
        ];
    }
}
