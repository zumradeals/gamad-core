<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Support;

use Gamad\Core\Shared\Http\JwksProvider;
use RuntimeException;

final class LocalOidcIdentityProvider implements JwksProvider
{
    /** @var array<string, array{private:string,jwk:array<string,string>}> */
    private array $keys = [];

    public function __construct(
        public readonly string $issuer = 'https://identity.gamad.test',
        public readonly string $audience = 'gamad-admin',
    ) {
        $this->rotate('key-1');
    }

    public function rotate(string $kid): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false || !openssl_pkey_export($resource, $privateKey)) {
            throw new RuntimeException('Unable to generate RSA test key.');
        }

        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Unable to export RSA public parameters.');
        }

        $this->keys[$kid] = [
            'private' => $privateKey,
            'jwk' => [
                'kty' => 'RSA',
                'kid' => $kid,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => self::base64UrlEncode($details['rsa']['n']),
                'e' => self::base64UrlEncode($details['rsa']['e']),
            ],
        ];
    }

    public function keys(): array
    {
        $keys = [];
        foreach ($this->keys as $kid => $material) {
            $keys[$kid] = $material['jwk'];
        }

        return $keys;
    }

    /** @param array<string, mixed> $claims */
    public function token(array $claims = [], string $kid = 'key-1'): string
    {
        $material = $this->keys[$kid] ?? null;
        if ($material === null) {
            throw new RuntimeException('Unknown signing key.');
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid];
        $payload = $claims + [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => 'GAM-PER-000001',
            'scope' => 'core.runtime.health.read',
            'iat' => $now,
            'nbf' => $now - 1,
            'exp' => $now + 300,
        ];
        $unsigned = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            . '.' . self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        if (!openssl_sign($unsigned, $signature, $material['private'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign test token.');
        }

        return $unsigned . '.' . self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
