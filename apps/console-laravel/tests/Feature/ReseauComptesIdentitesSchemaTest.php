<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('crée la liaison persistante sans importer de comptes historiques', function () {
    expect(Schema::hasTable('reseau_comptes_identites'))->toBeTrue();
    expect(Schema::hasColumns('reseau_comptes_identites', [
        'user_id', 'identite_reference', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});

it('interdit deux références Core pour le même compte', function () {
    $user = DB::table('users')->insertGetId([
        'name' => 'Compte Réseau',
        'email' => 'reseau-test-1@example.invalid',
        'password' => 'not-a-real-password-hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('reseau_comptes_identites')->insert([
        'user_id' => $user, 'identite_reference' => 'PER-001',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(fn () => DB::table('reseau_comptes_identites')->insert([
        'user_id' => $user, 'identite_reference' => 'PER-002',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('interdit qu une référence Core soit associée à deux comptes', function () {
    $premier = DB::table('users')->insertGetId([
        'name' => 'Compte Un',
        'email' => 'reseau-test-2@example.invalid',
        'password' => 'not-a-real-password-hash',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $second = DB::table('users')->insertGetId([
        'name' => 'Compte Deux',
        'email' => 'reseau-test-3@example.invalid',
        'password' => 'not-a-real-password-hash',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('reseau_comptes_identites')->insert([
        'user_id' => $premier, 'identite_reference' => 'PER-001',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(fn () => DB::table('reseau_comptes_identites')->insert([
        'user_id' => $second, 'identite_reference' => 'PER-001',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
