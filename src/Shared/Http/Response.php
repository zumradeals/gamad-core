<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = ['Content-Type' => 'application/json'],
    ) {
    }

    /** @param mixed $payload */
    public static function json(int $status, mixed $payload): self
    {
        return new self($status, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
