<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

/**
 * @see docs/06-decisions/ADR-0011-authorization-bootstrap-provisoire.md — mécanisme provisoire, non représentatif du futur Access Control.
 */
final readonly class ScopeAuthorizationMiddleware
{
    /** @param list<string> $requiredScopes */
    public function authorize(?AuthenticatedActor $actor, array $requiredScopes): bool
    {
        if ($actor === null) {
            return false;
        }

        foreach ($requiredScopes as $scope) {
            if (!$actor->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }
}
