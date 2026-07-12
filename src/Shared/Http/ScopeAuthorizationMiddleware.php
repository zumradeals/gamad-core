<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

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
