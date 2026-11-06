<?php

use App\Support\Unification\CompteReseauUnifie;
use App\Support\Unification\ExecuterLiaisonReseau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function requeteLiaisonInterne(): Request
{
    $request = Request::create('/liaison-interne', 'POST');
    $request->setLaravelSession(app('session')->driver());

    return $request;
}

it('refuse le parcours sans session Core et ne persiste aucune liaison', function () {
    $compte = CompteReseauUnifie::creer('1', 'PER-001');

    expect(fn () => ExecuterLiaisonReseau::executer(requeteLiaisonInterne(), $compte))
        ->toThrow(DomainException::class);

    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});

it('refuse un compte ciblé différent du compte authentifié', function () {
    $utilisateur = \App\Models\User::factory()->create();
    $request = requeteLiaisonInterne();
    $request->setUserResolver(fn () => $utilisateur);
    $compte = CompteReseauUnifie::creer((string) ($utilisateur->id + 1), 'PER-001');

    expect(fn () => ExecuterLiaisonReseau::executer($request, $compte))
        ->toThrow(DomainException::class);

    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});

it('ne confond pas authentification Laravel et autorisation Core', function () {
    $utilisateur = \App\Models\User::factory()->create();
    $request = requeteLiaisonInterne();
    $request->setUserResolver(fn () => $utilisateur);
    $compte = CompteReseauUnifie::creer((string) $utilisateur->id, 'PER-001');

    expect(fn () => ExecuterLiaisonReseau::executer($request, $compte))
        ->toThrow(DomainException::class);

    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});
