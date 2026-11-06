<?php

use App\Support\Unification\ResoudreCompteReseauCore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function requeteResolutionCore(): Request
{
    $request = Request::create('/reseau/interne', 'GET');
    $request->setLaravelSession(app('session')->driver());

    return $request;
}

it('ne resout aucun compte sans session Core', function () {
    expect(ResoudreCompteReseauCore::resoudre(requeteResolutionCore()))->toBeNull();
});

it('ne confond pas utilisateur Laravel et session Core', function () {
    $utilisateur = \App\Models\User::factory()->create();
    $request = requeteResolutionCore();
    $request->setUserResolver(fn () => $utilisateur);

    expect(ResoudreCompteReseauCore::resoudre($request))->toBeNull();
});

it('ne cree jamais de liaison lors de la resolution', function () {
    ResoudreCompteReseauCore::resoudre(requeteResolutionCore());

    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});

it('refuse une identite sans journal de cycle', function () {
    expect(ResoudreCompteReseauCore::identiteActive('PER-ABSENT'))->toBeFalse();
});

it('accepte un cycle actif et refuse une revocation posterieure', function () {
    \Illuminate\Support\Facades\Schema::create('evenement_cycle_identite', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('identite_reference');
        $table->string('evenement_type');
        $table->string('etat_apres');
        $table->string('date_effet');
    });

    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-001',
        'evenement_type' => 'ACTIVATION',
        'etat_apres' => 'ACTIVE',
        'date_effet' => now()->subDays(2)->toIso8601String(),
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-001'))->toBeTrue();

    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-001',
        'evenement_type' => 'REVOCATION',
        'etat_apres' => 'REVOQUEE',
        'date_effet' => now()->subDay()->toIso8601String(),
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-001'))->toBeFalse();

    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-001',
        'evenement_type' => 'ACTIVATION',
        'etat_apres' => 'ACTIVE',
        'date_effet' => now()->addDay()->toIso8601String(),
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-001'))->toBeFalse();
});

it('compare les instants reels malgre des fuseaux differents', function () {
    \Illuminate\Support\Facades\Schema::create('evenement_cycle_identite', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('identite_reference');
        $table->string('evenement_type');
        $table->string('etat_apres');
        $table->string('date_effet');
    });

    $activation = now('UTC')->subDays(2)->setTimezone('Pacific/Honolulu')->toIso8601String();
    $revocation = now('UTC')->subDay()->setTimezone('Pacific/Kiritimati')->toIso8601String();
    DB::table('evenement_cycle_identite')->insert([
        ['identite_reference' => 'PER-FUSEAU', 'evenement_type' => 'ACTIVATION',
            'etat_apres' => 'ACTIVE', 'date_effet' => $activation],
        ['identite_reference' => 'PER-FUSEAU', 'evenement_type' => 'REVOCATION',
            'etat_apres' => 'REVOQUEE', 'date_effet' => $revocation],
    ]);

    expect(ResoudreCompteReseauCore::identiteActive('PER-FUSEAU'))->toBeFalse();
});

it('ignore les evenements relationnels et refuse les dates ambigues', function () {
    \Illuminate\Support\Facades\Schema::create('evenement_cycle_identite', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('identite_reference');
        $table->string('evenement_type');
        $table->string('etat_apres');
        $table->string('date_effet');
    });

    DB::table('evenement_cycle_identite')->insert([
        ['identite_reference' => 'PER-REL', 'evenement_type' => 'ACTIVATION',
            'etat_apres' => 'ACTIVE', 'date_effet' => now()->subDays(2)->toIso8601String()],
        ['identite_reference' => 'PER-REL', 'evenement_type' => 'RETRAIT_ORGANISATION',
            'etat_apres' => 'REVOQUEE', 'date_effet' => now()->subDay()->toIso8601String()],
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-REL'))->toBeTrue();

    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-REL', 'evenement_type' => 'REVOCATION',
        'etat_apres' => 'REVOQUEE', 'date_effet' => '2026-11-06 12:00:00',
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-REL'))->toBeFalse();
});

it('qualifie la session CTR-16 et refuse une resolution si le stockage Core est distinct', function () {
    $pdo = DB::connection()->getPdo();
    \Gamad\RegistreAcces\Magasin::creer($pdo);

    \Illuminate\Support\Facades\Schema::create('identite_inscrite', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->string('reference')->primary();
        $table->string('type');
        $table->string('regime');
        $table->boolean('provisoire');
    });
    \Illuminate\Support\Facades\Schema::create('evenement_cycle_identite', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('identite_reference');
        $table->string('evenement_type');
        $table->string('etat_apres');
        $table->string('date_effet');
    });

    DB::table('identite_inscrite')->insert([
        'reference' => 'PER-INTEGREE', 'type' => 'personne',
        'regime' => 'INSCRIT_AU_REGISTRE', 'provisoire' => 0,
    ]);
    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-INTEGREE', 'evenement_type' => 'ACTIVATION',
        'etat_apres' => 'ACTIVE', 'date_effet' => now()->subDay()->toIso8601String(),
    ]);
    $user = \App\Models\User::factory()->create();
    DB::table('reseau_comptes_identites')->insert([
        'user_id' => $user->id, 'identite_reference' => 'PER-INTEGREE',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $core = new \Gamad\RegistreAcces\Ctr16($pdo);
    $core->inscrireAuthentificateur('PER-INTEGREE', 'secret-de-qualification-2026');
    $session = $core->etablirSession('PER-INTEGREE', 'secret-de-qualification-2026');
    expect($session)->not->toBeNull();

    // Le Core peut partager le stockage, sans ajouter de seconde autorite.
    config()->set('database.connections.gamad_identity', config('database.connections.'.config('database.default')));
    DB::purge('gamad_identity');
    // En SQLite memoire, les connexions distinctes sont volontairement refusees.
    // Ce test qualifie la frontiere de stockage avant toute resolution.
    if (config('database.default') === 'sqlite'
        && config('database.connections.sqlite.database') === ':memory:') {
        expect(\App\Support\Unification\VerifierContexteLiaison::registreDansTransactionLocale())->toBeFalse();
        return;
    }

    $request = requeteResolutionCore();
    $request->session()->put('gamad_session', $session['session']);
    expect(ResoudreCompteReseauCore::resoudre($request)?->userId)->toBe((string) $user->id);

    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-INTEGREE', 'evenement_type' => 'REVOCATION',
        'etat_apres' => 'REVOQUEE', 'date_effet' => now()->toIso8601String(),
    ]);
    expect(ResoudreCompteReseauCore::resoudre($request))->toBeNull();
});

it('accepte les dates civiles CTR-01 et refuse une revocation future', function () {
    \Illuminate\Support\Facades\Schema::create('evenement_cycle_identite', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        $table->string('identite_reference');
        $table->string('evenement_type');
        $table->string('etat_apres');
        $table->string('date_effet');
    });
    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-CIVILE', 'evenement_type' => 'ACTIVATION',
        'etat_apres' => 'ACTIVE', 'date_effet' => now('UTC')->subDay()->format('Y-m-d'),
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-CIVILE'))->toBeTrue();

    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-CIVILE', 'evenement_type' => 'REVOCATION',
        'etat_apres' => 'REVOQUEE', 'date_effet' => now('UTC')->addDay()->format('Y-m-d'),
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-CIVILE'))->toBeTrue();

    DB::table('evenement_cycle_identite')->insert([
        'identite_reference' => 'PER-CIVILE', 'evenement_type' => 'REVOCATION',
        'etat_apres' => 'REVOQUEE', 'date_effet' => now('UTC')->format('Y-m-d'),
    ]);
    expect(ResoudreCompteReseauCore::identiteActive('PER-CIVILE'))->toBeFalse();
});
