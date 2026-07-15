<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Http;

use Gamad\Core\Shared\Http\RouteDefinition;

final readonly class OrganizationsAndMembershipsRoutes
{
    /**
     * `requiredScopes` here is OrganizationsAndMembershipsHttpKernel's own
     * convention for "a valid session is required" — every route requires
     * one (DIRECTIVE-006 Task 6: no route in this context is public).
     *
     * @return list<RouteDefinition>
     */
    public static function forController(OrganizationsAndMembershipsHttpController $controller): array
    {
        return [
            new RouteDefinition('POST', '/organizations', ['session'], $controller->createOrganization(...), 'createOrganization'),
            new RouteDefinition('GET', '/organizations/{orgId}', ['session'], $controller->getOrganization(...), 'getOrganization'),
            new RouteDefinition('GET', '/organizations/{orgId}/children', ['session'], $controller->getOrganizationChildren(...), 'getOrganizationChildren'),
            new RouteDefinition('POST', '/organizations/{orgId}/departments', ['session'], $controller->createDepartment(...), 'createDepartment'),
            new RouteDefinition('POST', '/organizations/{orgId}/memberships', ['session'], $controller->createMembership(...), 'createMembership'),
            new RouteDefinition('GET', '/organizations/{orgId}/memberships', ['session'], $controller->listOrganizationMemberships(...), 'listOrganizationMemberships'),
            new RouteDefinition('POST', '/memberships/{membershipId}/suspend', ['session'], $controller->suspendMembership(...), 'suspendMembership'),
            new RouteDefinition('POST', '/memberships/{membershipId}/resume', ['session'], $controller->resumeMembership(...), 'resumeMembership'),
            new RouteDefinition('POST', '/memberships/{membershipId}/end', ['session'], $controller->endMembership(...), 'endMembership'),
        ];
    }
}
