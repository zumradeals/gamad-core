<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

final readonly class ProblemDetails
{
    public static function response(
        int $status,
        string $type,
        string $title,
        string $detail,
        string $requestId,
    ): Response {
        return Response::json($status, [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'request_id' => $requestId,
        ]);
    }
}
