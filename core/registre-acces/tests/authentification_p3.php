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

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;

require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/Ctr16.php';

$fichier = sys_get_temp_dir() . '/regn-authn-p3-' . getmypid() . '.sqlite';
@unlink($fichier);

$magasin = Magasin::connecter($fichier);
$ctr16 = new Ctr16($magasin);

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
$authn = $ctr16->inscrireAuthentificateur('ENTITE-DE-TEST', $secret);

// --- Le secret exact ouvre une session.
$session = $ctr16->etablirSession('ENTITE-DE-TEST', $secret);
$verifier($session !== null && isset($session['session']), 'un secret exact ouvre une session');

// --- Le secret erroné la refuse.
$verifier(
    $ctr16->etablirSession('ENTITE-DE-TEST', 'mauvais-secret-quelconque') === null,
    'un secret erroné est refusé',
);

// --- Une entité sans authentificateur est refusée de la même manière.
$verifier(
    $ctr16->etablirSession('ENTITE-INEXISTANTE', $secret) === null,
    'une entité sans authentificateur est refusée',
);

// --- La session ouverte est valide maintenant.
$reference = $session['session'] ?? '';
$verifier(($ctr16->verifierSession($reference)['valide'] ?? false) === true, 'la session ouverte est valide');
$fuiteSession = $magasin->prepare(
    'SELECT count(*) FROM session_ouverte WHERE reference = ? OR jeton_empreinte = ?'
);
$fuiteSession->execute([$reference, $reference]);
$verifier(
    (int) $fuiteSession->fetchColumn() === 0,
    'le jeton bearer n’est pas conservé en clair',
);

// --- Elle ne l'est plus après son expiration (INV-25).
$apres = date('c', time() + 3600 * 24);
$expiree = $ctr16->verifierSession($reference, $apres);
$verifier(
    ($expiree['valide'] ?? true) === false && $expiree['motif'] === 'session expirée',
    'une session expirée est invalide (INV-25)',
);

// --- M-21 : la révocation de l'authentificateur invalide la session ouverte.
$verifier($ctr16->revoquerAuthentificateur($authn), "l'authentificateur se révoque");
$apresRevocation = $ctr16->verifierSession($reference);
$verifier(
    ($apresRevocation['valide'] ?? true) === false,
    'une session ne survit pas à la révocation de son authentificateur (M-21)',
);

// --- L'attestation ne restitue jamais l'empreinte du secret (INV-24).
$attestation = $ctr16->attester('ENTITE-DE-TEST');
$fuite = false;
foreach ($attestation['authentificateurs'] as $a) {
    if (array_key_exists('empreinte', $a)) {
        $fuite = true;
    }
}
$verifier(!$fuite, "l'attestation ne restitue aucune empreinte de secret (INV-24)");

// --- Migration additive d'un magasin v1 : une session existante reste
// valide, mais sa valeur bearer disparaît de la base.
$fichierV1 = sys_get_temp_dir() . '/regn-authn-v1-p3-' . getmypid() . '.sqlite';
@unlink($fichierV1);
$v1 = new PDO('sqlite:' . $fichierV1);
$v1->exec(
    'CREATE TABLE authentificateur (
        reference TEXT PRIMARY KEY, entite_reference TEXT NOT NULL, type TEXT NOT NULL,
        empreinte TEXT NOT NULL, niveau_assurance TEXT NOT NULL, etat TEXT NOT NULL,
        cree_le TEXT NOT NULL, revoque_le TEXT
    )',
);
$v1->exec(
    'CREATE TABLE session_ouverte (
        id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT NOT NULL UNIQUE,
        authentificateur_ref TEXT NOT NULL, entite_reference TEXT NOT NULL,
        niveau_assurance TEXT NOT NULL, ouverte_le TEXT NOT NULL, expire_le TEXT NOT NULL,
        revoquee_le TEXT
    )',
);
$v1->exec(
    "INSERT INTO authentificateur VALUES
     ('AUTHN-V1','ENTITE-V1','mot_de_passe','empreinte','AS1','ACTIF','2026-07-30T00:00:00Z',NULL)",
);
$v1->exec(
    "INSERT INTO session_ouverte
     (reference,authentificateur_ref,entite_reference,niveau_assurance,ouverte_le,expire_le)
     VALUES
     ('SESS-V1-EN-CLAIR','AUTHN-V1','ENTITE-V1','AS1','2026-07-30T00:00:00Z','2030-07-31T00:00:00Z')",
);
unset($v1);
$migre = Magasin::connecter($fichierV1);
$ctrMigre = new Ctr16($migre);
$ligneMigree = $migre->query(
    "SELECT reference,jeton_empreinte FROM session_ouverte WHERE entite_reference = 'ENTITE-V1'",
)->fetch();
$verifier(
    ($ctrMigre->verifierSession('SESS-V1-EN-CLAIR')['valide'] ?? false) === true
        && $ligneMigree['reference'] !== 'SESS-V1-EN-CLAIR'
        && $ligneMigree['jeton_empreinte'] === hash('sha256', 'SESS-V1-EN-CLAIR'),
    'la migration v2 conserve la session sans conserver son bearer en clair',
);

unset($magasin);
@unlink($fichier);
unset($migre);
@unlink($fichierV1);

echo "\n";
if ($echecs === 0) {
    echo "Preuve P3 : ÉTABLIE. CAP-CORE-005 atteint le niveau de preuve P3.\n";
    exit(0);
}
echo "Preuve P3 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
