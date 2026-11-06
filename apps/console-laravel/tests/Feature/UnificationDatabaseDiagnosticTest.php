<?php

use Illuminate\Support\Facades\Artisan;

it('refuse une cible Core dispersée sur plusieurs bases', function () {
    config()->set('database.default', 'gamad_index');
    config()->set('database.connections.gamad_index', [
        'driver' => 'pgsql', 'host' => 'localhost', 'port' => 5432, 'database' => 'gamad',
    ]);
    config()->set('database.connections.gamad_identity', [
        'driver' => 'pgsql', 'host' => 'localhost', 'port' => 5432, 'database' => 'identity_other',
    ]);

    expect(Artisan::call('gamad:unification:diagnostic'))->toBe(1);
});

it('ne considère pas une URL masquée comme preuve de base unique', function () {
    config()->set('database.default', 'gamad_index');
    config()->set('database.connections', [
        'gamad_index' => ['driver' => 'pgsql', 'url' => 'postgres://secret@localhost/gamad'],
    ]);

    expect(Artisan::call('gamad:unification:diagnostic'))->toBe(1);
});

it('accepte uniquement une cible PostgreSQL commune déclarée', function () {
    config()->set('database.default', 'gamad_index');
    $target = ['driver' => 'pgsql', 'host' => 'localhost', 'port' => 5432, 'database' => 'gamad'];
    config()->set('database.connections', [
        'gamad_index' => $target,
        'gamad_identity' => $target,
        'gamad_journal' => $target,
    ]);

    expect(Artisan::call('gamad:unification:diagnostic'))->toBe(0);
});
