<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Http;

use Gamad\Core\Shared\Http\RouteDefinition;

final readonly class IdentityRoutes
{
    /** @return list<RouteDefinition> */
    public static function forController(IdentityHttpController $controller): array
    {
        return [
            new RouteDefinition('POST', '/identities', ['core.identity.register'], $controller->register(...), 'registerIdentity'),
            new RouteDefinition('GET', '/identities/{identityId}', ['core.identity.read'], $controller->get(...), 'getIdentity'),
            new RouteDefinition('POST', '/identities/{identityId}/{transition}', ['core.identity.lifecycle.manage'], $controller->transition(...), 'transitionIdentity'),
        ];
    }
}
