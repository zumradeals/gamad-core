<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Messaging;

use Gamad\Core\OrganizationsAndMemberships\Application\ReactToOrganizationSuspended;
use Gamad\Core\Shared\Messaging\EventBus;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;

/**
 * Decorates the real event bus with the Task 4 cascade (GENESIS-011 §4
 * invariant 9) — suspending an Organization's active memberships happens
 * only when `organization.suspended.v1` is actually published, exactly as
 * it would for any other consumer of that event.
 */
final readonly class OrganizationSuspendedReactingEventBus implements EventBus
{
    private const string ORGANIZATION_SUSPENDED_EVENT = 'organization.suspended.v1';

    public function __construct(
        private EventBus $inner,
        private ReactToOrganizationSuspended $reactor,
    ) {
    }

    public function publish(PendingOutboxMessage $message): void
    {
        $this->inner->publish($message);

        if ($message->eventName === self::ORGANIZATION_SUSPENDED_EVENT) {
            $this->reactor->handle((string) $message->payload['organization_id']);
        }
    }
}
