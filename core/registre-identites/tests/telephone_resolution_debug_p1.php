<?php

declare(strict_types=1);

use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\SchemaInscription;

require __DIR__ . '/../src/SchemaInscription.php';
require __DIR__ . '/../src/IdentifiantsResolution.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
SchemaInscription::migrer($pdo);

$registre = new IdentifiantsResolution($pdo);
$creation = '+225 07 18 71 37 81';
$connexion = '+2250718713781';

$normaliseeCreation = IdentifiantsResolution::normaliser('TELEPHONE', $creation);
$normaliseeConnexion = IdentifiantsResolution::normaliser('TELEPHONE', $connexion);

$attache = $registre->attacher('IDN-PER-TEST', 'TELEPHONE', $creation, [
    'source' => 'TEST',
    'preuve' => 'TEST',
    'producteur' => 'TEST',
]);
$resolution = $registre->resoudre($connexion, 'TELEPHONE');

echo json_encode([
    'creation_brute' => $creation,
    'connexion_brute' => $connexion,
    'normalisee_creation' => $normaliseeCreation,
    'normalisee_connexion' => $normaliseeConnexion,
    'normalisees_identiques' => $normaliseeCreation === $normaliseeConnexion,
    'attache' => $attache,
    'resolution' => $resolution,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

$ok = $normaliseeCreation === $normaliseeConnexion
    && $normaliseeCreation === '+2250718713781'
    && ($resolution['identite'] ?? null) === 'IDN-PER-TEST';

exit($ok ? 0 : 1);
