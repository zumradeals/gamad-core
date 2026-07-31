<?php

declare(strict_types=1);

use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;

$racine = dirname(__DIR__);
$autoload = $racine.'/apps/console-laravel/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Dépendances absentes : exécuter composer install dans apps/console-laravel.\n");
    exit(1);
}

require $autoload;

$sortie = $argv[1] ?? null;
if (! is_string($sortie) || trim($sortie) === '') {
    fwrite(STDERR, "Usage : php outils/exporter-baseline-index.php <fichier.json>\n");
    exit(1);
}

$temp = tempnam(sys_get_temp_dir(), 'gamad-baseline-');
if ($temp === false) {
    fwrite(STDERR, "Impossible de créer la base temporaire.\n");
    exit(1);
}

register_shutdown_function(static function () use ($temp): void {
    @unlink($temp);
});

putenv('DATABASE_URL=');
putenv('SQLITE_PATH='.$temp);
$_ENV['DATABASE_URL'] = '';
$_SERVER['DATABASE_URL'] = '';
$_ENV['SQLITE_PATH'] = $temp;
$_SERVER['SQLITE_PATH'] = $temp;

$pdo = Db::connect();
$compteurs = (new Ingestion($pdo, $racine))->executer();

$tables = $pdo->query(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);

$contenu = [];
foreach ($tables as $table) {
    if (! is_string($table) || preg_match('/^[a-z_]+$/', $table) !== 1) {
        throw new RuntimeException('Nom de table inattendu dans la baseline.');
    }

    $colonnes = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
    $noms = array_map(static fn (array $colonne): string => (string) $colonne['name'], $colonnes);
    $ordre = array_values(array_map(
        static fn (array $colonne): string => (string) $colonne['name'],
        array_filter($colonnes, static fn (array $colonne): bool => (int) $colonne['pk'] > 0),
    ));
    if ($ordre === []) {
        $ordre = $noms;
    }

    $sql = "SELECT * FROM {$table}";
    if ($ordre !== []) {
        $sql .= ' ORDER BY '.implode(', ', $ordre);
    }

    $contenu[$table] = [
        'colonnes' => $noms,
        'lignes' => $pdo->query($sql)->fetchAll(),
    ];
}

$payload = [
    'format' => 'gamad-core-index-baseline',
    'version' => 1,
    'source' => [
        'type' => 'migration-legacy-genesis-ii',
        'commit' => getenv('GITHUB_SHA') ?: null,
        'note' => 'Photographie technique transitoire de l’index avant découplage de la commande registre:reindexer.',
    ],
    'compteurs' => $compteurs,
    'tables' => $contenu,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
@mkdir(dirname($sortie), 0777, true);
if (file_put_contents($sortie, $json) === false) {
    fwrite(STDERR, "Impossible d’écrire {$sortie}.\n");
    exit(1);
}

printf("Baseline exportée : %s (%d octets, SHA-256 %s)\n", $sortie, strlen($json), hash('sha256', $json));
