<?php

declare(strict_types=1);

/**
 * Garde de la configuration email/SMS depuis la console dirigeant.
 *
 * Prouve que les secrets sont chiffrés au repos, qu'ils ne sont jamais remis
 * à la vue console et qu'un enregistrement ultérieur vide les conserve.
 */

use App\Application\Comptes\ConfigurationCanauxVerification;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-canaux-' . getmypid() . '.enc';
@unlink($temp);
register_shutdown_function(static fn () => @unlink($temp));

$cle = 'base64:' . base64_encode(str_repeat('v', 32));
foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => $cle,
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
] as $nom => $valeur) {
    putenv("{$nom}={$valeur}");
    $_ENV[$nom] = $valeur;
    $_SERVER[$nom] = $valeur;
}

require $application . '/vendor/autoload.php';
$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$echecs = 0;
$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $message);
    if (!$ok) {
        $echecs++;
    }
};

$service = new ConfigurationCanauxVerification($temp);
$motDePasse = 'smtp-secret-ne-doit-pas-fuiter';
$jetonSms = 'sms-token-ne-doit-pas-fuiter';

$console = $service->enregistrer([
    'email_enabled' => true,
    'email_driver' => 'smtp',
    'email_host' => 'smtp.example.test',
    'email_port' => 587,
    'email_scheme' => 'smtp',
    'email_username' => 'mailer@example.test',
    'email_password' => $motDePasse,
    'email_from_address' => 'no-reply@example.test',
    'email_from_name' => 'GAMAD',
    'email_subject' => 'Code GAMAD',
    'sms_enabled' => true,
    'sms_relay_url' => 'https://sms.example.test/send',
    'sms_relay_token' => $jetonSms,
    'sms_sender' => 'GAMAD',
    'sms_timeout' => 7,
]);

$brut = is_file($temp) ? (string) file_get_contents($temp) : '';
$verifier(is_file($temp), 'le fichier de configuration chiffré est créé');
$verifier($brut !== '' && !str_contains($brut, $motDePasse) && !str_contains($brut, $jetonSms), 'aucun secret n’est présent en clair sur disque');
$verifier(($console['email']['password_present'] ?? false) === true && !array_key_exists('password', $console['email']), 'la console voit seulement la présence du mot de passe SMTP');
$verifier(($console['sms']['token_present'] ?? false) === true && !array_key_exists('relay_token', $console['sms']), 'la console voit seulement la présence du jeton SMS');

$interne = $service->lire();
$verifier(($interne['email']['password'] ?? null) === $motDePasse, 'le Core peut relire le secret SMTP en interne');
$verifier(($interne['sms']['relay_token'] ?? null) === $jetonSms, 'le Core peut relire le jeton SMS en interne');

$service->enregistrer([
    'email_enabled' => true,
    'email_driver' => 'smtp',
    'email_host' => 'smtp2.example.test',
    'email_port' => 587,
    'email_scheme' => 'smtp',
    'email_username' => 'mailer@example.test',
    'email_password' => '',
    'email_from_address' => 'no-reply@example.test',
    'email_from_name' => 'GAMAD',
    'email_subject' => 'Code GAMAD',
    'sms_enabled' => true,
    'sms_relay_url' => 'https://sms.example.test/send',
    'sms_relay_token' => '',
    'sms_sender' => 'GAMAD',
    'sms_timeout' => 7,
]);
$relecture = $service->lire();
$verifier(($relecture['email']['password'] ?? null) === $motDePasse, 'laisser le mot de passe vide conserve le secret SMTP existant');
$verifier(($relecture['sms']['relay_token'] ?? null) === $jetonSms, 'laisser le jeton vide conserve le secret SMS existant');

$refus = false;
try {
    $service->enregistrer([
        'email_enabled' => false,
        'email_driver' => 'smtp',
        'email_port' => 587,
        'email_scheme' => 'smtp',
        'sms_enabled' => true,
        'sms_relay_url' => 'http://sms.example.test/send',
        'sms_relay_token' => 'x',
    ]);
} catch (\InvalidArgumentException) {
    $refus = true;
}
$verifier($refus, 'un relais SMS non HTTPS est refusé');

if (PHP_OS_FAMILY !== 'Windows' && is_file($temp)) {
    $mode = fileperms($temp) & 0777;
    $verifier($mode <= 0600, 'le fichier de configuration est protégé au maximum en 0600');
}

echo "\n";
if ($echecs === 0) {
    echo "Configuration canaux console : ÉTABLIE.\n";
    exit(0);
}

echo "Configuration canaux console : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
