<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

final readonly class AuthenticatedActor
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $actorId,
        public array $scopes,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
