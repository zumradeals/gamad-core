<?php

use App\Support\Unification\VerifierRegistreIdentites;

function registreTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE identite_inscrite (reference TEXT PRIMARY KEY, type TEXT, regime TEXT, provisoire INTEGER)');
    $pdo->exec("INSERT INTO identite_inscrite VALUES ('PER-001', 'personne', 'INSCRIT_AU_REGISTRE', 0)");
    $pdo->exec("INSERT INTO identite_inscrite VALUES ('PER-002', 'personne', 'INSCRIT_AU_REGISTRE', 1)");
    $pdo->exec("INSERT INTO identite_inscrite VALUES ('ORG-001', 'organisation', 'INSCRIT_AU_REGISTRE', 0)");
    return $pdo;
}

it('valide uniquement une personne inscrite non provisoire', function () {
    $resultat = VerifierRegistreIdentites::verifier(registreTestPdo(), [
        ['reseau_user_id' => '550e8400-e29b-41d4-a716-446655440000', 'core_reference' => 'PER-001'],
    ]);
    expect($resultat['conflits'])->toBe([]);
    expect($resultat['valides'])->toHaveCount(1);
});

it('refuse les identités absentes, provisoires ou non humaines sans accepter partiellement le lot', function () {
    $liens = [];
    foreach (['PER-001', 'PER-ABSENT', 'PER-002', 'ORG-001'] as $i => $ref) {
        $liens[] = [
            'reseau_user_id' => sprintf('550e8400-e29b-41d4-a716-%012d', $i),
            'core_reference' => $ref,
        ];
    }
    $resultat = VerifierRegistreIdentites::verifier(registreTestPdo(), $liens);
    expect($resultat['valides'])->toBe([]);
    expect($resultat['conflits'])->toHaveCount(3);
});

it('refuse un schéma absent sans divulguer les détails SQL', function () {
    $pdo = new PDO('sqlite::memory:');
    $resultat = VerifierRegistreIdentites::verifier($pdo, [
        ['reseau_user_id' => '550e8400-e29b-41d4-a716-446655440000', 'core_reference' => 'PER-001'],
    ]);
    expect($resultat['valides'])->toBe([]);
    expect($resultat['conflits'])->toBe(['Registre Core inaccessible ou schéma incompatible']);
});

it('refuse les doublons avant toute interrogation du registre', function () {
    $resultat = VerifierRegistreIdentites::verifier(registreTestPdo(), [
        ['reseau_user_id' => '550e8400-e29b-41d4-a716-446655440000', 'core_reference' => 'PER-001'],
        ['reseau_user_id' => '550e8400-e29b-41d4-a716-446655440000', 'core_reference' => 'PER-002'],
    ]);
    expect($resultat['valides'])->toBe([]);
    expect($resultat['conflits'])->toHaveCount(1);
});
