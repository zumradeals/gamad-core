<?php

declare(strict_types=1);

use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\SchemaInscription;
use Gamad\RegistreIdentites\SchemaVerificationIdentifiants;

require __DIR__ . '/../src/SchemaInscription.php';
require __DIR__ . '/../src/SchemaVerificationIdentifiants.php';
require __DIR__ . '/../src/IdentifiantsResolution.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
SchemaInscription::migrer($pdo);
SchemaVerificationIdentifiants::migrer($pdo);

$registre = new IdentifiantsResolution($pdo);
$echecs = 0;
$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    echo $ok ? "  [OK]    {$message}\n" : "  [ÉCHEC] {$message}\n";
    if (!$ok) {
        $echecs++;
    }
};

$email = 'personne.renvoi@example.test';
$lie = $registre->attacher('IDN-PER-RENVOI', 'EMAIL', $email, [
    'source' => 'TEST', 'preuve' => 'TEST-RID', 'producteur' => 'PRD-GAMAD-002',
]);
$rid = (string) ($lie['reference'] ?? '');
$initial = $registre->demarrerVerification($rid, [
    'source' => 'TEST', 'preuve' => 'TEST-VRF-1', 'producteur' => 'PRD-GAMAD-002',
]);
$vrfInitial = (string) ($initial['reference'] ?? '');

$verifier($registre->destinationCorrespond($rid, 'PERSONNE.RENVOI@example.test'), 'la destination normalisée correspond au RID');
$verifier(!$registre->destinationCorrespond($rid, 'autre@example.test'), 'une autre destination ne correspond pas au RID');

$rapide = $registre->renvoyerVerification($rid, $email, 'PRD-GAMAD-002', [
    'source' => 'TEST', 'preuve' => 'TEST-RENVOI-RAPIDE',
]);
$verifier(($rapide['refus'] ?? null) === 'RENVOI_TROP_RAPIDE', 'un renvoi immédiat est refusé');

$mauvaiseDestination = $registre->renvoyerVerification($rid, 'autre@example.test', 'PRD-GAMAD-002');
$verifier(($mauvaiseDestination['refus'] ?? null) === 'DESTINATION_INCORRECTE', 'le code ne peut pas être détourné vers une autre adresse');

$mauvaisProduit = $registre->renvoyerVerification($rid, $email, 'PRD-GAMAD-999');
$verifier(($mauvaisProduit['refus'] ?? null) === 'RENVOI_NON_AUTORISE', 'un autre produit ne peut pas renvoyer le défi');

$pdo->prepare('UPDATE verification_identifiant SET cree_le = ? WHERE reference = ?')
    ->execute([gmdate('c', time() - 120), $vrfInitial]);
$renvoi = $registre->renvoyerVerification($rid, $email, 'PRD-GAMAD-002', [
    'source' => 'TEST', 'preuve' => 'TEST-RENVOI-OK',
]);
$vrfRenvoi = (string) ($renvoi['reference'] ?? '');
$verifier(str_starts_with($vrfRenvoi, 'VRF-') && $vrfRenvoi !== $vrfInitial, 'un nouveau défi est créé après le délai minimal');

$etatAncien = $pdo->prepare('SELECT etat FROM verification_identifiant WHERE reference = ?');
$etatAncien->execute([$vrfInitial]);
$verifier($etatAncien->fetchColumn() === 'EXPIREE', 'le renvoi invalide immédiatement le défi précédent');

$registre->annulerVerification($vrfRenvoi);
$etatNouveau = $pdo->prepare('SELECT etat FROM verification_identifiant WHERE reference = ?');
$etatNouveau->execute([$vrfRenvoi]);
$verifier($etatNouveau->fetchColumn() === 'EXPIREE', 'une livraison échouée peut annuler le nouveau défi');

exit($echecs === 0 ? 0 : 1);
