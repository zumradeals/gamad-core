<?php

use Illuminate\Support\Facades\Artisan;

function inventaireRoutesTemporaire(array $routes): string
{
    $path = tempnam(sys_get_temp_dir(), 'gamad-routes-');
    file_put_contents($path, json_encode($routes, JSON_THROW_ON_ERROR));

    return $path;
}

it('bloque la collision entre les accueils publics et console', function () {
    $reseau = inventaireRoutesTemporaire([['method' => 'GET|HEAD', 'uri' => '/']]);
    $core = inventaireRoutesTemporaire([['method' => 'GET|HEAD', 'uri' => '/']]);

    try {
        expect(Artisan::call('gamad:unification:routes', ['reseau' => $reseau, 'core' => $core]))->toBe(1);
        expect(Artisan::output())->toContain('GET ');
    } finally {
        unlink($reseau);
        unlink($core);
    }
});

it('autorise des routes sans collision exacte', function () {
    $reseau = inventaireRoutesTemporaire([['method' => 'GET|HEAD', 'uri' => 'fil']]);
    $core = inventaireRoutesTemporaire([['method' => 'GET|HEAD', 'uri' => 'core']]);

    try {
        expect(Artisan::call('gamad:unification:routes', ['reseau' => $reseau, 'core' => $core]))->toBe(0);
    } finally {
        unlink($reseau);
        unlink($core);
    }
});

it('refuse un inventaire JSON absent', function () {
    $core = inventaireRoutesTemporaire([]);

    try {
        expect(Artisan::call('gamad:unification:routes', [
            'reseau' => '/chemin/inexistant/gamad-routes.json',
            'core' => $core,
        ]))->toBe(1);
    } finally {
        unlink($core);
    }
});
