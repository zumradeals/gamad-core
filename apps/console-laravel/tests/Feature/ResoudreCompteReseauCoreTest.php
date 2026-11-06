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
