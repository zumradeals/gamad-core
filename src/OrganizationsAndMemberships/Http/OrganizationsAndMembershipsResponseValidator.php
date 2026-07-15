<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Http;

use Gamad\Core\Shared\Http\Response;
use RuntimeException;

/**
 * Deliberately not an extension of Shared's OpenApiResponseValidator — that
 * class must never learn this context's vocabulary (ADR-0013 §2), and this
 * context must never import PersonsAndAccountsResponseValidator either
 * (ADR-0013 boundary — DIRECTIVE-006 Task 7).
 */
final readonly class OrganizationsAndMembershipsResponseValidator
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
            'createOrganization', 'getOrganization' => ['organization_id', 'name', 'status', 'founded_at'],
            'getOrganizationChildren', 'listOrganizationMemberships' => ['items'],
            'createDepartment' => ['department_id', 'name', 'status'],
            'createMembership', 'suspendMembership', 'resumeMembership', 'endMembership' => ['membership_id', 'person_id', 'organization_id', 'membership_type', 'status', 'started_at'],
            default => throw new RuntimeException(sprintf('Unknown OpenAPI operation %s.', $operationId)),
        };

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new RuntimeException(sprintf('Response field %s is required.', $field));
            }
        }
    }
}
