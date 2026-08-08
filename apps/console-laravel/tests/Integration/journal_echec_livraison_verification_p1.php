<?php

declare(strict_types=1);

/**
 * Contre-épreuve de l'observabilité des échecs de livraison (email/SMS).
 *
 * `LivrerVerification` avalait ses exceptions sans laisser de trace
 * exploitable — constaté le 8 août 2026 en diagnostiquant un smoke test
 * production à la main. Cette épreuve prouve deux choses, pas une seule :
 *
 * 1. Un échec de livraison, sur les deux canaux, produit un événement du
 *    journal opérationnel structuré (canal, motif normalisé, produit
 *    demandeur, référence du défi/RID, corrélation) — exploitable sans
 *    relire les logs applicatifs à la main.
 * 2. Ni le code de vérification, ni l'adresse email complète, ni le numéro
 *    de téléphone complet, ni le mot de passe SMTP, ni le jeton du relais
 *    SMS, ni le message brut d'exception du fournisseur n'apparaissent nulle
 *    part dans cet événement — vérifié en cherchant leurs valeurs exactes
 *    dans la ligne entière, pas seulement dans les champs attendus.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/journal_echec_livraison_verification_p1.php
 */

use App\Application\Comptes\ConfigurationCanauxVerification;
use App\Application\Comptes\LivrerVerification;
use Gamad\RegistreNormes\Db;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-journal-livraison-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'journal' => $temp . '-journal.sqlite',
];
$configTemp = $temp . '-canaux.enc';
$cache = [
    $temp . '-config.php',
    $temp . '-events.php',
    $temp . '-packages.php',
    $temp . '-routes.php',
    $temp . '-services.php',
];
foreach (array_merge(array_values($fichiers), [$configTemp], $cache) as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers, $configTemp, $cache): void {
    foreach (array_merge(array_values($fichiers), [$configTemp], $cache) as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('j', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'MAIL_MAILER' => 'array',
    // Le canal de repli (.env) reste désactivé : ce n'est pas lui qu'on teste ici.
    'GAMAD_VERIFICATION_EMAIL_ENABLED' => 'false',
    'GAMAD_VERIFICATION_SMS_ENABLED' => 'false',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

$index = Db::connect();
$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "CONTRE-ÉPREUVE — JOURNAL DES ÉCHECS DE LIVRAISON (EMAIL / SMS)\n\n";

$motDePasseSmtp = 'SECRET-SMTP-NE-DOIT-JAMAIS-FUITER-93F1';
$jetonSms = 'SECRET-TOKEN-SMS-NE-DOIT-JAMAIS-FUITER-7A2C';

$config = new ConfigurationCanauxVerification($configTemp);
$config->enregistrer([
    'email_enabled' => true,
    'email_driver' => 'smtp',
    // Domaine réservé (RFC 2606), jamais résolu : garantit un échec de
    // connexion réel, déterministe, sans dépendance réseau externe.
    'email_host' => 'smtp.invalid',
    'email_port' => 587,
    'email_scheme' => 'smtp',
    'email_username' => 'mailer@example.test',
    'email_password' => $motDePasseSmtp,
    'email_from_address' => 'no-reply@example.test',
    'email_from_name' => 'GAMAD',
    'email_subject' => 'Code GAMAD',
    'sms_enabled' => true,
    'sms_relay_url' => 'https://relais.invalid/envoyer',
    'sms_relay_token' => $jetonSms,
    'sms_sender' => 'GAMAD',
    'sms_timeout' => 2,
]);

$livrer = new LivrerVerification($config);

$emailDestination = 'personne.confidentielle@example.test';
$codeOtpEmail = '482913';
$resultatEmail = $livrer->executer('EMAIL', $emailDestination, $codeOtpEmail, gmdate('c', time() + 600), [
    'produit' => 'PRD-GAMAD-005',
    'identifiant_reference' => 'RID-TEST-JOURNAL-EMAIL',
    'verification_reference' => 'VRF-TEST-JOURNAL-EMAIL',
    'correlation' => 'COR-TEST-JOURNAL-EMAIL',
]);
$verifier(
    $resultatEmail['livree'] === false && ($resultatEmail['motif'] ?? null) === 'ECHEC_LIVRAISON_EMAIL',
    'un hôte SMTP injoignable produit bien un échec de livraison EMAIL',
);

$telephoneDestination = '+225070000099';
$codeOtpSms = '317642';
$resultatSms = $livrer->executer('TELEPHONE', $telephoneDestination, $codeOtpSms, gmdate('c', time() + 600), [
    'produit' => 'PRD-GAMAD-005',
    'identifiant_reference' => 'RID-TEST-JOURNAL-SMS',
    'verification_reference' => 'VRF-TEST-JOURNAL-SMS',
    'correlation' => 'COR-TEST-JOURNAL-SMS',
]);
$verifier(
    $resultatSms['livree'] === false && ($resultatSms['motif'] ?? null) === 'RELAIS_SMS_INDISPONIBLE',
    'un relais SMS injoignable produit bien un échec de livraison TELEPHONE',
);

$journal = new \PDO('sqlite:' . $fichiers['journal']);
$journal->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$journal->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

$evenements = $journal->query(
    "SELECT * FROM evenement_operationnel WHERE categorie = 'LIVRAISON_VERIFICATION' ORDER BY sequence_id"
)->fetchAll();
$verifier(count($evenements) === 2, 'les deux échecs produisent chacun un événement du journal opérationnel');

$parCanal = [];
foreach ($evenements as $e) {
    $parCanal[$e['ressource']] = $e;
}
$evtEmail = $parCanal['EMAIL'] ?? null;
$evtSms = $parCanal['TELEPHONE'] ?? null;

$verifier(
    is_array($evtEmail)
        && $evtEmail['type_evenement'] === 'ECHEC_LIVRAISON_VERIFICATION'
        && $evtEmail['acteur'] === 'PRD-GAMAD-005'
        && $evtEmail['decision'] === 'ECHEC'
        && $evtEmail['motif'] === 'ECHEC_LIVRAISON_EMAIL'
        && $evtEmail['correlation_id'] === 'COR-TEST-JOURNAL-EMAIL',
    'l’événement EMAIL porte canal, motif normalisé, produit demandeur et corrélation — exploitable sans relire les logs',
);
$donneesEmail = json_decode((string) ($evtEmail['donnees'] ?? '{}'), true);
$verifier(
    is_array($donneesEmail)
        && ($donneesEmail['identifiant_reference'] ?? null) === 'RID-TEST-JOURNAL-EMAIL'
        && ($donneesEmail['verification_reference'] ?? null) === 'VRF-TEST-JOURNAL-EMAIL',
    'l’événement EMAIL porte la référence du défi et le RID, jamais l’adresse elle-même',
);

$verifier(
    is_array($evtSms)
        && $evtSms['type_evenement'] === 'ECHEC_LIVRAISON_VERIFICATION'
        && $evtSms['acteur'] === 'PRD-GAMAD-005'
        && $evtSms['decision'] === 'ECHEC'
        && $evtSms['motif'] === 'RELAIS_SMS_INDISPONIBLE'
        && $evtSms['correlation_id'] === 'COR-TEST-JOURNAL-SMS',
    'l’événement TELEPHONE porte canal, motif normalisé, produit demandeur et corrélation',
);
$donneesSms = json_decode((string) ($evtSms['donnees'] ?? '{}'), true);
$verifier(
    is_array($donneesSms)
        && ($donneesSms['identifiant_reference'] ?? null) === 'RID-TEST-JOURNAL-SMS'
        && ($donneesSms['verification_reference'] ?? null) === 'VRF-TEST-JOURNAL-SMS',
    'l’événement TELEPHONE porte la référence du défi et le RID, jamais le numéro lui-même',
);

// La ligne entière, pas seulement les champs qu'on attend à contrôler — un
// secret qui fuiterait dans un champ imprévu doit être détecté aussi.
$ligneComplete = json_encode($evenements, JSON_UNESCAPED_UNICODE);
$interdits = [
    'code de vérification (email)' => $codeOtpEmail,
    'code de vérification (sms)' => $codeOtpSms,
    'adresse email complète' => $emailDestination,
    'numéro de téléphone complet' => $telephoneDestination,
    'mot de passe SMTP' => $motDePasseSmtp,
    'jeton du relais SMS' => $jetonSms,
];
foreach ($interdits as $libelle => $valeur) {
    $verifier(
        !str_contains((string) $ligneComplete, $valeur),
        "aucune trace de : {$libelle}",
    );
}

// L'extraction du code SMTP ne recopie jamais le message complet du
// fournisseur — même quand ce message contient, par construction, une
// adresse et un code : seuls les trois premiers chiffres franchissent la
// frontière vers le journal.
$reflet = new ReflectionMethod(LivrerVerification::class, 'extraireCodeSmtp');
$reflet->setAccessible(true);
$exceptionHostile = new RuntimeException(
    '550 No Such User Here — rejet pour personne.confidentielle@example.test, code 482913 refusé'
);
$codeExtrait = $reflet->invoke($livrer, $exceptionHostile);
$verifier(
    $codeExtrait === '550',
    'un message d’erreur fournisseur commençant par un code SMTP en extrait exactement les trois chiffres',
);
$verifier(
    !str_contains((string) $codeExtrait, 'personne.confidentielle') && !str_contains((string) $codeExtrait, '482913'),
    'le code extrait ne contient jamais le reste du message, même hostile',
);

echo "\n";
if ($echecs === 0) {
    echo "Contre-épreuve journal des échecs de livraison : ÉTABLIE.\n";
    exit(0);
}

echo "Contre-épreuve journal des échecs de livraison : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
