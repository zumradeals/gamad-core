<?php

declare(strict_types=1);

namespace Gamad\RegistrePreuves;

use Gamad\RegistreSecretsCles\ExceptionSecret;
use Gamad\RegistreSecretsCles\ResolveurSecret;
use Gamad\RegistreSecretsCles\SensitiveValue;
use Gamad\RegistreSecretsCles\UsageSecret;

/**
 * Signature et vérification Ed25519 (CAP-CORE-015, fiche partie 2 §6,
 * partie 3 §19).
 *
 * `CAP-CORE-015` ne lit jamais une clé privée : `signer()` délègue à
 * `ResolveurSecret::avecSecret()` (CAP-CORE-016) — le matériel privé
 * n'existe que dans la portée du callback interne, jamais retourné à cette
 * classe. `sodium_crypto_sign_detached()` s'exécute à l'intérieur de ce
 * callback ; seule la signature (donnée publique par construction) en
 * ressort.
 */
final class ServiceSignature
{
    public function __construct(
        private readonly ResolveurSecret $resolveur,
    ) {
    }

    public static function sodiumDisponible(): bool
    {
        return extension_loaded('sodium') && function_exists('sodium_crypto_sign_detached');
    }

    /**
     * @return array{signature_base64url:string,algorithme:string}
     */
    public function signer(string $cleReference, string $contexteCanonique, UsageSecret $usage): array
    {
        if (!self::sodiumDisponible()) {
            throw new ExceptionPreuve('extension sodium indisponible — signature Ed25519 impossible');
        }

        try {
            $signatureBrute = $this->resolveur->avecSecret(
                $cleReference,
                $usage,
                static function (SensitiveValue $valeur) use ($contexteCanonique): string {
                    $clePrivee = base64_decode($valeur->valeur(), true);
                    if ($clePrivee === false || strlen($clePrivee) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                        throw new ExceptionPreuve('matériel de clé Ed25519 invalide');
                    }
                    $signature = sodium_crypto_sign_detached($contexteCanonique, $clePrivee);
                    sodium_memzero($clePrivee);

                    return $signature;
                },
            );
        } catch (ExceptionSecret $e) {
            throw new ExceptionPreuve('signature refusée par CAP-CORE-016 : ' . $e->getMessage());
        }

        return [
            'signature_base64url' => self::base64UrlEncoder($signatureBrute),
            'algorithme' => 'ED25519',
        ];
    }

    public function verifier(string $signatureBase64url, string $contexteCanonique, string $clePubliqueBase64): bool
    {
        if (!self::sodiumDisponible()) {
            return false;
        }
        $signature = self::base64UrlDecoder($signatureBase64url);
        $clePublique = base64_decode($clePubliqueBase64, true);
        if ($signature === false || $clePublique === false || strlen($clePublique) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $contexteCanonique, $clePublique);
    }

    private static function base64UrlEncoder(string $donnees): string
    {
        return rtrim(strtr(base64_encode($donnees), '+/', '-_'), '=');
    }

    private static function base64UrlDecoder(string $donnees): string|false
    {
        $pad = strlen($donnees) % 4;
        if ($pad > 0) {
            $donnees .= str_repeat('=', 4 - $pad);
        }

        return base64_decode(strtr($donnees, '-_', '+/'), true);
    }
}
