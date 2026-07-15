<?php

declare(strict_types=1);

namespace Gamad\Console\Lib;

/**
 * Single server-side exit point to the GAMAD Core API (ADR-0016 §4).
 * The browser never talks to the Core directly; every call attaches a
 * bearer token read from the PHP session.
 *
 * ADR-0019 — two credential-bound entry points, `callAsAdmin()` (ADR-0011
 * bootstrap token) and `callAsPerson()` (ADR-0018 Persons session). Each
 * reads only its own Session slot and, on a 401, redirects to its own login
 * screen — never the other's. There is no generic "call" that would pick a
 * credential implicitly. `get()`/`post()` remain as low-level primitives
 * for the two moments no session credential exists yet: validating a
 * freshly pasted admin token, and the public `/auth/login` call itself.
 */
final class CoreApiClient
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (getenv('GAMAD_CORE_API_BASE_URL') ?: 'https://gamad.dgafrique.com'), '/');
    }

    /** @param array<string, scalar> $query */
    public function get(string $path, array $query = [], ?string $token = null): CoreApiResponse
    {
        return $this->request('GET', $path, $query, null, [], $token);
    }

    /** @param array<string, mixed> $body */
    public function post(string $path, array $body = [], ?string $token = null): CoreApiResponse
    {
        return $this->request('POST', $path, [], $body, [], $token);
    }

    /**
     * ADR-0019 — the only entry point for admin/runtime routes (ADR-0011).
     * Reads `admin_token` from its own session slot; a 401 destroys that
     * credential and redirects to /login, never to /login-operateur.
     *
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders
     */
    public function callAsAdmin(string $method, string $path, array $query = [], ?array $body = null, array $extraHeaders = []): CoreApiResponse
    {
        return $this->callWithSessionCredential(
            $method,
            $path,
            $query,
            $body,
            $extraHeaders,
            Session::adminToken(),
            static fn () => Session::destroyAdmin(),
            '/login',
        );
    }

    /**
     * ADR-0019 — the only entry point for Persons routes (ADR-0018 session).
     * Reads `person_session_token` from its own session slot; a 401
     * destroys that credential and redirects to /login-operateur, never to
     * /login.
     *
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders
     */
    public function callAsPerson(string $method, string $path, array $query = [], ?array $body = null, array $extraHeaders = []): CoreApiResponse
    {
        return $this->callWithSessionCredential(
            $method,
            $path,
            $query,
            $body,
            $extraHeaders,
            Session::personToken(),
            static fn () => Session::destroyPerson(),
            '/login-operateur',
        );
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders
     */
    private function callWithSessionCredential(
        string $method,
        string $path,
        array $query,
        ?array $body,
        array $extraHeaders,
        ?string $token,
        callable $destroySessionCredential,
        string $loginPath,
    ): CoreApiResponse {
        if ($token !== null) {
            $response = $this->request($method, $path, $query, $body, $extraHeaders, $token);
            if (!$response->unauthorized()) {
                return $response;
            }
        }

        $destroySessionCredential();
        header('Location: ' . $loginPath);
        exit;
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $body
     * @param array<string, string> $extraHeaders
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null, array $extraHeaders = [], ?string $token = null): CoreApiResponse
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
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
}
