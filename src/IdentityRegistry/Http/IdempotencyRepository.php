<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Http;

interface IdempotencyRepository
{
    /** @return array{request_hash:string,status:int,response:string}|null */
    public function find(string $actorId, string $key): ?array;

    public function store(string $actorId, string $key, string $requestHash, int $status, string $response): void;
}
