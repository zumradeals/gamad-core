<?php

declare(strict_types=1);

/**
 * Garde des identifiants de résolution de CAP-CORE-001.
 *
 * Exécution : php core/registre-identites/tests/identifiants_resolution_p3.php
 */

use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\SchemaInscription;

require __DIR__ . '/../src/SchemaInscription.php';
require __DIR__ . '/../src/SchemaVerificationIdentifiants.php';
require __DIR__ . '/../src/IdentifiantsResolution.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
SchemaInscription::migrer($pdo);

$resolution = new IdentifiantsResolution($pdo);
$echecs = 0;
$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    echo $ok ? "  [OK]    {$message}\n" : "  [ÉCHEC] {$message}\n";
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — IDENTIFIANTS DE RÉSOLUTION (CAP-CORE-001)\n\n";

$email = $resolution->attacher('IDN-PER-000001', 'EMAIL', '  Personne@Test.Example ', [
    'source' => 'garde', 'preuve' => 'P3-EMAIL-1', 'producteur' => 'PRD-GAMAD-005', 'verifie' => true,
]);
$verifier(isset($email['reference']), 'un email normalisable peut être attaché');
$trouveEmail = $resolution->resoudre('personne@test.example');
$verifier(($trouveEmail['identite'] ?? null) === 'IDN-PER-000001', 'un email retrouve la référence canonique');
$verifier(($trouveEmail['type'] ?? null) === 'EMAIL', 'le type de résolution email est conservé');

$telephone = $resolution->attacher('IDN-PER-000001', 'TELEPHONE', '00 225 07 01 02 03 04', [
    'source' => 'garde', 'preuve' => 'P3-TEL-1', 'producteur' => 'PRD-GAMAD-005', 'verifie' => true,
]);
$verifier(isset($telephone['reference']), 'un téléphone international peut être attaché');
$trouveTel = $resolution->resoudre('+2250701020304', 'TELEPHONE');
$verifier(($trouveTel['identite'] ?? null) === 'IDN-PER-000001', 'un téléphone retrouve la même identité canonique');

$collision = $resolution->attacher('IDN-PER-000002', 'EMAIL', 'PERSONNE@test.example', [
    'source' => 'garde', 'preuve' => 'P3-EMAIL-2', 'producteur' => 'PRD-GAMAD-005',
]);
$verifier(($collision['refus'] ?? null) === 'IDENTIFIANT_DEJA_UTILISE', 'un email actif ne peut pas désigner deux identités');

$nonVerifie = $resolution->attacher('IDN-PER-000003', 'EMAIL', 'verification@test.example', [
    'source' => 'garde', 'preuve' => 'P3-EMAIL-VRF', 'producteur' => 'PRD-GAMAD-005',
]);
$verifier(
    isset($nonVerifie['reference'])
        && $resolution->resoudrePourAuthentification('verification@test.example', 'EMAIL') === null,
    'un email non vérifié ne sert pas à l’authentification',
);
$defi = $resolution->demarrerVerification((string) ($nonVerifie['reference'] ?? ''), [
    'source' => 'garde', 'preuve' => 'P3-VRF-1', 'producteur' => 'PRD-GAMAD-005',
]);
$verifier(
    str_starts_with((string) ($defi['reference'] ?? ''), 'VRF-')
        && preg_match('/^[0-9]{6}$/', (string) ($defi['code'] ?? '')) === 1,
    'le Core engendre un défi court sans persister le code brut',
);
$preuve = $resolution->verifierPossession(
    'IDN-PER-000003',
    (string) ($nonVerifie['reference'] ?? ''),
    (string) ($defi['reference'] ?? ''),
    (string) ($defi['code'] ?? ''),
);
$verifier(
    ($preuve['etat'] ?? null) === 'VERIFIE'
        && ($resolution->resoudrePourAuthentification('verification@test.example', 'EMAIL')['identite'] ?? null) === 'IDN-PER-000003',
    'la preuve correcte rend l’identifiant utilisable pour se connecter',
);

$brut = $pdo->query("SELECT group_concat(empreinte, '|') FROM identifiant_resolution")->fetchColumn();
$verifier(is_string($brut) && !str_contains($brut, 'personne@test.example'), 'la valeur email brute n’est pas conservée dans le registre de résolution');
$secrets = (string) $pdo->query("SELECT group_concat(secret_empreinte, '|') FROM verification_identifiant")->fetchColumn();
$verifier($secrets !== '' && !str_contains($secrets, (string) ($defi['code'] ?? '')), 'le code de vérification brut n’est pas persisté');

$verifier(IdentifiantsResolution::normaliser('TELEPHONE', '0701020304') === null, 'un téléphone local ambigu est refusé sans contexte pays');
$verifier(IdentifiantsResolution::normaliser('EMAIL', 'pas-un-email') === null, 'un email invalide est refusé');

exit($echecs === 0 ? 0 : 1);
