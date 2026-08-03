<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AutorisationController;
use App\Http\Controllers\Api\V1\ContratController;
use App\Http\Controllers\Api\V1\FederationController;
use App\Http\Controllers\Api\V1\FondationController;
use App\Http\Controllers\Api\V1\IdentiteController;
use App\Http\Controllers\Api\V1\OrganisationController;
use App\Http\Controllers\Api\V1\PasskeySessionController;
use App\Http\Controllers\Api\V1\PolitiqueController;
use App\Http\Controllers\Api\V1\ProduitController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SourceController;
use App\Http\Controllers\Api\V1\VocabulaireController;
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

        // CAP-CORE-009 — registre des contrats. `{version}` suit toujours
        // X.Y.Z. `compatibilite` et `conformite` exigent `?version=`.
        Route::get('/contrats', [ContratController::class, 'index']);
        Route::post('/contrats', [ContratController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/contrats/{reference}', [ContratController::class, 'show']);
        Route::get('/contrats/{reference}/versions', [ContratController::class, 'versions']);
        Route::get('/contrats/{reference}/historique', [ContratController::class, 'historique']);
        Route::get('/contrats/{reference}/compatibilite', [ContratController::class, 'compatibilite']);
        Route::get('/contrats/{reference}/conformite', [ContratController::class, 'conformite']);
        Route::get('/contrats/{reference}/consommateurs', [ContratController::class, 'consommateurs']);
        Route::post('/contrats/{reference}/versions', [ContratController::class, 'creerVersion'])
            ->middleware('throttle:20,1');
        Route::get('/contrats/{reference}/versions/{version}', [ContratController::class, 'version'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+');
        Route::post('/contrats/{reference}/versions/{version}/parties', [ContratController::class, 'declarerPartie'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/operations', [ContratController::class, 'declarerOperation'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/schemas', [ContratController::class, 'declarerSchema'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/erreurs', [ContratController::class, 'declarerErreur'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/soumission', [ContratController::class, 'soumettre'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/analyse', [ContratController::class, 'analyser'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/activation', [ContratController::class, 'activer'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/depreciation', [ContratController::class, 'deprecier'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/suspension', [ContratController::class, 'suspendre'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/retrait', [ContratController::class, 'retirer'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/contrats/{reference}/versions/{version}/conformite', [ContratController::class, 'enregistrerConformite'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');

        // CAP-CORE-010 — registre du vocabulaire canonique. `{version}` suit
        // toujours X.Y.Z. `compatibilite` et `conformite` exigent `?version=`.
        Route::get('/vocabulaires', [VocabulaireController::class, 'index']);
        Route::post('/vocabulaires', [VocabulaireController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/vocabulaires/{reference}', [VocabulaireController::class, 'show']);
        Route::get('/vocabulaires/{reference}/versions', [VocabulaireController::class, 'versions']);
        Route::get('/vocabulaires/{reference}/version-active', [VocabulaireController::class, 'versionActive']);
        Route::get('/vocabulaires/{reference}/termes', [VocabulaireController::class, 'termes']);
        Route::get('/vocabulaires/{reference}/compatibilite', [VocabulaireController::class, 'compatibilite']);
        Route::get('/vocabulaires/{reference}/conformite', [VocabulaireController::class, 'conformite']);
        Route::post('/vocabulaires/{reference}/versions', [VocabulaireController::class, 'creerVersion'])
            ->middleware('throttle:20,1');
        Route::get('/vocabulaires/{reference}/versions/{version}', [VocabulaireController::class, 'version'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+');
        Route::post('/vocabulaires/{reference}/versions/{version}/termes', [VocabulaireController::class, 'ajouterTerme'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/vocabulaires/{reference}/versions/{version}/soumission', [VocabulaireController::class, 'soumettre'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/vocabulaires/{reference}/versions/{version}/analyse', [VocabulaireController::class, 'analyser'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/vocabulaires/{reference}/versions/{version}/activation', [VocabulaireController::class, 'activer'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/vocabulaires/{reference}/versions/{version}/projections', [VocabulaireController::class, 'projections'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/vocabulaires/{reference}/versions/{version}/conformite', [VocabulaireController::class, 'enregistrerConformite'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/vocabulaires/{reference}/versions/{version}/depreciation', [VocabulaireController::class, 'deprecierVersion'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');
        Route::post('/vocabulaires/{reference}/versions/{version}/retrait', [VocabulaireController::class, 'retirerVersion'])
            ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1');

        Route::get('/termes/{reference}', [VocabulaireController::class, 'termeShow']);
        Route::get('/termes/{reference}/usages', [VocabulaireController::class, 'termeUsages']);
        Route::get('/termes/{reference}/mappings', [VocabulaireController::class, 'termeMappings']);
        Route::post('/termes/{reference}/evolution', [VocabulaireController::class, 'evoluerTerme'])
            ->middleware('throttle:20,1');
        Route::post('/termes/{reference}/libelles', [VocabulaireController::class, 'ajouterLibelle'])
            ->middleware('throttle:20,1');
        Route::post('/termes/{reference}/alias', [VocabulaireController::class, 'ajouterAlias'])
            ->middleware('throttle:20,1');
        Route::post('/termes/{reference}/relations', [VocabulaireController::class, 'declarerRelation'])
            ->middleware('throttle:20,1');
        Route::post('/termes/{reference}/mappings', [VocabulaireController::class, 'declarerMappingExterne'])
            ->middleware('throttle:20,1');
        Route::post('/termes/{reference}/usages', [VocabulaireController::class, 'declarerUsage'])
            ->middleware('throttle:20,1');
        Route::post('/termes/{reference}/depreciation', [VocabulaireController::class, 'deprecierTerme'])
            ->middleware('throttle:20,1');
        Route::post('/termes/{reference}/retrait', [VocabulaireController::class, 'retirerTerme'])
            ->middleware('throttle:20,1');

        Route::get('/mandats/{fonction}', [Ctr02Controller::class, 'resoudreMandat']);
        Route::post('/autorisation/decisions', [AutorisationController::class, 'store'])
            ->middleware('throttle:60,1');

        // CAP-CORE-002 — registre des organisations.
        Route::get('/organisations', [OrganisationController::class, 'index']);
        Route::post('/organisations', [OrganisationController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/organisations/{reference}', [OrganisationController::class, 'show']);
        Route::patch('/organisations/{reference}', [OrganisationController::class, 'update'])
            ->middleware('throttle:20,1');
        Route::get('/organisations/{reference}/structure', [OrganisationController::class, 'structure']);
        Route::get('/organisations/{reference}/unites', [OrganisationController::class, 'unites']);
        Route::get('/organisations/{reference}/relations', [OrganisationController::class, 'relations']);
        Route::get('/organisations/{reference}/affiliations', [OrganisationController::class, 'affiliations']);
        Route::get('/organisations/{reference}/fonctions', [OrganisationController::class, 'fonctions']);
        Route::get('/identites/{reference}/organisations', [OrganisationController::class, 'affiliationsIdentite']);
        Route::post('/organisations/{reference}/appartenance/verification', [OrganisationController::class, 'verifierAppartenance'])
            ->middleware('throttle:60,1');
        Route::post('/organisations/{reference}/representation/verification', [OrganisationController::class, 'verifierRepresentation'])
            ->middleware('throttle:60,1');
        Route::post('/organisations/{reference}/activation', [OrganisationController::class, 'activer'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/suspension', [OrganisationController::class, 'suspendre'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/dissolution', [OrganisationController::class, 'dissoudre'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/retrait', [OrganisationController::class, 'retirer'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/identifiants', [OrganisationController::class, 'declarerIdentifiant'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/unites', [OrganisationController::class, 'creerUnite'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/relations', [OrganisationController::class, 'declarerRelation'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/affiliations', [OrganisationController::class, 'proposerAffiliation'])
            ->middleware('throttle:20,1');
        Route::post('/organisations/{reference}/fonctions', [OrganisationController::class, 'creerFonction'])
            ->middleware('throttle:20,1');
        Route::post(
            '/organisations/{reference}/affiliations/{affiliation}/activation',
            [OrganisationController::class, 'activerAffiliation'],
        )->middleware('throttle:20,1');
        Route::post(
            '/organisations/{reference}/affiliations/{affiliation}/suspension',
            [OrganisationController::class, 'suspendreAffiliation'],
        )->middleware('throttle:20,1');
        Route::post(
            '/organisations/{reference}/affiliations/{affiliation}/fermeture',
            [OrganisationController::class, 'fermerAffiliation'],
        )->middleware('throttle:20,1');
    });
});
