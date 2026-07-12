<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 5,
        private int $baseDelaySeconds = 5,
        private int $maxDelaySeconds = 300,
    ) {
        if ($maxAttempts < 1 || $baseDelaySeconds < 1 || $maxDelaySeconds < $baseDelaySeconds) {
            throw new InvalidArgumentException('Invalid retry policy configuration.');
        }
    }

    public function mustDeadLetter(int $attemptsAfterFailure): bool
    {
        return $attemptsAfterFailure >= $this->maxAttempts;
    }

    public function nextAvailableAt(DateTimeImmutable $failedAt, int $attemptsAfterFailure): DateTimeImmutable
    {
        $exponent = max(0, $attemptsAfterFailure - 1);
        $delay = min($this->maxDelaySeconds, $this->baseDelaySeconds * (2 ** $exponent));

        return $failedAt->add(new DateInterval(sprintf('PT%dS', $delay)));
    }
}
