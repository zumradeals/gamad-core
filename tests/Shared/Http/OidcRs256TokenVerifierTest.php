<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Http;

use Gamad\Core\Shared\Http\JwksProvider;
use Gamad\Core\Shared\Infrastructure\Http\OidcRs256TokenVerifier;
use Gamad\Core\Tests\Support\JwtTestFactory;
use PHPUnit\Framework\TestCase;

final class OidcRs256TokenVerifierTest extends TestCase
{
    public function test_it_accepts_a_valid_rs256_token(): void
    {
        $factory = new JwtTestFactory();
        $verifier = $this->verifier(['test-key-1' => $factory->jwk()]);
        $actor = $verifier->verify($factory->token($this->claims()));

        self::assertSame('GAM-GAT-PER-000001', $actor?->actorId);
        self::assertTrue($actor?->hasScope('core.runtime.health.read') ?? false);
    }

    /** @dataProvider invalidClaimProvider */
    public function test_it_rejects_invalid_standard_claims(array $overrides): void
    {
        $factory = new JwtTestFactory();
        $claims = array_replace($this->claims(), $overrides);

        self::assertNull($this->verifier(['test-key-1' => $factory->jwk()])->verify($factory->token($claims)));
    }

    public static function invalidClaimProvider(): array
    {
        return [
            'wrong issuer' => [['iss' => 'https://wrong.example.test']],
            'wrong audience' => [['aud' => 'another-api']],
            'expired' => [['exp' => time() - 120]],
            'not active yet' => [['nbf' => time() + 120]],
            'missing subject' => [['sub' => '']],
        ];
    }

    public function test_it_rejects_unknown_key_and_accepts_rotated_key(): void
    {
        $old = new JwtTestFactory('old-key');
        $new = new JwtTestFactory('new-key');
        $provider = new MutableJwksProvider(['old-key' => $old->jwk()]);
        $verifier = new OidcRs256TokenVerifier($provider, 'https://idp.example.test', 'gamad-admin', 60);
        $newToken = $new->token($this->claims());

        self::assertNull($verifier->verify($newToken));

        $provider->replace(['new-key' => $new->jwk()]);

        self::assertSame('GAM-GAT-PER-000001', $verifier->verify($newToken)?->actorId);
    }

    public function test_it_rejects_a_tampered_signature(): void
    {
        $factory = new JwtTestFactory();
        $token = $factory->token($this->claims());
        $parts = explode('.', $token);
        $parts[1] = rtrim(strtr(base64_encode(json_encode(array_replace($this->claims(), ['sub' => 'GAM-GAT-PER-999999']), JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        self::assertNull($this->verifier(['test-key-1' => $factory->jwk()])->verify(implode('.', $parts)));
    }

    /** @param array<string, array<string, mixed>> $keys */
    private function verifier(array $keys): OidcRs256TokenVerifier
    {
        return new OidcRs256TokenVerifier(new MutableJwksProvider($keys), 'https://idp.example.test', 'gamad-admin', 60);
    }

    /** @return array<string, mixed> */
    private function claims(): array
    {
        return [
            'iss' => 'https://idp.example.test',
            'aud' => 'gamad-admin',
            'sub' => 'GAM-GAT-PER-000001',
            'scope' => 'core.runtime.health.read core.outbox.dashboard.read',
            'iat' => time(),
            'nbf' => time() - 5,
            'exp' => time() + 300,
        ];
    }
}

final class MutableJwksProvider implements JwksProvider
{
    /** @param array<string, array<string, mixed>> $keys */
    public function __construct(private array $keys) {}

    public function keys(): array
    {
        return $this->keys;
    }

    /** @param array<string, array<string, mixed>> $keys */
    public function replace(array $keys): void
    {
        $this->keys = $keys;
    }
}
