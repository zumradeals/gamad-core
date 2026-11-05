<?php

declare(strict_types=1);

/**
 * R01 — épreuves de la frontière session / console.
 * Exécution : php apps/console-laravel/tests/Integration/reseau_r01_console_p1.php
 * Les règles de test sont insérées dans un magasin SQLite isolé, jamais dans
 * le bootstrap institutionnel ni dans les registres d'exploitation.
 */

use App\Http\Middleware\ExigerAccesConsole;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-r01-'.getmypid();
foreach (['index', 'politiques'] as $nom) {
    @unlink($temp.'-'.$nom.'.sqlite');
}
register_shutdown_function(static function () use ($temp): void {
    @unlink($temp.'-index.sqlite');
    @unlink($temp.'-politiques.sqlite');
});

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('r', 32)),
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $temp.'-index.sqlite',
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $temp.'-politiques.sqlite',
] as $cle => $valeur) {
    putenv($cle.'='.$valeur);
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';
$app = require $application.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
BaselineOperationnelle::standard()->reconstruire(Db::connect());
$pdo = PolitiquesMagasin::connecter();

$garde = new ExigerAccesConsole();
$passage = static fn (): \Symfony\Component\HttpFoundation\Response => response('autorise', 200);
$essai = static function (string $acteur) use ($garde, $passage): int {
    $request = Request::create('/identites', 'GET');
    $request->attributes->set('gamad_entite', $acteur);
    try {
        return $garde->handle($request, $passage)->getStatusCode();
    } catch (HttpExceptionInterface $e) {
        return $e->getStatusCode();
    }
};
$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    echo ($ok ? '[OK] ' : '[ECHEC] ').$libelle.PHP_EOL;
    if (!$ok) {
        $echecs++;
    }
};

$verifier($essai('') === 403, 'aucune identite : refus');
$verifier($essai('IDN-PER-INCONNUE') === 403, 'personne sans mandat : refus');
$verifier($essai('AUT-GAMAD-001') === 403, 'mandat sans politique explicite : refus');

// Politique fictive de test, indépendante du bootstrap gouverné.
$maintenant = gmdate('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO politique
 (reference,libelle,proprietaire_reference,source_reference,politique_inscription,producteur,preuve_reference,cree_le,modifie_le)
 VALUES (?,?,?,?,?,?,?,?,?)')->execute([
    'POL-R01-TEST', 'R01 test uniquement', 'AUT-GAMAD-001', 'TEST-R01',
    'TEST', 'TEST', 'TEST-R01', $maintenant, $maintenant,
]);
$pdo->prepare('INSERT INTO politique_version
 (politique_reference,version,cree_par_reference,preuve_reference,cree_le)
 VALUES (?,?,?,?,?)')->execute(['POL-R01-TEST', '1.0.0', 'AUT-GAMAD-001', 'TEST-R01', $maintenant]);
$id = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO regle_politique
 (politique_version_id,ordre,effet,action_reference,sujet_reference,motif,cree_le)
 VALUES (?,?,?,?,?,?,?)')->execute([
    $id, 1, 'PERMET', 'consulter-la-console', 'AUT-GAMAD-001',
    'Permission fictive limitee au test', $maintenant,
]);
$pdo->prepare('INSERT INTO politique_version_cycle
 (politique_version_id,etat,date_effet,acteur_reference,preuve_reference)
 VALUES (?,?,?,?,?)')->execute([$id, 'ACTIVE', $maintenant, 'AUT-GAMAD-001', 'TEST-R01']);

$verifier($essai('AUT-GAMAD-001') === 200, 'mandat actif et politique explicite : autorise');
$verifier($essai('IDN-PER-INCONNUE') === 403, 'politique autorisant autrui : aucun mandat, refus');
$pdo->prepare('INSERT INTO politique_version_cycle
 (politique_version_id,etat,date_effet,acteur_reference,preuve_reference)
 VALUES (?,?,?,?,?)')->execute([$id, 'SUSPENDUE', gmdate('Y-m-d H:i:s', time()+1), 'AUT-GAMAD-001', 'TEST-R01']);
$verifier($essai('AUT-GAMAD-001') === 403, 'politique suspendue : refus');

$routes = file_get_contents($application.'/routes/web.php');
$verifier(
    str_contains($routes, "Route::middleware(['gamad.session', 'gamad.console'])")
        && str_contains($routes, "Route::middleware('gamad.session')->group")
        && str_contains($routes, "Route::get('/mon-acces'"),
    'routes administratives et personnelles distinctes',
);

echo $echecs === 0 ? 'R01 : ETABLI'.PHP_EOL : "R01 : {$echecs} ECHEC(S)".PHP_EOL;
exit($echecs === 0 ? 0 : 1);
