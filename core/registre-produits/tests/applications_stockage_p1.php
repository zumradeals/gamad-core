<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/SchemaProduits.php';
use Gamad\RegistreProduits\SchemaProduits;

// PostgreSQL : schéma isolé jetable, jamais le schéma public ni les données existantes.
$dsn = getenv('GAMAD_TEST_PG_DSN');
$pdo = $dsn ? new PDO($dsn, getenv('GAMAD_TEST_PG_USER') ?: null, getenv('GAMAD_TEST_PG_PASSWORD') ?: null) : new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$schema = 'test_app_'.bin2hex(random_bytes(6));
if ($dsn) {
    $pdo->exec("CREATE SCHEMA {$schema}");
    $pdo->exec("SET search_path TO {$schema}");
} else {
    $pdo->exec('PRAGMA foreign_keys = ON');
}
try {
    SchemaProduits::migrer($pdo);
    $insert = $pdo->prepare('INSERT INTO produit VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)');
    foreach (['A', 'B', 'C'] as $ref) {
        $insert->execute([$ref, 'ID-'.$ref, $ref, $ref, 'APPLICATION_INTERNE', 'AUT', 'SOURCE', 'POL', 'AUT', 'PREUVE', gmdate('c'), gmdate('c')]);
    }
    $declarer = $pdo->prepare('INSERT INTO produit_application VALUES (?, ?, ?, ?, ?, ?, 1)');
    $declarer->execute(['A', 'gamad.a', 'a.gamad-afrique.com', 'a.accueil', 'a.console', '[]']);
    foreach ([['B', 'gamad.a', 'b.gamad-afrique.com'], ['B', 'gamad.b', 'a.gamad-afrique.com']] as $collision) {
        $pdo->beginTransaction();
        try {
            $declarer->execute([...$collision, 'b.accueil', 'b.console', '[]']);
            throw new RuntimeException('Collision acceptée par la base');
        } catch (PDOException $e) {
            if (!in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw $e;
            }
        } finally {
            $pdo->rollBack();
        }
    }
    $declarer->execute(['B', 'gamad.b', null, 'b.accueil', 'b.console', '[]']);
    $declarer->execute(['C', 'gamad.c', null, 'c.accueil', 'c.console', '[]']);
    SchemaProduits::migrer($pdo);
    if ((int) $pdo->query('SELECT COUNT(*) FROM produit_application')->fetchColumn() !== 3) {
        throw new RuntimeException('Migration non idempotente');
    }
    echo 'OK — stockage applicatif '.$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)." : unicités, rollback et migration additive.\n";
} finally {
    if ($dsn) {
        $pdo->exec('SET search_path TO public');
        $pdo->exec("DROP SCHEMA {$schema} CASCADE");
    }
}
