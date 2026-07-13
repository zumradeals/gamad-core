<?php

declare(strict_types=1);

namespace Gamad\Console\Lib;

/**
 * Single server-side exit point to the GAMAD Core API (ADR-0016 §4).
 * The browser never talks to the Core directly; every call attaches the
 * bearer token held in the PHP session.
 */
final class CoreApiClient
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null, private readonly ?string $token = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (getenv('GAMAD_CORE_API_BASE_URL') ?: 'https://gamad.dgafrique.com'), '/');
    }

    /** @param array<string, scalar> $query */
    public function get(string $path, array $query = []): CoreApiResponse
    {
        return $this->request('GET', $path, $query);
    }

    /** @param array<string, mixed> $body */
    public function post(string $path, array $body = []): CoreApiResponse
    {
        return $this->request('POST', $path, [], $body);
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null, array $extraHeaders = []): CoreApiResponse
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        foreach ($extraHeaders as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($handle);
        if ($raw === false) {
            curl_close($handle);
            return new CoreApiResponse(0, null);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        $decoded = null;
        if ($raw !== '') {
            $decoded = json_decode((string) $raw, true);
            $decoded = is_array($decoded) ? $decoded : null;
        }

        return new CoreApiResponse($status, $decoded, (string) $raw);
    }

    public function withIdempotencyKey(string $path, array $body): CoreApiResponse
    {
        return $this->request('POST', $path, [], $body, [
            'Idempotency-Key' => bin2hex(random_bytes(16)),
        ]);
    }
}
