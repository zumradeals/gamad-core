<?php

use App\Support\Unification\QualificationLiaisonReseau;

it('refuse par défaut une liaison non qualifiée', function () {
    $resultat = QualificationLiaisonReseau::evaluer(false, null, null, false, false, false);
    expect($resultat['qualifie'])->toBeFalse();
    expect($resultat['blocages'])->toHaveCount(5);
});

it('refuse une référence différente de l acteur authentifié', function () {
    $resultat = QualificationLiaisonReseau::evaluer(true, 'PER-001', 'PER-002', true, true, true);
    expect($resultat['qualifie'])->toBeFalse();
    expect($resultat['blocages'])->toHaveCount(1);
});

it('refuse une session invalide malgré toutes les autres déclarations', function () {
    $resultat = QualificationLiaisonReseau::evaluer(false, 'PER-001', 'PER-001', true, true, true);
    expect($resultat['qualifie'])->toBeFalse();
});

it('refuse un registre hors transaction même avec une identité cohérente', function () {
    $resultat = QualificationLiaisonReseau::evaluer(true, 'PER-001', 'PER-001', true, false, true);
    expect($resultat['qualifie'])->toBeFalse();
});

it('ne considère le contrat qualifié que si toutes les préconditions sont attestées', function () {
    $resultat = QualificationLiaisonReseau::evaluer(true, 'PER-001', 'PER-001', true, true, true);
    expect($resultat)->toBe(['qualifie' => true, 'blocages' => []]);
});
