<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AutorisationController;
use App\Http\Controllers\Api\V1\FederationController;
use App\Http\Controllers\Api\V1\FondationController;
use App\Http\Controllers\Api\V1\IdentiteController;
use App\Http\Controllers\Api\V1\PasskeySessionController;
use App\Http\Controllers\Api\V1\ProduitController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Ctr01Controller;
use App\Http\Controllers\Ctr02Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('gamad.https')->group(function (): void {
    Route::get('/health/live', [FondationController::class, 'live']);
    Route::get('/health/ready', [FondationController::class, 'ready']);

    Route::post('/sessions', [SessionController::class, 'store'])
        ->middleware('throttle:5,1');
    Route::post('/sessions/passkey/options', [PasskeySessionController::class, 'options'])
        ->middleware('throttle:10,1');
    Route::post('/sessions/passkey', [PasskeySessionController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::middleware('gamad.api')->group(function (): void {
        Route::delete('/sessions/current', [SessionController::class, 'destroy']);

        Route::get('/identites', [Ctr01Controller::class, 'resoudreInventaire']);
        Route::get('/identites/{reference}', [Ctr01Controller::class, 'resoudreIdentite']);
        Route::get('/identites/{reference}/regime', [Ctr01Controller::class, 'resoudreRegime']);
        Route::get('/identites/{reference}/assurance', [Ctr01Controller::class, 'resoudreAssurance']);
        Route::post('/identites', [IdentiteController::class, 'store'])
            ->middleware('throttle:20,1');

        // CAP-CORE-022 — fédération. `{produit}` est l'audience : elle borne
        // l'ouverture, la vérification et la révocation.
        Route::get('/produits', [FederationController::class, 'index']);
        Route::post('/produits/{produit}/ouverture', [FederationController::class, 'ouvrir'])
            ->middleware('throttle:20,1');
        Route::post('/produits/{produit}/verification', [FederationController::class, 'verifier'])
            ->middleware('throttle:60,1');
        Route::post('/produits/{produit}/revocation', [FederationController::class, 'revoquer'])
            ->middleware('throttle:20,1');

        // CAP-CORE-011 — registre des produits. `{reference}` est la fiche
        // opérationnelle gouvernée, distincte de l'audience fédérée ci-dessus
        // même si les deux partagent la même valeur pour un satellite donné.
        Route::post('/produits', [ProduitController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/produits/{reference}', [ProduitController::class, 'show']);
        Route::patch('/produits/{reference}', [ProduitController::class, 'update'])
            ->middleware('throttle:20,1');
        Route::post('/produits/{reference}/activation', [ProduitController::class, 'activer'])
            ->middleware('throttle:20,1');
        Route::post('/produits/{reference}/suspension', [ProduitController::class, 'suspendre'])
            ->middleware('throttle:20,1');
        Route::post('/produits/{reference}/retrait', [ProduitController::class, 'retirer'])
            ->middleware('throttle:20,1');
        Route::get('/produits/{reference}/environnements', [ProduitController::class, 'environnements']);
        Route::post('/produits/{reference}/environnements', [ProduitController::class, 'declarerEnvironnement'])
            ->middleware('throttle:20,1');
        Route::post(
            '/produits/{reference}/environnements/{id}/fermeture',
            [ProduitController::class, 'fermerEnvironnement'],
        )->whereNumber('id')->middleware('throttle:20,1');

        Route::get('/mandats/{fonction}', [Ctr02Controller::class, 'resoudreMandat']);
        Route::post('/autorisation/decisions', [AutorisationController::class, 'store'])
            ->middleware('throttle:60,1');
    });
});
