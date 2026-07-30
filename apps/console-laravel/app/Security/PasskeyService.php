<?php

declare(strict_types=1);

namespace App\Security;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Cérémonies WebAuthn de CAP-CORE-005.
 *
 * Cette couche délègue toutes les validations cryptographiques à la
 * bibliothèque WebAuthn. Le magasin du Core ne conserve que le credential
 * public, un compteur de signature et les challenges temporaires.
 */
final class PasskeyService
{
    private const MAX_PASSKEYS_PAR_ENTITE = 5;

    private readonly SerializerInterface $serializer;

    private readonly AuthenticatorAttestationResponseValidator $attestationValidator;

    private readonly AuthenticatorAssertionResponseValidator $assertionValidator;

    private readonly string $rpId;

    private readonly string $rpName;

    /** @var list<string> */
    private readonly array $allowedOrigins;

    private readonly int $timeout;

    public function __construct()
    {
        $this->rpId = (string) config('passkeys.relying_party.id');
        $this->rpName = (string) config('passkeys.relying_party.name');
        $origines = config('passkeys.allowed_origins', []);
        $this->allowedOrigins = is_array($origines)
            ? array_values(array_filter($origines, 'is_string'))
            : [];
        $this->timeout = (int) config('passkeys.ceremony_timeout_seconds', 300);

        if ($this->rpId === '' || $this->allowedOrigins === []) {
            throw new \RuntimeException('Configuration WebAuthn incomplète.');
        }

        $attestations = new AttestationStatementSupportManager;
        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins($this->allowedOrigins, false);
        $factory->setAttestationStatementSupportManager($attestations);

        $this->serializer = (new WebauthnSerializerFactory($attestations))->create();
        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create(
            $factory->creationCeremony(),
        );
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create(
            $factory->requestCeremony(),
        );
    }

    /**
     * @return array{options:array<string,mixed>,ceremonie:string,autorisation:string,expire_le:string}
     */
    public function commencerEnrolement(
        string $entite,
        #[\SensitiveParameter] string $jeton,
    ): array {
        $ctr = $this->ctr();
        $autorisation = $ctr->verifierAutorisationEnrolement($entite, $jeton);
        if ($autorisation === null) {
            throw new \RuntimeException('Autorisation d’enrôlement invalide ou expirée.');
        }

        $passkeys = $ctr->passkeysActives($entite);
        if (count($passkeys) >= self::MAX_PASSKEYS_PAR_ENTITE) {
            throw new \RuntimeException('Nombre maximal de passkeys actives atteint.');
        }
        $exclues = array_map(
            fn (array $passkey): PublicKeyCredentialDescriptor => $this
                ->credentialRecord((string) $passkey['credential_record'])
                ->getPublicKeyCredentialDescriptor(),
            $passkeys,
        );
        $userHandle = random_bytes(32);
        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId),
            PublicKeyCredentialUserEntity::create($entite, $userHandle, $entite),
            random_bytes(32),
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $exclues,
            timeout: $this->timeout * 1000,
        );
        $optionsJson = $this->serialiser($options);
        $ceremonie = $ctr->enregistrerCeremoniePasskey(
            $entite,
            'ENROLEMENT',
            $optionsJson,
            $this->timeout,
        );

        return [
            'options' => $this->json($optionsJson),
            'ceremonie' => $ceremonie['reference'],
            'autorisation' => $autorisation['reference'],
            'expire_le' => $ceremonie['expire_le'],
        ];
    }

    public function terminerEnrolement(
        string $entite,
        string $ceremonie,
        string $autorisation,
        string $libelle,
        array $credential,
    ): string {
        $ctr = $this->ctr();
        $etat = $ctr->consommerCeremoniePasskey($ceremonie, 'ENROLEMENT', $entite);
        if ($etat === null) {
            throw new \RuntimeException('Cérémonie d’enrôlement absente, expirée ou déjà consommée.');
        }

        $options = $this->serializer->deserialize(
            (string) $etat['options_json'],
            PublicKeyCredentialCreationOptions::class,
            'json',
        );
        $publique = $this->chargerCredential($credential);
        if (! $publique->response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Réponse WebAuthn d’enrôlement invalide.');
        }

        $record = $this->attestationValidator->check(
            $publique->response,
            $options,
            $this->rpId,
        );
        if ($record->uvInitialized !== true) {
            throw new \RuntimeException('La passkey n’a pas vérifié son utilisateur.');
        }

        return $ctr->inscrirePasskey(
            $entite,
            Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
            Base64UrlSafe::encodeUnpadded($record->userHandle),
            $this->serialiser($record),
            $libelle,
            $autorisation,
        );
    }

    /**
     * @return array{options:array<string,mixed>,ceremonie:string,expire_le:string}
     */
    public function commencerAuthentification(string $entite): array
    {
        $ctr = $this->ctr();
        $passkeys = $ctr->passkeysActives($entite);
        $permises = array_map(
            fn (array $passkey): PublicKeyCredentialDescriptor => $this
                ->credentialRecord((string) $passkey['credential_record'])
                ->getPublicKeyCredentialDescriptor(),
            $passkeys,
        );

        // Cinq descripteurs sont toujours retournés. Les faux sont stables
        // pour une entité donnée mais imprévisibles sans APP_KEY : la réponse
        // ne révèle donc pas si l'entité existe ou possède une passkey.
        for ($i = count($permises); $i < self::MAX_PASSKEYS_PAR_ENTITE; $i++) {
            $permises[] = PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                hash_hmac('sha256', "{$entite}|{$i}", (string) config('app.key'), true),
            );
        }

        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $this->rpId,
            allowCredentials: $permises,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: $this->timeout * 1000,
        );
        $optionsJson = $this->serialiser($options);
        $ceremonie = $ctr->enregistrerCeremoniePasskey(
            $entite,
            'AUTHENTIFICATION',
            $optionsJson,
            $this->timeout,
        );

        return [
            'options' => $this->json($optionsJson),
            'ceremonie' => $ceremonie['reference'],
            'expire_le' => $ceremonie['expire_le'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function terminerAuthentification(
        string $ceremonie,
        array $credential,
    ): array {
        $ctr = $this->ctr();
        $etat = $ctr->consommerCeremoniePasskey($ceremonie, 'AUTHENTIFICATION');
        if ($etat === null) {
            throw new \RuntimeException('Cérémonie d’authentification absente, expirée ou déjà consommée.');
        }

        $options = $this->serializer->deserialize(
            (string) $etat['options_json'],
            PublicKeyCredentialRequestOptions::class,
            'json',
        );
        $publique = $this->chargerCredential($credential);
        if (! $publique->response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Réponse WebAuthn d’authentification invalide.');
        }

        $credentialId = Base64UrlSafe::encodeUnpadded($publique->rawId);
        $passkey = $ctr->trouverPasskey($credentialId);
        if ($passkey === null
            || ! hash_equals((string) $etat['entite_reference'], (string) $passkey['entite_reference'])) {
            throw new \RuntimeException('Passkey refusée.');
        }

        $record = $this->credentialRecord((string) $passkey['credential_record']);
        $record = $this->assertionValidator->check(
            $record,
            $publique->response,
            $options,
            $this->rpId,
            Base64UrlSafe::decode((string) $passkey['user_handle']),
        );
        $ctr->actualiserPasskey((string) $passkey['reference'], $this->serialiser($record));
        $session = $ctr->etablirSessionPasskey((string) $passkey['reference']);
        if ($session === null) {
            throw new \RuntimeException('La passkey a été révoquée pendant la cérémonie.');
        }

        return $session + ['passkey' => (string) $passkey['reference']];
    }

    private function ctr(): Ctr16
    {
        return new Ctr16(Magasin::connecter());
    }

    private function chargerCredential(array $credential): PublicKeyCredential
    {
        $json = json_encode($credential, JSON_THROW_ON_ERROR);
        $publique = $this->serializer->deserialize($json, PublicKeyCredential::class, 'json');
        if (! $publique instanceof PublicKeyCredential) {
            throw new \RuntimeException('Credential WebAuthn illisible.');
        }

        return $publique;
    }

    private function credentialRecord(string $json): CredentialRecord
    {
        $record = $this->serializer->deserialize($json, CredentialRecord::class, 'json');
        if (! $record instanceof CredentialRecord) {
            throw new \RuntimeException('Credential WebAuthn persistant illisible.');
        }

        return $record;
    }

    private function serialiser(object $objet): string
    {
        return $this->serializer->serialize($objet, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function json(string $json): array
    {
        $valeur = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($valeur)) {
            throw new \RuntimeException('Options WebAuthn illisibles.');
        }

        return $valeur;
    }
}
