<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use RuntimeException;

final readonly class RsaJwk
{
    /** @param array<string, mixed> $jwk */
    public static function toPem(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || !isset($jwk['n'], $jwk['e'])) {
            throw new RuntimeException('Only RSA JWK keys are supported.');
        }

        $modulus = self::base64UrlDecode((string) $jwk['n']);
        $exponent = self::base64UrlDecode((string) $jwk['e']);
        $rsaPublicKey = self::sequence(self::integer($modulus) . self::integer($exponent));
        $algorithmIdentifier = self::sequence(
            self::objectIdentifier("\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00",
        );
        $subjectPublicKeyInfo = self::sequence(
            $algorithmIdentifier . "\x03" . self::length(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey,
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url JWK field.');
        }

        return $decoded;
    }

    private static function integer(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '' || (ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }

        return "\x02" . self::length(strlen($value)) . $value;
    }

    private static function sequence(string $value): string
    {
        return "\x30" . self::length(strlen($value)) . $value;
    }

    private static function objectIdentifier(string $value): string
    {
        return "\x06" . self::length(strlen($value)) . $value;
    }

    private static function length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff) . $encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)) . $encoded;
    }
}
