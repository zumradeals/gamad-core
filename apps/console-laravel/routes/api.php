<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AutorisationController;
use App\Http\Controllers\Api\V1\FederationController;
use App\Http\Controllers\Api\V1\FondationController;
use App\Http\Controllers\Api\V1\IdentiteController;
use App\Http\Controllers\Api\V1\PasskeySessionController;
use App\Http\Controllers\Api\V1\PolitiqueController;
use App\Http\Controllers\Api\V1\ProduitController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SourceController;
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

        // CAP-CORE-006 — registre des sources. Découplé du registre des
        // normes : `CTR-15` ne lit plus `norme`/`version_norme`/`statut`.
        Route::get('/sources', [SourceController::class, 'index']);
        Route::post('/sources', [SourceController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/sources/{reference}', [SourceController::class, 'show']);
        Route::patch('/sources/{reference}', [SourceController::class, 'update'])
            ->middleware('throttle:20,1');
        Route::get('/sources/{reference}/lignee', [SourceController::class, 'lignee']);
        Route::get('/sources/{reference}/finalites', [SourceController::class, 'finalites']);
        Route::get('/sources/{reference}/verification', [SourceController::class, 'verification']);
        Route::post('/sources/{reference}/utilisabilite', [SourceController::class, 'utilisabilite'])
            ->middleware('throttle:60,1');
        Route::post('/sources/{reference}/activation', [SourceController::class, 'activer'])
            ->middleware('throttle:20,1');
        Route::post('/sources/{reference}/suspension', [SourceController::class, 'suspendre'])
            ->middleware('throttle:20,1');
        Route::post('/sources/{reference}/retrait', [SourceController::class, 'retirer'])
            ->middleware('throttle:20,1');
        Route::post('/sources/{reference}/finalites', [SourceController::class, 'declarerFinalite'])
            ->middleware('throttle:20,1');
        Route::post(
            '/sources/{reference}/finalites/{id}/fermeture',
            [SourceController::class, 'fermerFinalite'],
        )->whereNumber('id')->middleware('throttle:20,1');
        Route::post('/sources/{reference}/verifications', [SourceController::class, 'enregistrerVerification'])
            ->middleware('throttle:20,1');
        Route::post('/sources/{reference}/lignee', [SourceController::class, 'declarerLignee'])
            ->middleware('throttle:20,1');

        // CAP-CORE-007 — registre des politiques. CTR-03 (CAP-CORE-004) lit ce
        // magasin pour décider ; il ne lit plus jamais politique/regle depuis
        // l'index. `{version}` suit toujours X.Y.Z.
        Route::get('/politiques', [PolitiqueController::class, 'index']);
        Route::post('/politiques', [PolitiqueController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/politiques/{reference}', [PolitiqueController::class, 'show']);
        Route::get('/politiques/{reference}/versions', [PolitiqueController::class, 'versions']);
        Route::get('/politiques/{reference}/historique', [PolitiqueController::class, 'historique']);
        Route::post('/politiques/{reference}/versions', [PolitiqueController::class, 'creerVersion'])
            ->middleware('throttle:20,1');
        Route::get('/politiques/{reference}/versions/{version}', [PolitiqueController::class, 'version'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+');
        Route::post('/politiques/{reference}/versions/{version}/regles', [PolitiqueController::class, 'ajouterRegle'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::patch('/politiques/{reference}/versions/{version}/regles/{id}', [PolitiqueController::class, 'modifierRegle'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->whereNumber('id')->middleware('throttle:20,1');
        Route::post('/politiques/{reference}/versions/{version}/soumission', [PolitiqueController::class, 'soumettre'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/politiques/{reference}/versions/{version}/simulation', [PolitiqueController::class, 'simuler'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/politiques/{reference}/versions/{version}/activation', [PolitiqueController::class, 'activer'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/politiques/{reference}/versions/{version}/suspension', [PolitiqueController::class, 'suspendre'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/politiques/{reference}/retrait', [PolitiqueController::class, 'retirer'])
            ->middleware('throttle:20,1');

        Route::get('/mandats/{fonction}', [Ctr02Controller::class, 'resoudreMandat']);
        Route::post('/autorisation/decisions', [AutorisationController::class, 'store'])
            ->middleware('throttle:60,1');
    });
});
