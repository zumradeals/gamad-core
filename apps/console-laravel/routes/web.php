<?php

declare(strict_types=1);

use App\Http\Controllers\AccesConsoleController;
use App\Http\Controllers\AccesController;
use App\Http\Controllers\ContinuiteConsoleController;
use App\Http\Controllers\Ctr01Controller;
use App\Http\Controllers\Ctr02Controller;
use App\Http\Controllers\Ctr03Controller;
use App\Http\Controllers\Ctr04Controller;
use App\Http\Controllers\IdentiteConsoleController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\ProduitConsoleController;
use App\Http\Controllers\SatelliteConsoleController;
use Illuminate\Support\Facades\Route;

/*
 * Console authentifiée : les lectures restent servies par les contrats du
 * Core. La seule écriture web est l'inscription gouvernée d'une identité ;
 * elle partage le même cas d'usage que l'API v1.
 */
Route::middleware('gamad.session')->group(function (): void {
    Route::get('/', [Ctr04Controller::class, 'tableauDeBord'])->name('console.accueil');
    Route::get('/normes/{reference}', [Ctr04Controller::class, 'resoudreNorme'])->name('ctr04.resoudre-norme');
    Route::get('/sources/{reference}', [Ctr04Controller::class, 'resoudreSource'])->name('ctr04.resoudre-source');
    Route::get('/capacites/{reference}', [Ctr04Controller::class, 'resoudreCapacite'])->name('ctr04.resoudre-capacite');
    Route::get('/identites', [IdentiteConsoleController::class, 'index'])
        ->name('console.identites.index');
    Route::get('/identites/nouvelle', [IdentiteConsoleController::class, 'create'])
        ->name('console.identites.create');
    Route::post('/identites', [IdentiteConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.identites.store');
    Route::get('/identites/{reference}', [IdentiteConsoleController::class, 'show'])
        ->name('console.identites.show');
    // CAP-CORE-022 — fédération. Les écritures passent par le même cas d'usage
    // gouverné que l'API v1 ; la console n'ouvre aucun chemin parallèle.
    Route::get('/satellites', [SatelliteConsoleController::class, 'index'])
        ->name('console.satellites.index');
    Route::get('/satellites/{produit}', [SatelliteConsoleController::class, 'show'])
        ->name('console.satellites.show');
    Route::post('/satellites/{produit}/acces', [SatelliteConsoleController::class, 'ouvrir'])
        ->middleware('throttle:20,1')
        ->name('console.satellites.ouvrir');
    Route::post('/satellites/{produit}/revocation', [SatelliteConsoleController::class, 'revoquer'])
        ->middleware('throttle:20,1')
        ->name('console.satellites.revoquer');
    // Raccordement du satellite lui-même. Le secret est engendré par le Core
    // et remis une seule fois ; la cadence est volontairement basse.
    Route::post('/satellites/{produit}/identifiants', [SatelliteConsoleController::class, 'delivrer'])
        ->middleware('throttle:5,1')
        ->name('console.satellites.delivrer');
    Route::post('/satellites/{produit}/identifiants/retrait', [SatelliteConsoleController::class, 'retirer'])
        ->middleware('throttle:10,1')
        ->name('console.satellites.retirer');

    // CAP-CORE-011 — registre des produits. Toute écriture passe par
    // `AccesProduits`, le même cas d'usage gouverné que l'API v1.
    Route::get('/produits', [ProduitConsoleController::class, 'index'])
        ->name('console.produits.index');
    Route::get('/produits/nouveau', [ProduitConsoleController::class, 'create'])
        ->name('console.produits.create');
    Route::post('/produits', [ProduitConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.produits.store');
    Route::get('/produits/{reference}', [ProduitConsoleController::class, 'show'])
        ->name('console.produits.show');
    Route::post('/produits/{reference}/modification', [ProduitConsoleController::class, 'modifier'])
        ->middleware('throttle:20,1')
        ->name('console.produits.modifier');
    Route::post('/produits/{reference}/activation', [ProduitConsoleController::class, 'activer'])
        ->middleware('throttle:20,1')
        ->name('console.produits.activer');
    Route::post('/produits/{reference}/suspension', [ProduitConsoleController::class, 'suspendre'])
        ->middleware('throttle:20,1')
        ->name('console.produits.suspendre');
    Route::post('/produits/{reference}/retrait', [ProduitConsoleController::class, 'retirer'])
        ->middleware('throttle:20,1')
        ->name('console.produits.retirer');
    Route::post('/produits/{reference}/environnements', [ProduitConsoleController::class, 'declarerEnvironnement'])
        ->middleware('throttle:20,1')
        ->name('console.produits.environnements.declarer');
    Route::post(
        '/produits/{reference}/environnements/{id}/fermeture',
        [ProduitConsoleController::class, 'fermerEnvironnement'],
    )->whereNumber('id')->middleware('throttle:20,1')->name('console.produits.environnements.fermer');

    // CAP-CORE-005 — moyens d'accès personnels. Le sujet vient de la session ;
    // on ne gère jamais l'accès d'autrui.
    Route::get('/mon-acces', [AccesConsoleController::class, 'index'])
        ->name('console.acces.index');
    Route::post('/mon-acces/codes-de-secours', [AccesConsoleController::class, 'engendrerCodes'])
        ->middleware('throttle:5,1')
        ->name('console.acces.codes');
    Route::post('/mon-acces/passkey', [AccesConsoleController::class, 'autoriserPasskey'])
        ->middleware('throttle:5,1')
        ->name('console.acces.passkey');
    Route::post('/mon-acces/retrait', [AccesConsoleController::class, 'retirer'])
        ->middleware('throttle:10,1')
        ->name('console.acces.retirer');

    // CAP-CORE-019 — continuité. La console ne lance rien : elle écrit des
    // réglages et dépose des demandes qu'une unité systemd sert.
    Route::get('/continuite', [ContinuiteConsoleController::class, 'index'])
        ->name('console.continuite.index');
    Route::post('/continuite/destination', [ContinuiteConsoleController::class, 'configurer'])
        ->middleware('throttle:10,1')
        ->name('console.continuite.configurer');
    Route::post('/continuite/{operation}', [ContinuiteConsoleController::class, 'declencher'])
        ->whereIn('operation', ['sauvegarde', 'exercice'])
        ->middleware('throttle:10,1')
        ->name('console.continuite.declencher');

    Route::get('/denominations', [Ctr01Controller::class, 'resoudreDenominations'])->name('ctr01.denominations');
    Route::get('/mandats/{fonction}', [Ctr02Controller::class, 'resoudreMandat'])->name('ctr02.resoudre-mandat');
    Route::get('/actes/{reference}/verification', [Ctr02Controller::class, 'verifierActe'])->name('ctr02.verifier-acte');
    Route::get('/interdits', [Ctr03Controller::class, 'interdits'])->name('ctr03.interdits');
    Route::get('/autorisation', [Ctr03Controller::class, 'autoriser'])->name('ctr03.autoriser');
    Route::get('/vacances', [Ctr02Controller::class, 'resoudreVacance'])->name('ctr02.resoudre-vacance');
    Route::get('/index/diagnostic', [Ctr04Controller::class, 'diagnostiquerIndex'])->name('ctr04.diagnostiquer-index');
});

// Accès : la seule porte ouverte sans session (CAP-CORE-005).
Route::get('/connexion', [AccesController::class, 'formulaire'])->name('acces.formulaire');
Route::post('/connexion', [AccesController::class, 'connecter'])
    ->middleware('throttle:10,1')
    ->name('acces.connecter');
Route::post('/deconnexion', [AccesController::class, 'deconnecter'])->name('acces.deconnecter');

Route::middleware('gamad.https')->group(function (): void {
    Route::get('/passkeys/enrolement', [PasskeyController::class, 'formulaireEnrolement'])
        ->name('passkeys.enrolement');
    Route::post('/passkeys/enrolement/options', [PasskeyController::class, 'optionsEnrolement'])
        ->middleware('throttle:5,1')
        ->name('passkeys.enrolement.options');
    Route::post('/passkeys/enrolement', [PasskeyController::class, 'enroler'])
        ->middleware('throttle:5,1')
        ->name('passkeys.enroler');
    Route::post('/passkeys/authentification/options', [PasskeyController::class, 'optionsAuthentification'])
        ->middleware('throttle:10,1')
        ->name('passkeys.authentification.options');
    Route::post('/passkeys/authentification', [PasskeyController::class, 'authentifier'])
        ->middleware('throttle:10,1')
        ->name('passkeys.authentifier');
});
