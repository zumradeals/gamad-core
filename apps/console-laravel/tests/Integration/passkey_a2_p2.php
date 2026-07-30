<?php

declare(strict_types=1);

/**
 * Preuve cryptographique du facteur fort WebAuthn.
 *
 * Exécution depuis la racine :
 *   php apps/console-laravel/tests/Integration/passkey_a2_p2.php
 */

use App\Security\PasskeyService;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Key\Ec2Key;
use Cose\Key\Key;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-passkey-a2-'.getmypid();
$fichiers = [
    'acces' => $temp.'-acces.sqlite',
    'journal' => $temp.'-journal.sqlite',
    'config' => $temp.'-config.php',
    'events' => $temp.'-events.php',
    'packages' => $temp.'-packages.php',
    'routes' => $temp.'-routes.php',
    'services' => $temp.'-services.php',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)),
    'APP_URL' => 'https://console.example.test',
    'APP_CONFIG_CACHE' => $fichiers['config'],
    'APP_EVENTS_CACHE' => $fichiers['events'],
    'APP_PACKAGES_CACHE' => $fichiers['packages'],
    'APP_ROUTES_CACHE' => $fichiers['routes'],
    'APP_SERVICES_CACHE' => $fichiers['services'],
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'MAGASIN_URL' => '',
    'MAGASIN_PATH' => $fichiers['acces'],
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
    'GAMAD_PASSKEY_RP_ID' => 'console.example.test',
    'GAMAD_PASSKEY_ALLOWED_ORIGINS' => 'https://console.example.test',
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';
$app = require $application.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$http = $app->make(HttpKernel::class);

$cleOpenSsl = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
]);
if ($cleOpenSsl === false) {
    throw new RuntimeException('Impossible de créer la clé EC de test.');
}
$details = openssl_pkey_get_details($cleOpenSsl);
if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
    throw new RuntimeException('Coordonnées EC de test indisponibles.');
}

$clePrivee = Ec2Key::create([
    Key::TYPE => Key::TYPE_EC2,
    Key::ALG => ES256::ID,
    Ec2Key::DATA_CURVE => Ec2Key::CURVE_P256,
    Ec2Key::DATA_X => $details['ec']['x'],
    Ec2Key::DATA_Y => $details['ec']['y'],
    Ec2Key::DATA_D => $details['ec']['d'],
]);
$clePubliqueCbor = (string) MapObject::create()
    ->add(UnsignedIntegerObject::create(Key::TYPE), UnsignedIntegerObject::create(Key::TYPE_EC2))
    ->add(UnsignedIntegerObject::create(Key::ALG), NegativeIntegerObject::create(ES256::ID))
    ->add(
        NegativeIntegerObject::create(Ec2Key::DATA_CURVE),
        UnsignedIntegerObject::create(Ec2Key::CURVE_P256),
    )
    ->add(
        NegativeIntegerObject::create(Ec2Key::DATA_X),
        ByteStringObject::create($details['ec']['x']),
    )
    ->add(
        NegativeIntegerObject::create(Ec2Key::DATA_Y),
        ByteStringObject::create($details['ec']['y']),
    );

$credentialId = random_bytes(32);
$serializer = (new WebauthnSerializerFactory(
    new AttestationStatementSupportManager,
))->create();
$ctr = new Ctr16(Magasin::connecter());
$autorisation = $ctr->preparerEnrolementPasskey('ENTITE-A2');
$service = new PasskeyService;
$enrolement = $service->commencerEnrolement('ENTITE-A2', $autorisation['jeton']);
$userHandle = Base64UrlSafe::decode((string) $enrolement['options']['user']['id']);
$clientDataEnrolement = json_encode([
    'type' => 'webauthn.create',
    'challenge' => $enrolement['options']['challenge'],
    'origin' => 'https://console.example.test',
    'crossOrigin' => false,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$authenticatorDataEnrolement = hash('sha256', 'console.example.test', true)
    .chr(0x45) // user present + user verified + attested credential data
    .pack('N', 0)
    .str_repeat("\0", 16)
    .pack('n', strlen($credentialId))
    .$credentialId
    .$clePubliqueCbor;
$attestation = (string) MapObject::create()
    ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
    ->add(TextStringObject::create('attStmt'), MapObject::create())
    ->add(
        TextStringObject::create('authData'),
        ByteStringObject::create($authenticatorDataEnrolement),
    );
$credentialIdEncode = Base64UrlSafe::encodeUnpadded($credentialId);
$passkey = $service->terminerEnrolement(
    'ENTITE-A2',
    $enrolement['ceremonie'],
    $enrolement['autorisation'],
    'Passkey cryptographique de test',
    [
        'id' => $credentialIdEncode,
        'rawId' => $credentialIdEncode,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientDataEnrolement),
            'attestationObject' => Base64UrlSafe::encodeUnpadded($attestation),
            'transports' => ['internal'],
        ],
    ],
);
$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
        $echecs++;
    }
};

$assertion = static function (
    array $options,
    string $origine,
    int $compteur,
) use ($credentialId, $userHandle, $clePrivee): array {
    $clientDataJson = json_encode([
        'type' => 'webauthn.get',
        'challenge' => $options['challenge'],
        'origin' => $origine,
        'crossOrigin' => false,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $authenticatorData = hash('sha256', 'console.example.test', true)
        .chr(0x05) // user present + user verified
        .pack('N', $compteur);
    $signature = ES256::create()->sign(
        $authenticatorData.hash('sha256', $clientDataJson, true),
        $clePrivee,
    );
    $id = Base64UrlSafe::encodeUnpadded($credentialId);

    return [
        'id' => $id,
        'rawId' => $id,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientDataJson),
            'authenticatorData' => Base64UrlSafe::encodeUnpadded($authenticatorData),
            'signature' => Base64UrlSafe::encodeUnpadded($signature),
            'userHandle' => Base64UrlSafe::encodeUnpadded($userHandle),
        ],
    ];
};
$requeteApi = static function (array $corps) use ($http): array {
    $request = Request::create(
        '/api/v1/sessions/passkey',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        json_encode($corps, JSON_THROW_ON_ERROR),
    );
    $response = $http->handle($request);
    $json = json_decode((string) $response->getContent(), true);
    $resultat = [
        'statut' => $response->getStatusCode(),
        'corps' => is_array($json) ? $json : [],
        'cache' => $response->headers->get('Cache-Control'),
    ];
    $http->terminate($request, $response);

    return $resultat;
};

echo "INTÉGRATION — PASSKEY WEBAUTHN A2\n\n";

$verifier(
    ($enrolement['options']['authenticatorSelection']['userVerification'] ?? null) === 'required'
        && $ctr->verifierAutorisationEnrolement('ENTITE-A2', $autorisation['jeton']) === null,
    'l’enrôlement vérifie cryptographiquement l’utilisateur et consomme son autorisation',
);

$preparation = $service->commencerAuthentification('ENTITE-A2');
$descripteurs = $preparation['options']['allowCredentials'] ?? [];
$verifier(
    count($descripteurs) === 5
        && ($preparation['options']['userVerification'] ?? null) === 'required',
    'les options imposent la vérification utilisateur et ne révèlent pas le nombre de passkeys',
);
$session = $service->terminerAuthentification(
    $preparation['ceremonie'],
    $assertion($preparation['options'], 'https://console.example.test', 1),
);
$etatSession = $ctr->verifierSession((string) $session['session']);
$verifier(
    ($session['passkey'] ?? null) === $passkey
        && ($session['assurance'] ?? null) === 'A2 — FACTEUR FORT'
        && $etatSession['valide'] === true,
    'une signature ES256 valide ouvre une session A2',
);

$rejeuRefuse = false;
try {
    $service->terminerAuthentification(
        $preparation['ceremonie'],
        $assertion($preparation['options'], 'https://console.example.test', 2),
    );
} catch (Throwable) {
    $rejeuRefuse = true;
}
$verifier($rejeuRefuse, 'le challenge consommé ne peut pas être rejoué');

$origine = $service->commencerAuthentification('ENTITE-A2');
$origineRefusee = false;
try {
    $service->terminerAuthentification(
        $origine['ceremonie'],
        $assertion($origine['options'], 'https://origine-hostile.example', 2),
    );
} catch (Throwable) {
    $origineRefusee = true;
}
$verifier($origineRefusee, 'une origine hors liste fermée est refusée');

$origineBrulee = false;
try {
    $service->terminerAuthentification(
        $origine['ceremonie'],
        $assertion($origine['options'], 'https://console.example.test', 2),
    );
} catch (Throwable) {
    $origineBrulee = true;
}
$verifier($origineBrulee, 'une assertion refusée brûle aussi son challenge');

$sansJournal = $service->commencerAuthentification('ENTITE-A2');
$cheminJournalValide = $fichiers['journal'];
putenv('JOURNAL_OPERATIONNEL_PATH=/dev/null/journal.sqlite');
$_ENV['JOURNAL_OPERATIONNEL_PATH'] = '/dev/null/journal.sqlite';
$_SERVER['JOURNAL_OPERATIONNEL_PATH'] = '/dev/null/journal.sqlite';
$journalIndisponible = $requeteApi([
    'entite' => 'ENTITE-A2',
    'ceremonie' => $sansJournal['ceremonie'],
    'credential' => $assertion(
        $sansJournal['options'],
        'https://console.example.test',
        2,
    ),
]);
putenv("JOURNAL_OPERATIONNEL_PATH={$cheminJournalValide}");
$_ENV['JOURNAL_OPERATIONNEL_PATH'] = $cheminJournalValide;
$_SERVER['JOURNAL_OPERATIONNEL_PATH'] = $cheminJournalValide;
$derniereRevoquee = Magasin::ouvrir()->query(
    'SELECT revoquee_le FROM session_ouverte ORDER BY id DESC LIMIT 1',
)->fetchColumn();
$verifier(
    $journalIndisponible['statut'] === 503
        && ($journalIndisponible['corps']['erreur'] ?? null) === 'JOURNAL_INDISPONIBLE'
        && is_string($derniereRevoquee),
    'un échec du journal révoque la session A2 et ferme la réponse',
);

$api = $service->commencerAuthentification('ENTITE-A2');
$sessionApi = $requeteApi([
    'entite' => 'ENTITE-A2',
    'ceremonie' => $api['ceremonie'],
    'credential' => $assertion($api['options'], 'https://console.example.test', 3),
]);
$verifier(
    $sessionApi['statut'] === 201
        && ($sessionApi['corps']['assurance'] ?? null) === 'A2 — FACTEUR FORT'
        && isset($sessionApi['corps']['preuve']['empreinte'])
        && str_contains((string) $sessionApi['cache'], 'no-store'),
    'l’API ne livre une session A2 qu’avec sa preuve et une réponse non cachable',
);

$persistante = $ctr->trouverPasskey(Base64UrlSafe::encodeUnpadded($credentialId));
$recordPersistant = $serializer->deserialize(
    (string) $persistante['credential_record'],
    CredentialRecord::class,
    'json',
);
$verifier(
    $recordPersistant instanceof CredentialRecord && $recordPersistant->counter === 3,
    'le compteur de signature validé est persisté',
);

echo "\n";
if ($echecs === 0) {
    echo "Passkey A2 : ÉTABLIE.\n";
    exit(0);
}

echo "Passkey A2 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
