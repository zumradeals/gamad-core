<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Http;

use Gamad\Core\Shared\Http\Response;
use RuntimeException;

/**
 * Deliberately not an extension of Shared's OpenApiResponseValidator, and
 * deliberately its own class rather than reusing
 * OrganizationsAndMembershipsResponseValidator or any other context's —
 * this context never imports another bounded context's Http namespace
 * (ADR-0013 boundary, extended by DIRECTIVE-007 Task 8).
 */
final readonly class AccessControlResponseValidator
{
    public function validate(string $operationId, Response $response): void
    {
        if ($response->body === '') {
            throw new RuntimeException('JSON response body cannot be empty.');
        }

        $payload = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Response payload must be a JSON object or array.');
        }

        if ($response->status >= 400) {
            if (!isset($payload['error']) && !isset($payload['type'])) {
                throw new RuntimeException('Error response must expose a stable error identifier.');
            }

            return;
        }

        $required = match ($operationId) {
            'createPermission' => ['permission_id', 'name', 'description'],
            'listPermissions', 'listRoles' => ['items'],
            'createRole', 'addPermissionToRole' => ['role_id', 'name', 'scope', 'status'],
            'assignRole', 'revokeRole' => ['assignment_id', 'role_id', 'person_id', 'organization_id', 'status'],
            'evaluateAccess' => ['decision', 'reason'],
            default => throw new RuntimeException(sprintf('Unknown OpenAPI operation %s.', $operationId)),
        };

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new RuntimeException(sprintf('Response field %s is required.', $field));
            }
        }
    }
}
