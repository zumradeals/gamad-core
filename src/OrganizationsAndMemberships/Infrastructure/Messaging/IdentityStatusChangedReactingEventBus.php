<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Messaging;

use Gamad\Core\OrganizationsAndMemberships\Application\ReactToIdentityStatusChangedForOrganizations;
use Gamad\Core\Shared\Messaging\EventBus;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;

/**
 * Decorates the real event bus with the Task 8 reaction — this is the
 * outbox-worker's actual publication point, the same place any other
 * consumer of `identity.status_changed.v1` would receive it, never a
 * shortcut into the Identity Registry's own tables.
 */
final readonly class IdentityStatusChangedReactingEventBus implements EventBus
{
    private const string IDENTITY_STATUS_CHANGED_EVENT = 'identity.status_changed.v1';

    public function __construct(
        private EventBus $inner,
        private ReactToIdentityStatusChangedForOrganizations $reactor,
    ) {
    }

    public function publish(PendingOutboxMessage $message): void
    {
        $this->inner->publish($message);

        if ($message->eventName === self::IDENTITY_STATUS_CHANGED_EVENT) {
            $this->reactor->handle((string) $message->payload['identity_id'], (string) $message->payload['to']);
        }
    }
}
