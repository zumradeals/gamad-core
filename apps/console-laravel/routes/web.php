<?php

declare(strict_types=1);

use App\Http\Controllers\Ctr04Controller;
use Illuminate\Support\Facades\Route;

/*
 * Livraison HTTP du contrat CTR-04 (CAP-CORE-007) — GET uniquement.
 * Aucune route d'écriture n'est déclarée ici : INV-4 est tenu par
 * l'absence structurelle de verbe POST/PUT/PATCH/DELETE vers ce contrôleur
 * (CONCEPTION-LIVRAISON-LARAVEL-CTR-04-0001, Article 6).
 */

Route::get('/', [Ctr04Controller::class, 'tableauDeBord'])->name('ctr04.tableau-de-bord');
Route::get('/normes/{reference}', [Ctr04Controller::class, 'resoudreNorme'])->name('ctr04.resoudre-norme');
Route::get('/integrite/{reference?}', [Ctr04Controller::class, 'verifierIntegrite'])->name('ctr04.verifier-integrite');
Route::get('/index', [Ctr04Controller::class, 'resoudreIndex'])->name('ctr04.resoudre-index');
