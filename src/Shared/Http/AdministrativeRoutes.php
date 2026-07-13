<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

final readonly class AdministrativeRoutes
{
    /** @return list<RouteDefinition> */
    public static function forController(AdministrativeRuntimeController $controller): array
    {
        return [
            new RouteDefinition('GET', '/admin/runtime/health', ['core.runtime.health.read'], $controller->health(...), 'getRuntimeHealthSummary'),
            new RouteDefinition('GET', '/admin/runtime/outbox', ['core.outbox.dashboard.read'], $controller->outbox(...), 'getOutboxDashboard'),
            new RouteDefinition('GET', '/admin/runtime/audit/verify', ['core.audit.verify.read'], $controller->verifyAudit(...), 'verifyAuditChain'),
            new RouteDefinition('GET', '/admin/runtime/dead-letters', ['core.outbox.dead_letter.read'], $controller->listDeadLetters(...), 'listDeadLetters'),
            new RouteDefinition('GET', '/admin/runtime/dead-letters/{messageId}', ['core.outbox.dead_letter.read'], $controller->inspectDeadLetter(...), 'inspectDeadLetter'),
            new RouteDefinition('POST', '/admin/runtime/dead-letters/{messageId}/replay', ['core.outbox.dead_letter.replay'], $controller->replayDeadLetter(...), 'replayDeadLetter'),
        ];
    }
}
