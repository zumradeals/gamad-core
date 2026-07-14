<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Http;

use Gamad\Core\Shared\Http\RouteDefinition;

final readonly class PersonsAndAccountsRoutes
{
    /**
     * `requiredScopes` here is PersonsAndAccountsHttpKernel's own convention
     * for "a valid session is required" (non-empty) vs. public (empty) — see
     * that kernel's docblock. This context has no finer-grained scopes.
     *
     * @return list<RouteDefinition>
     */
    public static function forController(PersonsAndAccountsHttpController $controller): array
    {
        return [
            new RouteDefinition('POST', '/persons', ['session'], $controller->registerPerson(...), 'registerPerson'),
            new RouteDefinition('GET', '/persons/{personId}', ['session'], $controller->getPerson(...), 'getPerson'),
            new RouteDefinition('POST', '/persons/{personId}/account', ['session'], $controller->registerUserAccount(...), 'registerUserAccount'),
            new RouteDefinition('POST', '/accounts/{accountId}/password', ['session'], $controller->setPassword(...), 'setPassword'),
            new RouteDefinition('POST', '/auth/login', [], $controller->login(...), 'login'),
            new RouteDefinition('POST', '/sessions/{sessionId}/revoke', ['session'], $controller->revokeSession(...), 'revokeSession'),
        ];
    }
}
