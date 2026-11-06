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
