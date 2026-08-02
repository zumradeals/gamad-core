<?php

declare(strict_types=1);

/**
 * Vérifie le comportement de `Ctr03Controller` face au registre persistant
 * des politiques (CAP-CORE-007).
 *
 * Avant ce chantier, ce contrôleur reconstruisait silencieusement l'index
 * documentaire si `regle` était vide — un magasin persistant ne se comporte
 * pas ainsi : un registre vide reste vide, refuse tout par défaut, et
 * n'invente jamais son contenu depuis une baseline.
 */

use App\Http\Controllers\Ctr03Controller;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;

$application = dirname(__DIR__, 2);
$racine = dirname($application, 2);
$temp = sys_get_temp_dir().'/gamad-ctr03-baseline-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'politiques' => $temp.'-politiques.sqlite',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
    'APP_CONFIG_CACHE' => $temp.'-config.php',
    'APP_EVENTS_CACHE' => $temp.'-events.php',
    'APP_PACKAGES_CACHE' => $temp.'-packages.php',
    'APP_ROUTES_CACHE' => $temp.'-routes.php',
    'APP_SERVICES_CACHE' => $temp.'-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';
$app = require $application.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) { $echecs++; }
};

echo "INTÉGRATION — CTR-03 PAR REGISTRE PERSISTANT P1\n\n";
$verifier(! is_file($fichiers['politiques']), 'le scénario démarre sans magasin de politiques préconstruit');

$controleur = new Ctr03Controller();
$methode = new ReflectionMethod($controleur, 'ctr03');
$methode->setAccessible(true);
$ctr03Vide = $methode->invoke($controleur);

// Un magasin vide n'est jamais reconstruit silencieusement : il refuse tout.
$refuseSansBootstrap = $ctr03Vide->simuler('AUT-GAMAD-001', 'inscrire une identité', 'personne');
$verifier(
    ($refuseSansBootstrap['decision'] ?? null) !== 'PERMIS'
        && ($refuseSansBootstrap['politique'] ?? null) === null,
    'un registre de politiques vide refuse tout par défaut, sans reconstruction silencieuse',
);

// Bootstrap réel — même ressource figée que `core:politiques:bootstrap`.
$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$registreIdentites = IdentiteMagasin::connecter($fichiers['identites']);
$ctr01 = new Ctr01($index, $registreIdentites);
$magasinPolitiques = PolitiquesMagasin::connecter($fichiers['politiques']);
$registre = new RegistrePolitiques($index, $registreIdentites, $magasinPolitiques, $ctr01);
$bootstrap = json_decode(
    file_get_contents($racine.'/core/registre-politiques/resources/bootstrap-politiques-v1.json'),
    true,
);
$p = null;
foreach ($bootstrap['politiques'] as $ligne) {
    if ($ligne['reference'] === 'POL-INSCRIPTION-IDENTITES-V1') {
        $p = $ligne;
        break;
    }
}
$g = static fn (): array => [
    'politique' => PolitiqueAdministration::POLITIQUE, 'producteur' => 'AUT-GAMAD-001',
    'source' => 'intégration CTR-03', 'preuve' => 'P-'.bin2hex(random_bytes(4)),
];
$registre->inscrirePolitique(array_merge($g(), [
    'reference' => $p['reference'], 'libelle' => $p['libelle'],
    'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => $p['source'],
]));
$registre->creerVersion($p['reference'], array_merge($g(), ['version' => $p['version']]));
$cas = [];
foreach ($p['regles'] as $r) {
    $registre->ajouterRegle($p['reference'], $p['version'], array_merge($g(), [
        'effet' => $r['effet'], 'action_reference' => $r['action'],
        'sujet_reference' => $r['sujet_type'], 'motif' => $r['motif'],
    ]));
    $cas[] = ['sujet' => $r['sujet_type'] ?? 'AUT-GAMAD-001', 'action' => $r['action'], 'attendu' => $r['effet'] === 'PERMET' ? 'PERMIS' : 'REFUSE'];
}
$registre->soumettreVersion($p['reference'], $p['version'], $g());
$registre->simulerVersion($p['reference'], $p['version'], array_merge($g(), ['jeu_reference' => 'CTR03-P1', 'cas' => $cas]));
$registre->activerVersion($p['reference'], $p['version'], $g());

$ctr03 = $methode->invoke($controleur);
$permise = $ctr03->simuler('AUT-GAMAD-001', 'inscrire une identité', 'personne');
$verifier(
    ($permise['decision'] ?? null) === 'PERMIS'
        && ($permise['politique'] ?? null) === 'POL-INSCRIPTION-IDENTITES-V1',
    'après bootstrap, la décision historiquement permise redevient permise',
);

$refusee = $ctr03->simuler('SUJET-INCONNU', 'action inconnue', null);
$verifier(
    ($refusee['decision'] ?? null) !== 'PERMIS',
    'une action inconnue reste refusée par défaut',
);

$ctr03Seconde = $methode->invoke($controleur);
$permiseApres = $ctr03Seconde->simuler('AUT-GAMAD-001', 'inscrire une identité', 'personne');
$verifier(
    ($permiseApres['decision'] ?? null) === 'PERMIS',
    'une seconde résolution reste non destructive',
);

$source = file_get_contents($racine.'/apps/console-laravel/app/Http/Controllers/Ctr03Controller.php');
$verifier(
    is_string($source)
        && ! str_contains($source, 'BaselineOperationnelle')
        && ! str_contains($source, 'Ingestion')
        && ! str_contains($source, 'genesis-ii'),
    'le contrôleur ne reconstruit plus la baseline et ne dépend plus du parseur ni du corpus historique',
);

echo "\n";
if ($echecs === 0) {
    echo "CTR-03 par registre persistant P1 : ÉTABLI.\n";
    exit(0);
}

echo "CTR-03 par registre persistant P1 : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
