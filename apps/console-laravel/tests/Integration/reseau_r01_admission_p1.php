<?php

declare(strict_types=1);

/**
 * R01 — exécution réelle de la commande sur des registres SQLite isolés.
 * Ne touche ni au bootstrap figé ni aux données d'exploitation.
 */
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use App\Http\Middleware\ExigerAccesConsole;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-r01-admission-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
];
foreach ($fichiers as $fichier) @unlink($fichier);
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) @unlink($fichier);
});
foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('r', 32)),
    'APP_CONFIG_CACHE' => $temp.'-config.php',
    'APP_EVENTS_CACHE' => $temp.'-events.php',
    'APP_PACKAGES_CACHE' => $temp.'-packages.php',
    'APP_ROUTES_CACHE' => $temp.'-routes.php',
    'APP_SERVICES_CACHE' => $temp.'-services.php',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
] as $cle => $valeur) {
    putenv($cle.'='.$valeur);
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}
require $application.'/vendor/autoload.php';
BaselineOperationnelle::standard()->reconstruire(Db::connect());
$app = require $application.'/bootstrap/app.php';
$console = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();
$pdo = PolitiquesMagasin::connecter();
$ctr = new Ctr03($pdo);
$echecs = 0;
$verifier = static function (bool $ok, string $titre) use (&$echecs): void {
    echo ($ok ? '[OK] ' : '[ECHEC] ').$titre.PHP_EOL;
    if (!$ok) $echecs++;
};
$compter = static function () use ($pdo): array {
    return [
        (int) $pdo->query("SELECT COUNT(*) FROM politique WHERE reference = 'POL-ACCES-CONSOLE-ADMIN-V1'")->fetchColumn(),
        (int) $pdo->query("SELECT COUNT(*) FROM politique_version WHERE politique_reference = 'POL-ACCES-CONSOLE-ADMIN-V1'")->fetchColumn(),
        (int) $pdo->query("SELECT COUNT(*) FROM regle_politique WHERE politique_version_id IN (SELECT id FROM politique_version WHERE politique_reference = 'POL-ACCES-CONSOLE-ADMIN-V1')")->fetchColumn(),
    ];
};

$verifier($ctr->autoriser('AUT-GAMAD-001', 'consulter la console')['decision'] === 'REFUSÉ', 'absence de politique : refus initial');
$premier = $console->call('core:console:accorder-acces-administratif');
if ($premier !== 0) echo '[DIAGNOSTIC] '.trim($console->output()).PHP_EOL;
$verifier($premier === 0, 'commande : activation réussie');
$verifier($ctr->autoriser('AUT-GAMAD-001', 'consulter la console')['decision'] === 'PERMIS', 'autorité explicitement autorisée');
$verifier($ctr->autoriser('IDN-PER-INCONNUE', 'consulter la console')['decision'] === 'REFUSÉ', 'identité ordinaire refusée');
$verifier($ctr->autoriser('AUT-GAMAD-001', 'modifier les identités')['decision'] === 'REFUSÉ', 'permission sans élargissement implicite');
$avant = $compter();
$second = $console->call('core:console:accorder-acces-administratif');
$verifier($second === 0 && $compter() === $avant, 'seconde exécution idempotente : aucune écriture');
$verifier($avant === [1,1,1], 'politique, version et règle uniques');

$id = (int) $pdo->query("SELECT id FROM politique_version WHERE politique_reference = 'POL-ACCES-CONSOLE-ADMIN-V1'")->fetchColumn();
$maintenant = gmdate('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO politique_version_cycle (politique_version_id,etat,date_effet,acteur_reference,preuve_reference,cree_le) VALUES (?,?,?,?,?,?)')
    ->execute([$id, 'SUSPENDUE', $maintenant, 'AUT-GAMAD-001', 'R01-TEST-SUSPENSION', $maintenant]);
$verifier($ctr->autoriser('AUT-GAMAD-001', 'consulter la console')['decision'] === 'REFUSÉ', 'suspension : retrait immédiat de la permission');
$verifier($console->call('core:console:accorder-acces-administratif') !== 0, 'commande ne réactive pas une politique suspendue');

echo $echecs === 0 ? 'R01 ADMISSION : ETABLIE'.PHP_EOL : "R01 ADMISSION : {$echecs} ECHEC(S)".PHP_EOL;
exit($echecs === 0 ? 0 : 1);
