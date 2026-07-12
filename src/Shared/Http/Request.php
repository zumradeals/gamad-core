<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $query
     * @param array<string, string> $pathParameters
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers = [],
        public array $query = [],
        public string $body = '',
        public array $pathParameters = [],
        public ?AuthenticatedActor $actor = null,
    ) {
    }

    public function withPathParameters(array $parameters): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->headers,
            $this->query,
            $this->body,
            $parameters,
            $this->actor,
        );
    }

    public function withActor(AuthenticatedActor $actor): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->headers,
            $this->query,
            $this->body,
            $this->pathParameters,
            $actor,
        );
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp($header, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
