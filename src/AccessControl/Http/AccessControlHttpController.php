<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Http;

use Gamad\Core\AccessControl\Application\AccessControlLookup;
use Gamad\Core\AccessControl\Application\Command\AddPermissionToRole;
use Gamad\Core\AccessControl\Application\Command\AddPermissionToRoleHandler;
use Gamad\Core\AccessControl\Application\Command\AssignRole;
use Gamad\Core\AccessControl\Application\Command\AssignRoleHandler;
use Gamad\Core\AccessControl\Application\Command\CreatePermission;
use Gamad\Core\AccessControl\Application\Command\CreatePermissionHandler;
use Gamad\Core\AccessControl\Application\Command\CreateRole;
use Gamad\Core\AccessControl\Application\Command\CreateRoleHandler;
use Gamad\Core\AccessControl\Application\Command\EvaluateAccess;
use Gamad\Core\AccessControl\Application\Command\EvaluateAccessHandler;
use Gamad\Core\AccessControl\Application\Command\RevokeRole;
use Gamad\Core\AccessControl\Application\Command\RevokeRoleHandler;
use Gamad\Core\AccessControl\Application\Exception\OrganizationNotFound;
use Gamad\Core\AccessControl\Application\Exception\PermissionAlreadyExists;
use Gamad\Core\AccessControl\Application\Exception\PermissionNotFound;
use Gamad\Core\AccessControl\Application\Exception\PersonNotFound;
use Gamad\Core\AccessControl\Application\Exception\RoleAlreadyExists;
use Gamad\Core\AccessControl\Application\Exception\RoleAssignmentAlreadyActive;
use Gamad\Core\AccessControl\Application\Exception\RoleAssignmentNotFound;
use Gamad\Core\AccessControl\Application\Exception\RoleNotFound;
use Gamad\Core\AccessControl\Application\Exception\SelfAssignmentNotAllowed;
use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionRepository;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleRepository;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\Response;
use InvalidArgumentException;
use ValueError;

/**
 * Every route requires a session (OrganizationsAndMembershipsHttpKernel's
 * convention, Task 6) AND the matching permission (Task 7). Most routes
 * gate at this HTTP boundary via requirePermission(), evaluated against
 * $realmRootOrganizationId — the only context these meta-administrative
 * actions have, since they operate on Access Control's own vocabulary
 * (roles, permissions), not on any specific organization instance.
 * POST /role-assignments is the exception: AssignRoleHandler enforces
 * `role:assign` itself, against the *target* organization (Task 5) — this
 * controller does not gate it a second time.
 *
 * Permission mapping (GENESIS-013 §3.4 defines five: role:create,
 * role:read, permission:assign, role:assign, role:revoke — there is no
 * dedicated "permission:create"; creating a Permission is bundled under
 * role:create, the same authority that defines the role vocabulary).
 */
final readonly class AccessControlHttpController
{
    public function __construct(
        private CreatePermissionHandler $createPermission,
        private PermissionRepository $permissions,
        private CreateRoleHandler $createRole,
        private RoleRepository $roles,
        private AddPermissionToRoleHandler $addPermissionToRole,
        private AssignRoleHandler $assignRole,
        private RevokeRoleHandler $revokeRole,
        private EvaluateAccessHandler $evaluateAccess,
        private AccessControlLookup $lookup,
        private string $realmRootOrganizationId,
    ) {
    }

    public function createPermission(Request $request): Response
    {
        try {
            $this->requirePermission($request, 'role:create');
            $body = $this->decode($request);
            $permission = ($this->createPermission)(new CreatePermission(
                (string) ($body['name'] ?? ''),
                (string) ($body['description'] ?? ''),
            ));
        } catch (PermissionAlreadyExists $exception) {
            return Response::json(409, ['error' => 'permission_already_exists', 'detail' => $exception->getMessage()]);
        } catch (AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        }

        return Response::json(201, $this->serializePermission($permission));
    }

    public function listPermissions(Request $request): Response
    {
        try {
            $this->requirePermission($request, 'role:read');
        } catch (AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        }

        $items = array_map($this->serializePermission(...), $this->permissions->findAll());

        return Response::json(200, ['items' => $items]);
    }

    public function createRole(Request $request): Response
    {
        try {
            $this->requirePermission($request, 'role:create');
            $body = $this->decode($request);
            $role = ($this->createRole)(new CreateRole(
                (string) ($body['name'] ?? ''),
                (string) ($body['scope'] ?? ''),
            ));
        } catch (RoleAlreadyExists $exception) {
            return Response::json(409, ['error' => 'role_already_exists', 'detail' => $exception->getMessage()]);
        } catch (ValueError) {
            return Response::json(400, ['error' => 'invalid_scope', 'detail' => 'Unknown role scope.']);
        } catch (AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        }

        return Response::json(201, $this->serializeRole($role));
    }

    public function listRoles(Request $request): Response
    {
        try {
            $this->requirePermission($request, 'role:read');
        } catch (AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        }

        $items = array_map($this->serializeRole(...), $this->roles->findAll());

        return Response::json(200, ['items' => $items]);
    }

    public function addPermissionToRole(Request $request): Response
    {
        try {
            $this->requirePermission($request, 'permission:assign');
            $body = $this->decode($request);
            $role = ($this->addPermissionToRole)(new AddPermissionToRole(
                $request->pathParameters['roleId'],
                (string) ($body['permission_id'] ?? ''),
            ));
        } catch (RoleNotFound|PermissionNotFound $exception) {
            return Response::json(404, ['error' => 'not_found', 'detail' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return Response::json(400, ['error' => 'invalid_request', 'detail' => $exception->getMessage()]);
        } catch (AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        }

        return Response::json(200, $this->serializeRole($role));
    }

    public function assignRole(Request $request): Response
    {
        $body = $this->decode($request);

        try {
            $assignment = ($this->assignRole)(new AssignRole(
                (string) ($body['role_id'] ?? ''),
                (string) ($body['person_id'] ?? ''),
                (string) ($body['organization_id'] ?? ''),
                $this->actorPersonId($request),
            ));
        } catch (RoleNotFound $exception) {
            return Response::json(404, ['error' => 'role_not_found', 'detail' => $exception->getMessage()]);
        } catch (PersonNotFound|OrganizationNotFound $exception) {
            return Response::json(404, ['error' => 'not_found', 'detail' => $exception->getMessage()]);
        } catch (RoleAssignmentAlreadyActive $exception) {
            return Response::json(409, ['error' => 'role_assignment_already_active', 'detail' => $exception->getMessage()]);
        } catch (SelfAssignmentNotAllowed|AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return Response::json(400, ['error' => 'invalid_request', 'detail' => $exception->getMessage()]);
        }

        return Response::json(201, $this->serializeAssignment($assignment));
    }

    public function revokeRole(Request $request): Response
    {
        try {
            $this->requirePermission($request, 'role:revoke');
            $assignment = ($this->revokeRole)(new RevokeRole($request->pathParameters['assignmentId']));
        } catch (RoleAssignmentNotFound $exception) {
            return Response::json(404, ['error' => 'role_assignment_not_found', 'detail' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return Response::json(400, ['error' => 'invalid_request', 'detail' => $exception->getMessage()]);
        } catch (AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        }

        return Response::json(200, $this->serializeAssignment($assignment));
    }

    public function evaluateAccess(Request $request): Response
    {
        try {
            $this->requirePermission($request, 'runtime:health:read');
            $body = $this->decode($request);
            $decision = ($this->evaluateAccess)(new EvaluateAccess(
                (string) ($body['actor_id'] ?? ''),
                (string) ($body['action'] ?? ''),
                (string) ($body['context_id'] ?? ''),
            ));
        } catch (AccessDenied $exception) {
            return Response::json(403, ['error' => 'access_denied', 'detail' => $exception->getMessage()]);
        }

        return Response::json(200, ['decision' => $decision->allowed ? 'ALLOW' : 'DENY', 'reason' => $decision->reason]);
    }

    private function requirePermission(Request $request, string $permission): void
    {
        $decision = ($this->evaluateAccess)(new EvaluateAccess($this->actorPersonId($request), $permission, $this->realmRootOrganizationId));
        if (!$decision->allowed) {
            throw AccessDenied::forDecision($permission, $decision->reason);
        }
    }

    /**
     * The session actor is a UserAccountId (SessionTokenAuthenticator);
     * every check in this context is keyed by person id, so it is resolved
     * once here rather than at every call site.
     */
    private function actorPersonId(Request $request): string
    {
        $accountId = $request->actor?->actorId ?? '';

        return $this->lookup->resolveAccountToPerson($accountId) ?? '';
    }

    /** @return array<string, mixed> */
    private function decode(Request $request): array
    {
        $decoded = json_decode($request->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function serializePermission(Permission $permission): array
    {
        return [
            'permission_id' => (string) $permission->id,
            'name' => $permission->name,
            'description' => $permission->description,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeRole(Role $role): array
    {
        return [
            'role_id' => (string) $role->id(),
            'name' => $role->name(),
            'scope' => $role->scope()->value,
            'status' => $role->status()->value,
            'permission_ids' => array_map(static fn ($id): string => (string) $id, $role->permissionIds()),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeAssignment(RoleAssignment $assignment): array
    {
        return [
            'assignment_id' => (string) $assignment->id(),
            'role_id' => (string) $assignment->roleId(),
            'person_id' => $assignment->personId(),
            'organization_id' => $assignment->organizationId(),
            'status' => $assignment->status()->value,
            'assigned_at' => $assignment->assignedAt()->format(DATE_ATOM),
            'revoked_at' => $assignment->revokedAt()?->format(DATE_ATOM),
        ];
    }
}
