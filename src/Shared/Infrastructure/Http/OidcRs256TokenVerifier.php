<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use Gamad\Core\Shared\Http\AuthenticatedActor;
use Gamad\Core\Shared\Http\JwksProvider;
use Gamad\Core\Shared\Http\TokenVerifier;

final readonly class OidcRs256TokenVerifier implements TokenVerifier
{
    public function __construct(
        private JwksProvider $jwks,
        private string $issuer,
        private string $audience,
        private int $clockSkewSeconds = 60,
    ) {
    }

    public function verify(string $accessToken): ?AuthenticatedActor
    {
        $parts = explode('.', $accessToken);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);
        $signature = $this->decodeBase64Url($encodedSignature);

        if ($header === null || $payload === null || $signature === null) {
            return null;
        }

        if (($header['alg'] ?? null) !== 'RS256' || !isset($header['kid'])) {
            return null;
        }

        $jwk = $this->jwks->keys()[(string) $header['kid']] ?? null;
        if (!is_array($jwk)) {
            return null;
        }

        $verified = openssl_verify(
            $encodedHeader . '.' . $encodedPayload,
            $signature,
            RsaJwk::toPem($jwk),
            OPENSSL_ALGO_SHA256,
        );
        if ($verified !== 1 || !$this->claimsAreValid($payload)) {
            return null;
        }

        $actorId = $payload['sub'] ?? null;
        if (!is_string($actorId) || $actorId === '') {
            return null;
        }

        $scopeClaim = $payload['scope'] ?? $payload['scp'] ?? [];
        $scopes = is_string($scopeClaim)
            ? preg_split('/\s+/', trim($scopeClaim), -1, PREG_SPLIT_NO_EMPTY)
            : (is_array($scopeClaim) ? array_values(array_map('strval', $scopeClaim)) : []);

        return new AuthenticatedActor($actorId, $scopes ?: []);
    }

    /** @param array<string, mixed> $claims */
    private function claimsAreValid(array $claims): bool
    {
        $now = time();
        $issuer = $claims['iss'] ?? null;
        $audience = $claims['aud'] ?? null;
        $expiresAt = $claims['exp'] ?? null;
        $notBefore = $claims['nbf'] ?? null;

        if (!is_string($issuer) || !hash_equals(rtrim($this->issuer, '/'), rtrim($issuer, '/'))) {
            return false;
        }

        $audiences = is_array($audience) ? array_map('strval', $audience) : [is_scalar($audience) ? (string) $audience : ''];
        if (!in_array($this->audience, $audiences, true)) {
            return false;
        }

        if (!is_int($expiresAt) && !is_numeric($expiresAt)) {
            return false;
        }
        if ((int) $expiresAt < ($now - $this->clockSkewSeconds)) {
            return false;
        }

        if ($notBefore !== null && is_numeric($notBefore) && (int) $notBefore > ($now + $this->clockSkewSeconds)) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $value): ?array
    {
        $decoded = $this->decodeBase64Url($value);
        if ($decoded === null) {
            return null;
        }

        try {
            $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
