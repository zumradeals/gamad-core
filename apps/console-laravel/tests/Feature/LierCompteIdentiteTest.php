<?php

use App\Support\Unification\CompteReseauUnifie;
use App\Support\Unification\LierCompteIdentite;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('identite_inscrite', function (Blueprint $table) {
        $table->string('reference')->primary();
        $table->string('type');
        $table->string('regime');
        $table->integer('provisoire');
    });
    DB::table('identite_inscrite')->insert([
        ['reference' => 'PER-001', 'type' => 'personne', 'regime' => 'INSCRIT_AU_REGISTRE', 'provisoire' => 0],
        ['reference' => 'PER-002', 'type' => 'personne', 'regime' => 'INSCRIT_AU_REGISTRE', 'provisoire' => 1],
    ]);
});

function comptePourLiaison(string $email): CompteReseauUnifie
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Réseau Test', 'email' => $email, 'password' => 'not-a-real-hash',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    return CompteReseauUnifie::creer((string) $id, 'PER-001');
}

it('lie un compte autorisé à une identité admissible', function () {
    $compte = comptePourLiaison('liaison-1@example.invalid');
    LierCompteIdentite::executer($compte, true);
    expect(DB::table('reseau_comptes_identites')->where('user_id', $compte->userId)
        ->where('identite_reference', 'PER-001')->exists())->toBeTrue();
});

it('refuse toute liaison sans autorisation explicite', function () {
    $compte = comptePourLiaison('liaison-2@example.invalid');
    expect(fn () => LierCompteIdentite::executer($compte, false))->toThrow(DomainException::class);
    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});

it('refuse une identité provisoire sans insertion partielle', function () {
    $compte = comptePourLiaison('liaison-3@example.invalid');
    $provisoire = CompteReseauUnifie::creer($compte->userId, 'PER-002');
    expect(fn () => LierCompteIdentite::executer($provisoire, true))->toThrow(DomainException::class);
    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});

it('refuse une identité absente sans insertion partielle', function () {
    $compte = comptePourLiaison('liaison-4@example.invalid');
    $absent = CompteReseauUnifie::creer($compte->userId, 'PER-ABSENT');
    expect(fn () => LierCompteIdentite::executer($absent, true))->toThrow(DomainException::class);
    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});

it('ne remplace jamais une liaison déjà enregistrée', function () {
    $compte = comptePourLiaison('liaison-5@example.invalid');
    LierCompteIdentite::executer($compte, true);
    expect(fn () => LierCompteIdentite::executer($compte, true))->toThrow(QueryException::class);
    expect(DB::table('reseau_comptes_identites')->count())->toBe(1);
});

it('annule la liaison si la transaction externe echoue apres insertion', function () {
    $compte = comptePourLiaison('liaison-rollback@example.invalid');

    expect(function () use ($compte): void {
        DB::transaction(function () use ($compte): void {
            LierCompteIdentite::executer($compte, true);
            throw new DomainException('Annulation de la transaction externe');
        });
    })->toThrow(DomainException::class);

    expect(DB::table('reseau_comptes_identites')->where('user_id', $compte->userId)->exists())
        ->toBeFalse();
});

it('ne laisse aucune association lors du conflit de reference canonique', function () {
    $premier = comptePourLiaison('liaison-unique-a@example.invalid');
    $second = comptePourLiaison('liaison-unique-b@example.invalid');

    LierCompteIdentite::executer($premier, true);
    expect(fn () => LierCompteIdentite::executer($second, true))
        ->toThrow(QueryException::class);

    expect(DB::table('reseau_comptes_identites')->count())->toBe(1);
    expect(DB::table('reseau_comptes_identites')->where('user_id', $second->userId)->exists())
        ->toBeFalse();
});
