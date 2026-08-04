<?php

declare(strict_types=1);

use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
require $application . '/vendor/autoload.php';

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — POSTGRESQL P0\n\n";

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$acces = AccesMagasin::connecter();
$identites = IdentiteMagasin::connecter();
$produits = ProduitsMagasin::connecter();
$sources = SourcesMagasin::connecter();
$politiques = PolitiquesMagasin::connecter();
$contrats = ContratsMagasin::connecter();
$vocabulaire = VocabulaireMagasin::connecter();
$organisations = OrganisationsMagasin::connecter();
$realms = RealmsMagasin::connecter();
$journalPdo = JournalMagasin::connecter();
$evenementsPdo = EvenementsMagasin::connecter();

$verifier(
    array_unique(array_map(
        static fn (\PDO $pdo): string => (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME),
        [$index, $acces, $identites, $produits, $sources, $politiques, $contrats, $vocabulaire, $organisations, $realms, $journalPdo, $evenementsPdo],
    )) === ['pgsql'],
    'les douze magasins utilisent réellement PostgreSQL',
);

$ctr16 = new Ctr16($acces);
$secret = 'PostgreSQL-P0-secret-de-test';
$ctr16->inscrireAuthentificateur('AUT-GAMAD-001', $secret);
$session = $ctr16->etablirSession('AUT-GAMAD-001', $secret);
$jeton = (string) ($session['session'] ?? '');
$fuite = $acces->prepare(
    'SELECT count(*) FROM session_ouverte WHERE reference = ? OR jeton_empreinte = ?',
);
$fuite->execute([$jeton, $jeton]);
$verifier(
    $jeton !== ''
        && ($ctr16->verifierSession($jeton)['valide'] ?? false) === true
        && (int) $fuite->fetchColumn() === 0,
    'la session PostgreSQL est résolue par empreinte, sans bearer en clair',
);

$journal = new Journal($journalPdo);
$preuve = $journal->enregistrer([
    'categorie' => 'EXPLOITATION',
    'type' => 'QUALIFICATION_POSTGRESQL',
    'acteur' => 'TEST-P0',
    'decision' => 'ETABLIE',
]);
$verifier(
    strlen((string) $preuve['empreinte']) === 64
        && $journal->verifierIntegrite()['valide'] === true,
    'le journal PostgreSQL produit et vérifie sa chaîne',
);
$mutationRefusee = false;
try {
    $journalPdo->exec(
        "UPDATE evenement_operationnel SET decision = 'FALSIFIEE'
         WHERE reference = " . $journalPdo->quote($preuve['reference']),
    );
} catch (\PDOException) {
    $mutationRefusee = true;
}
$verifier($mutationRefusee, 'le trigger PostgreSQL refuse UPDATE');

$app = require $application . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$requeteClaire = Request::create(
    '/api/v1/health/live',
    'GET',
    [],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json', 'SERVER_PORT' => 80],
);
$reponseClaire = $kernel->handle($requeteClaire);
$kernel->terminate($requeteClaire, $reponseClaire);
$verifier(
    $reponseClaire->getStatusCode() === 426,
    'l’API de production refuse HTTP en clair',
);

$request = Request::create(
    '/api/v1/health/ready',
    'GET',
    [],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json', 'HTTPS' => 'on', 'SERVER_PORT' => 443],
);
$response = $kernel->handle($request);
$corps = json_decode((string) $response->getContent(), true);
$kernel->terminate($request, $response);
$readinessOk = $response->getStatusCode() === 200
    && ($corps['pret'] ?? false) === true
    && ($corps['environnement'] ?? null) === 'production';
if (!$readinessOk) {
    fwrite(STDERR, json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}
$verifier(
    $readinessOk,
    'la readiness de production accepte le socle PostgreSQL complet',
);

echo "\n";
if ($echecs === 0) {
    echo "PostgreSQL P0 : ÉTABLI.\n";
    exit(0);
}

echo "PostgreSQL P0 : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
