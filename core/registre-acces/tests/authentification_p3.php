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

// --- Facteur fort A2 : autorisation d'enrôlement à usage unique, challenge
// brûlé et credential public seulement.
$autorisation = $ctr16->preparerEnrolementPasskey('ENTITE-DE-TEST', 300);
$verifier(
    $ctr16->verifierAutorisationEnrolement('ENTITE-DE-TEST', 'jeton-errone') === null
        && $ctr16->verifierAutorisationEnrolement(
            'ENTITE-DE-TEST',
            $autorisation['jeton'],
        ) !== null,
    'seul le jeton éphémère exact autorise l’enrôlement',
);
$brutMagasin = (string) file_get_contents($fichier);
$verifier(
    !str_contains($brutMagasin, $autorisation['jeton']),
    'le jeton d’enrôlement n’est jamais conservé en clair',
);

$ceremonie = $ctr16->enregistrerCeremoniePasskey(
    'ENTITE-DE-TEST',
    'ENROLEMENT',
    '{"challenge":"public-et-temporaire"}',
    300,
);
$premiereConsommation = $ctr16->consommerCeremoniePasskey(
    $ceremonie['reference'],
    'ENROLEMENT',
    'ENTITE-DE-TEST',
);
$verifier(
    is_array($premiereConsommation)
        && $ctr16->consommerCeremoniePasskey(
            $ceremonie['reference'],
            'ENROLEMENT',
            'ENTITE-DE-TEST',
        ) === null,
    'une cérémonie WebAuthn est brûlée à sa première réponse',
);

$passkey = $ctr16->inscrirePasskey(
    'ENTITE-DE-TEST',
    'credential-public-de-test',
    'user-handle-opaque-de-test',
    '{"cle_publique":"donnee-de-test"}',
    'Clé de test',
    $autorisation['reference'],
);
$verifier(
    $ctr16->verifierAutorisationEnrolement(
        'ENTITE-DE-TEST',
        $autorisation['jeton'],
    ) === null,
    'l’autorisation est consommée atomiquement avec la passkey',
);
$sessionA2 = $ctr16->etablirSessionPasskey($passkey);
$verifier(
    is_array($sessionA2)
        && ($sessionA2['assurance'] ?? null) === 'A2 — FACTEUR FORT'
        && ($ctr16->verifierSession((string) $sessionA2['session'])['valide'] ?? false) === true,
    'une passkey vérifiée ouvre une session A2',
);
$attestationA2 = $ctr16->attester('ENTITE-DE-TEST');
$fuiteCredential = false;
foreach ($attestationA2['authentificateurs'] as $a) {
    if (array_key_exists('credential_record', $a)
        || array_key_exists('credential_id', $a)
        || array_key_exists('user_handle', $a)) {
        $fuiteCredential = true;
    }
}
$verifier(
    !$fuiteCredential,
    'l’attestation publique ne restitue aucun matériau de credential',
);
$verifier(
    $ctr16->revoquerPasskey($passkey)
        && ($ctr16->verifierSession((string) $sessionA2['session'])['valide'] ?? true) === false,
    'révoquer la passkey invalide immédiatement sa session A2',
);

$limiteRefusee = false;
for ($i = 1; $i <= 5; $i++) {
    $autorisationLimite = $ctr16->preparerEnrolementPasskey('ENTITE-DE-TEST');
    $ctr16->inscrirePasskey(
        'ENTITE-DE-TEST',
        "credential-limite-{$i}",
        "user-handle-limite-{$i}",
        "{\"cle_publique\":\"limite-{$i}\"}",
        "Clé limite {$i}",
        $autorisationLimite['reference'],
    );
}
$autorisationSixieme = $ctr16->preparerEnrolementPasskey('ENTITE-DE-TEST');
try {
    $ctr16->inscrirePasskey(
        'ENTITE-DE-TEST',
        'credential-limite-6',
        'user-handle-limite-6',
        '{"cle_publique":"limite-6"}',
        'Clé limite 6',
        $autorisationSixieme['reference'],
    );
} catch (\RuntimeException) {
    $limiteRefusee = true;
}
$verifier(
    $limiteRefusee
        && $ctr16->verifierAutorisationEnrolement(
            'ENTITE-DE-TEST',
            $autorisationSixieme['jeton'],
        ) !== null,
    'la sixième passkey est refusée sans consommer son autorisation',
);

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
    'la migration additive conserve la session sans conserver son bearer en clair',
);


// Codes de secours : le second chemin, à usage unique.
$entiteSecours = 'ENTITE-SECOURS';
$ctr16->inscrireAuthentificateur($entiteSecours, 'Mot-de-passe-initial-1!');
$codes = $ctr16->engendrerCodesSecours($entiteSecours);
$verifier(
    count($codes) === 8
        && count(array_unique($codes)) === 8
        && $ctr16->codesSecoursRestants($entiteSecours) === 8
        && preg_match('/^[0-9A-F]{4}(-[0-9A-F]{4}){3}$/', $codes[0]) === 1,
    'un jeu de huit codes de secours distincts est engendré',
);

$sessionCode = $ctr16->etablirSession($entiteSecours, $codes[0]);
$rejeu = $ctr16->etablirSession($entiteSecours, $codes[0]);
$verifier(
    ($sessionCode['code_secours_consomme'] ?? false) === true
        && ($ctr16->verifierSession((string) $sessionCode['session'])['valide'] ?? false) === true
        && $rejeu === null
        && $ctr16->codesSecoursRestants($entiteSecours) === 7,
    'un code de secours ouvre une session une fois, et une seule',
);

$consomme = $ctr16->consommerCodeSecours($entiteSecours, $codes[1]);
$consommeDeux = $ctr16->consommerCodeSecours($entiteSecours, $codes[1]);
$verifier(
    $consomme === true && $consommeDeux === false
        && $ctr16->codesSecoursRestants($entiteSecours) === 6,
    'un code consommé hors session ne se rejoue pas davantage',
);

$anciens = $codes;
$ctr16->engendrerCodesSecours($entiteSecours);
$verifier(
    $ctr16->codesSecoursRestants($entiteSecours) === 8
        && $ctr16->consommerCodeSecours($entiteSecours, $anciens[2]) === false,
    'engendrer un nouveau jeu annule le précédent',
);

// Le magasin ne conserve aucun code en clair.
$brut = (string) file_get_contents($fichier);
$verifier(
    !str_contains($brut, $anciens[3]) && !str_contains($brut, $codes[3] ?? 'inexistant'),
    'aucun code de secours n’est conservé en clair',
);

// Nul ne retire son dernier moyen d'accès, pas même en le demandant.
$entiteSeule = 'ENTITE-MOYEN-UNIQUE';
$seul = $ctr16->inscrireAuthentificateur($entiteSeule, 'Mot-de-passe-unique-1!');
$refusDernier = $ctr16->revoquerMoyenAcces($entiteSeule, $seul);
$second = $ctr16->inscrireAuthentificateur($entiteSeule, 'Mot-de-passe-second-1!');
$retrait = $ctr16->revoquerMoyenAcces($entiteSeule, $seul);
$etranger = $ctr16->revoquerMoyenAcces('ENTITE-AUTRE', $second);
$verifier(
    ($refusDernier['refus'] ?? null) === 'DERNIER_MOYEN'
        && ($retrait['etat'] ?? null) === 'RÉVOQUÉ'
        && ($etranger['refus'] ?? null) === 'MOYEN_INTROUVABLE'
        && $ctr16->etablirSession($entiteSeule, 'Mot-de-passe-unique-1!') === null
        && $ctr16->etablirSession($entiteSeule, 'Mot-de-passe-second-1!') !== null,
    'le dernier moyen d’accès ne se retire pas, et nul ne retire celui d’autrui',
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
