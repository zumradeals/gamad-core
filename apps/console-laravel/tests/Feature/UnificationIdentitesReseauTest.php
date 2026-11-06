<?php

use Illuminate\Support\Facades\Artisan;

it('bloque le raccordement direct des clés UUID du Réseau aux clés bigint du Core', function () {
    $path = tempnam(sys_get_temp_dir(), 'gamad-users-');
    try {
        file_put_contents($path, <<<'PHP'
<?php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
});
Schema::create('sessions', function (Blueprint $table) {
    $table->foreignUuid('user_id')->nullable()->index();
});
PHP);
        expect(Artisan::call('gamad:unification:identites', ['reseau-migration' => $path]))->toBe(1);
        expect(Artisan::output())->toContain('Réseau=uuid / Core=bigint');
    } finally {
        @unlink($path);
    }
});

it('refuse une migration manquante au lieu de supposer la compatibilité', function () {
    expect(Artisan::call('gamad:unification:identites', [
        'reseau-migration' => '/chemin/inexistant/gamad-users.php',
    ]))->toBe(1);
});

it('accepte uniquement la forme des clés identique sans prétendre valider les comptes', function () {
    $path = database_path('migrations/0001_01_01_000000_create_users_table.php');
    expect(Artisan::call('gamad:unification:identites', ['reseau-migration' => $path]))->toBe(0);
    expect(Artisan::output())->toContain('données réelles NON démontrée');
});
