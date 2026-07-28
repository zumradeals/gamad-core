<?php

declare(strict_types=1);

/**
 * Preuve P3 de CAP-CORE-005 — authentification, expiration, révocation.
 *
 * Garde propre à la capacité (doctrine d'ADOPTION-0035, Art. 2.2).
 *
 * AUCUN SECRET RÉEL N'INTERVIENT ICI. L'essai crée un magasin temporaire,
 * y inscrit un authentificateur avec un secret de test, et détruit le tout
 * à la fin. Le magasin de production n'est jamais ouvert.
 *
 * Ce que le test éprouve :
 *   · un secret exact ouvre une session ;
 *   · un secret erroné la refuse ;
 *   · une session expirée est invalide (INV-25) ;
 *   · la révocation de l'authentificateur invalide la session qu'il avait
 *     ouverte — c'est la menace M-21, la plus dangereuse de la capacité.
 *
 * Exécution : php core/registre-acces/tests/authentification_p3.php
 */

use Gamad\RegistreAcces\Ctr05;
use Gamad\RegistreAcces\Magasin;

require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/Ctr05.php';

$fichier = sys_get_temp_dir() . '/regn-authn-p3-' . getmypid() . '.sqlite';
@unlink($fichier);

$magasin = Magasin::connecter($fichier);
$ctr05 = new Ctr05($magasin);

$echecs = 0;
$verifier = function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "PREUVE P3 — AUTHENTIFICATION, EXPIRATION, RÉVOCATION (CAP-CORE-005)\n\n";

// Secret de test, sans rapport avec aucun secret réel.
$secret = 'secret-de-test-non-institutionnel';
$authn = $ctr05->inscrireAuthentificateur('ENTITE-DE-TEST', $secret);

// --- Le secret exact ouvre une session.
$session = $ctr05->etablirSession('ENTITE-DE-TEST', $secret);
$verifier($session !== null && isset($session['session']), 'un secret exact ouvre une session');

// --- Le secret erroné la refuse.
$verifier(
    $ctr05->etablirSession('ENTITE-DE-TEST', 'mauvais-secret-quelconque') === null,
    'un secret erroné est refusé',
);

// --- Une entité sans authentificateur est refusée de la même manière.
$verifier(
    $ctr05->etablirSession('ENTITE-INEXISTANTE', $secret) === null,
    'une entité sans authentificateur est refusée',
);

// --- La session ouverte est valide maintenant.
$reference = $session['session'] ?? '';
$verifier(($ctr05->verifierSession($reference)['valide'] ?? false) === true, 'la session ouverte est valide');

// --- Elle ne l'est plus après son expiration (INV-25).
$apres = date('c', time() + 3600 * 24);
$expiree = $ctr05->verifierSession($reference, $apres);
$verifier(
    ($expiree['valide'] ?? true) === false && $expiree['motif'] === 'session expirée',
    'une session expirée est invalide (INV-25)',
);

// --- M-21 : la révocation de l'authentificateur invalide la session ouverte.
$verifier($ctr05->revoquerAuthentificateur($authn), "l'authentificateur se révoque");
$apresRevocation = $ctr05->verifierSession($reference);
$verifier(
    ($apresRevocation['valide'] ?? true) === false,
    'une session ne survit pas à la révocation de son authentificateur (M-21)',
);

// --- L'attestation ne restitue jamais l'empreinte du secret (INV-24).
$attestation = $ctr05->attester('ENTITE-DE-TEST');
$fuite = false;
foreach ($attestation['authentificateurs'] as $a) {
    if (array_key_exists('empreinte', $a)) {
        $fuite = true;
    }
}
$verifier(!$fuite, "l'attestation ne restitue aucune empreinte de secret (INV-24)");

unset($magasin);
@unlink($fichier);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-005 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
