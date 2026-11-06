<?php

use App\Support\Unification\CompteReseauUnifie;

it('représente un compte authentifiable et une identité canonique distincts', function () {
    $compte = CompteReseauUnifie::creer('42', 'PER-000042');
    expect($compte->references())->toBe([
        'user_id' => '42',
        'identite_reference' => 'PER-000042',
    ]);
});

it('rejette un UUID historique comme clé du nouveau modèle', function () {
    expect(fn () => CompteReseauUnifie::creer(
        '550e8400-e29b-41d4-a716-446655440000', 'PER-000042'
    ))->toThrow(DomainException::class);
});

it('rejette les références canoniques absentes et les identifiants non positifs', function () {
    expect(fn () => CompteReseauUnifie::creer('0', 'PER-000042'))->toThrow(DomainException::class);
    expect(fn () => CompteReseauUnifie::creer('42', ' '))->toThrow(DomainException::class);
    expect(fn () => CompteReseauUnifie::creer('42', ' PER-000042'))->toThrow(DomainException::class);
});

it('ne déduit aucun droit administratif de la possession du compte', function () {
    $compte = CompteReseauUnifie::creer('42', 'PER-000042');
    expect(array_keys($compte->references()))->toBe(['user_id', 'identite_reference']);
});
