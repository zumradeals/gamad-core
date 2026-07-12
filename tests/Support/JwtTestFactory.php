<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Support;

use OpenSSLAsymmetricKey;
use RuntimeException;

final class JwtTestFactory
{
    private OpenSSLAsymmetricKey $privateKey;
    private string $kid;

    public function __construct(string $kid = 'test-key-1')
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false) {
            throw new RuntimeException('Unable to generate RSA test key.');
        }

        $this->privateKey = $key;
        $this->kid = $kid;
    }

    /** @return array<string, mixed> */
    public function jwk(): array
    {
        $details = openssl_pkey_get_details($this->privateKey);
        if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Unable to read RSA test key details.');
        }

        return [
            'kty' => 'RSA',
            'kid' => $this->kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    /** @param array<string, mixed> $claims */
    public function token(array $claims, ?string $kid = null): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => $kid ?? $this->kid];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signingInput = $encodedHeader . '.' . $encodedPayload;

        if (!openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign JWT test token.');
        }

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
