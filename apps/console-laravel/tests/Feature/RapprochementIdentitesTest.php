<?php

use App\Support\Unification\RapprochementIdentites;

it('préserve les UUID historiques et les références canoniques', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    expect(RapprochementIdentites::exigerSansConflit([
        ['reseau_user_id' => strtoupper($uuid), 'core_reference' => 'PER-000001'],
    ]))->toBe([['reseau_user_id' => $uuid, 'core_reference' => 'PER-000001']]);
});

it('refuse plusieurs références Core pour un même compte Réseau', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    expect(fn () => RapprochementIdentites::exigerSansConflit([
        ['reseau_user_id' => $uuid, 'core_reference' => 'PER-000001'],
        ['reseau_user_id' => $uuid, 'core_reference' => 'PER-000002'],
    ]))->toThrow(InvalidArgumentException::class, 'UUID Réseau déjà rapproché');
});

it('refuse de fusionner deux comptes Réseau sous une seule identité Core', function () {
    expect(fn () => RapprochementIdentites::exigerSansConflit([
        ['reseau_user_id' => '550e8400-e29b-41d4-a716-446655440000', 'core_reference' => 'PER-000001'],
        ['reseau_user_id' => '550e8400-e29b-41d4-a716-446655440001', 'core_reference' => 'PER-000001'],
    ]))->toThrow(InvalidArgumentException::class, 'référence Core déjà rapprochée');
});

it('rejette un identifiant non UUID ou une référence vide', function () {
    expect(RapprochementIdentites::verifier([
        ['reseau_user_id' => '42', 'core_reference' => 'PER-000001'],
        ['reseau_user_id' => '550e8400-e29b-41d4-a716-446655440000', 'core_reference' => ''],
    ])['conflits'])->toHaveCount(2);
});

it('ne considère pas une égalité de courriel comme une preuve de rapprochement', function () {
    expect(RapprochementIdentites::verifier([]))->toBe(['valides' => [], 'conflits' => []]);
});
