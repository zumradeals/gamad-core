<?php

use App\Support\Unification\VerifierContexteLiaison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('refuse une liaison sans session CTR-16 même si le client fournit une référence', function () {
    $request = Request::create('/liaison-interne', 'POST');
    $request->setLaravelSession(app('session')->driver());

    $resultat = VerifierContexteLiaison::verifier($request, 'PER-001');

    expect($resultat['qualifie'])->toBeFalse();
    expect($resultat['blocages'])->toContain('Session Core CTR-16 non vérifiée');
    expect($resultat['blocages'])->toContain('Possession du compte Réseau non démontrée');
    expect($resultat['blocages'])->toContain('Autorisation de liaison non vérifiée');
});

it('refuse une transaction répartie sur deux connexions PDO distinctes', function () {
    // Le registre est physiquement séparé par défaut en environnement de test.
    // Une connexion absente ou inaccessible doit également produire un refus.
    expect(VerifierContexteLiaison::registreDansTransactionLocale())->toBeFalse();
});

it('ne crée aucune liaison en vérifiant seulement le contexte', function () {
    $request = Request::create('/liaison-interne', 'POST');
    $request->setLaravelSession(app('session')->driver());

    VerifierContexteLiaison::verifier($request, 'PER-001');

    expect(DB::table('reseau_comptes_identites')->count())->toBe(0);
});
