<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

interface OutboxDashboardRepository
{
    public function snapshot(): OutboxDashboard;
}
