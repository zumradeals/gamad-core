<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AbonnementController;
use App\Http\Controllers\Api\V1\AutorisationController;
use App\Http\Controllers\Api\V1\ContratController;
use App\Http\Controllers\Api\V1\EvenementController;
use App\Http\Controllers\Api\V1\FederationController;
use App\Http\Controllers\Api\V1\FondationController;
use App\Http\Controllers\Api\V1\FournisseurSecretController;
use App\Http\Controllers\Api\V1\IdentiteController;
use App\Http\Controllers\Api\V1\LettreMorteController;
use App\Http\Controllers\Api\V1\MatchingController;
use App\Http\Controllers\Api\V1\OrganisationController;
use App\Http\Controllers\Api\V1\PasskeySessionController;
use App\Http\Controllers\Api\V1\PolitiqueController;
use App\Http\Controllers\Api\V1\PreuveController;
use App\Http\Controllers\Api\V1\ProduitController;
use App\Http\Controllers\Api\V1\RealmController;
use App\Http\Controllers\Api\V1\RejeuController;
use App\Http\Controllers\Api\V1\RotationSecretController;
use App\Http\Controllers\Api\V1\SecretController;
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

        // CAP-CORE-012 — registre des realms.
        Route::get('/realms', [RealmController::class, 'index']);
        Route::post('/realms', [RealmController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/realms/{reference}', [RealmController::class, 'show']);
        Route::patch('/realms/{reference}', [RealmController::class, 'update'])
            ->middleware('throttle:20,1');
        Route::get('/realms/{reference}/historique', [RealmController::class, 'historique']);
        Route::get('/realms/{reference}/relations', [RealmController::class, 'relations']);
        Route::get('/realms/{reference}/parents', [RealmController::class, 'parents']);
        Route::get('/realms/{reference}/enfants', [RealmController::class, 'enfants']);
        Route::get('/realms/{reference}/perimetres', [RealmController::class, 'perimetres']);
        Route::get('/realms/{reference}/identifiants-externes', [RealmController::class, 'identifiantsExternes']);
        Route::get('/realms/{reference}/organisations', [RealmController::class, 'organisations']);
        Route::get('/realms/{reference}/produits', [RealmController::class, 'produits']);
        Route::get('/realms/{reference}/contrats', [RealmController::class, 'contrats']);
        Route::get('/realms/{reference}/franchissements', [RealmController::class, 'franchissements']);
        Route::get('/realms/{reference}/verification', [RealmController::class, 'verification']);
        Route::post('/realms/{reference}/portee', [RealmController::class, 'verifierPortee'])
            ->middleware('throttle:60,1');
        Route::post('/realms/{reference}/activation', [RealmController::class, 'activer'])
            ->middleware('throttle:20,1');
        Route::post('/realms/{reference}/suspension', [RealmController::class, 'suspendre'])
            ->middleware('throttle:20,1');
        Route::post('/realms/{reference}/fermeture', [RealmController::class, 'fermer'])
            ->middleware('throttle:20,1');
        Route::post('/realms/{reference}/retrait', [RealmController::class, 'retirer'])
            ->middleware('throttle:20,1');
        Route::post('/realms/{reference}/relations', [RealmController::class, 'declarerRelation'])
            ->middleware('throttle:20,1');
        Route::post(
            '/realms/{reference}/relations/{relation}/fermeture',
            [RealmController::class, 'fermerRelation'],
        )->middleware('throttle:20,1');
        Route::post('/realms/{reference}/perimetres', [RealmController::class, 'declarerPerimetre'])
            ->middleware('throttle:20,1');
        Route::post(
            '/realms/{reference}/perimetres/{perimetre}/fermeture',
            [RealmController::class, 'fermerPerimetre'],
        )->middleware('throttle:20,1');
        Route::post('/realms/{reference}/identifiants-externes', [RealmController::class, 'declarerIdentifiant'])
            ->middleware('throttle:20,1');
        Route::post('/realms/{reference}/organisations', [RealmController::class, 'rattacherOrganisation'])
            ->middleware('throttle:20,1');
        Route::post(
            '/realms/{reference}/organisations/{rattachement}/fermeture',
            [RealmController::class, 'detacherOrganisation'],
        )->middleware('throttle:20,1');
        Route::post('/realms/{reference}/produits', [RealmController::class, 'rattacherProduit'])
            ->middleware('throttle:20,1');
        Route::post(
            '/realms/{reference}/produits/{rattachement}/fermeture',
            [RealmController::class, 'detacherProduit'],
        )->middleware('throttle:20,1');
        Route::post('/realms/{reference}/contrats', [RealmController::class, 'rattacherContrat'])
            ->middleware('throttle:20,1');
        Route::post(
            '/realms/{reference}/contrats/{rattachement}/fermeture',
            [RealmController::class, 'detacherContrat'],
        )->middleware('throttle:20,1');
        Route::post('/realms/{reference}/franchissements', [RealmController::class, 'declarerFranchissement'])
            ->middleware('throttle:20,1');
        Route::post(
            '/realms/{reference}/franchissements/{franchissement}/fermeture',
            [RealmController::class, 'fermerFranchissement'],
        )->middleware('throttle:20,1');
        Route::post('/realms/{reference}/verifications', [RealmController::class, 'enregistrerVerification'])
            ->middleware('throttle:20,1');

        // CAP-CORE-014 — journal des événements : publications.
        Route::post('/evenements/publications', [EvenementController::class, 'publier'])
            ->middleware('throttle:60,1');
        Route::get(
            '/evenements/publications/{producteur}/{idempotence}',
            [EvenementController::class, 'resoudrePublication'],
        );

        // CAP-CORE-014 — lecture des événements.
        Route::get('/evenements', [EvenementController::class, 'index']);
        Route::get('/evenements/{reference}', [EvenementController::class, 'show']);
        Route::get('/evenements/{reference}/charge', [EvenementController::class, 'charge']);

        // CAP-CORE-014 — abonnements.
        Route::get('/abonnements', [AbonnementController::class, 'index']);
        Route::post('/abonnements', [AbonnementController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/abonnements/{reference}', [AbonnementController::class, 'show']);
        Route::patch('/abonnements/{reference}', [AbonnementController::class, 'update'])
            ->middleware('throttle:20,1');
        Route::post('/abonnements/{reference}/types', [AbonnementController::class, 'ajouterType'])
            ->middleware('throttle:20,1');
        Route::post('/abonnements/{reference}/producteurs', [AbonnementController::class, 'ajouterProducteur'])
            ->middleware('throttle:20,1');
        Route::post('/abonnements/{reference}/realms', [AbonnementController::class, 'ajouterRealm'])
            ->middleware('throttle:20,1');
        Route::post('/abonnements/{reference}/activation', [AbonnementController::class, 'activer'])
            ->middleware('throttle:20,1');
        Route::post('/abonnements/{reference}/suspension', [AbonnementController::class, 'suspendre'])
            ->middleware('throttle:20,1');
        Route::post('/abonnements/{reference}/retrait', [AbonnementController::class, 'retirer'])
            ->middleware('throttle:20,1');
        Route::get('/abonnements/{reference}/retard', [AbonnementController::class, 'retard']);
        Route::get('/abonnements/{reference}/curseur', [AbonnementController::class, 'curseur']);

        // CAP-CORE-014 — livraisons PULL.
        Route::get('/abonnements/{reference}/livraisons', [AbonnementController::class, 'livraisons'])
            ->middleware('throttle:120,1');
        Route::post('/abonnements/{reference}/livraisons/accuses', [AbonnementController::class, 'accuser'])
            ->middleware('throttle:120,1');
        Route::post(
            '/abonnements/{reference}/livraisons/refus-temporaires',
            [AbonnementController::class, 'refusTemporaire'],
        )->middleware('throttle:120,1');
        Route::post(
            '/abonnements/{reference}/livraisons/refus-definitifs',
            [AbonnementController::class, 'refusDefinitif'],
        )->middleware('throttle:120,1');

        // CAP-CORE-014 — rejeu borné.
        Route::get('/rejeux', [RejeuController::class, 'index']);
        Route::post('/rejeux', [RejeuController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('/rejeux/{reference}', [RejeuController::class, 'show']);
        Route::post('/rejeux/{reference}/validation', [RejeuController::class, 'validation'])
            ->middleware('throttle:10,1');
        Route::post('/rejeux/{reference}/annulation', [RejeuController::class, 'annulation'])
            ->middleware('throttle:10,1');

        // CAP-CORE-014 — lettres mortes.
        Route::get('/lettres-mortes', [LettreMorteController::class, 'index']);
        Route::get('/lettres-mortes/{reference}', [LettreMorteController::class, 'show']);
        Route::post('/lettres-mortes/{reference}/relance', [LettreMorteController::class, 'relance'])
            ->middleware('throttle:20,1');
        Route::post('/lettres-mortes/{reference}/cloture', [LettreMorteController::class, 'cloture'])
            ->middleware('throttle:20,1');

        // CAP-CORE-016 — secrets et clés : métadonnées seules, jamais de valeur.
        Route::get('/secrets-cles', [SecretController::class, 'index']);
        Route::post('/secrets-cles', [SecretController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('/secrets-cles/diagnostic', [SecretController::class, 'diagnostic']);
        Route::get('/secrets-cles/compromissions', [SecretController::class, 'compromissions']);
        Route::get('/secrets-cles/{reference}', [SecretController::class, 'show']);
        Route::get('/secrets-cles/{reference}/versions', [SecretController::class, 'versions']);
        Route::post('/secrets-cles/{reference}/versions', [SecretController::class, 'storeVersion'])
            ->middleware('throttle:10,1');
        Route::get('/secrets-cles/{reference}/versions/{version}', [SecretController::class, 'showVersion']);
        Route::post('/secrets-cles/{reference}/versions/{version}/verification', [SecretController::class, 'verifierVersion'])
            ->middleware('throttle:20,1');
        Route::post('/secrets-cles/{reference}/versions/{id}/activation', [SecretController::class, 'activerVersion'])
            ->middleware('throttle:10,1');
        Route::post('/secrets-cles/{reference}/versions/{id}/suspension', [SecretController::class, 'suspendreVersion'])
            ->middleware('throttle:10,1');
        Route::post('/secrets-cles/{reference}/versions/{id}/revocation', [SecretController::class, 'revoquerVersion'])
            ->middleware('throttle:10,1');
        Route::post('/secrets-cles/{reference}/versions/{id}/compromission', [SecretController::class, 'compromettreVersion'])
            ->middleware('throttle:30,1');
        Route::post('/secrets-cles/{reference}/versions/{id}/destruction', [SecretController::class, 'detruireVersion'])
            ->middleware('throttle:5,1');
        Route::get('/secrets-cles/{reference}/usages', [SecretController::class, 'usages']);
        Route::post('/secrets-cles/{reference}/usages', [SecretController::class, 'storeUsage'])
            ->middleware('throttle:20,1');
        Route::get('/secrets-cles/{reference}/dependances', [SecretController::class, 'dependances']);
        Route::get('/secrets-cles/{reference}/rotations', [SecretController::class, 'rotations']);
        Route::post('/secrets-cles/{reference}/rotations', [SecretController::class, 'storeRotation'])
            ->middleware('throttle:10,1');

        Route::get('/fournisseurs-secrets', [FournisseurSecretController::class, 'index']);
        Route::post('/fournisseurs-secrets', [FournisseurSecretController::class, 'store'])
            ->middleware('throttle:10,1');

        Route::post('/rotations-secrets/{reference}/validation', [RotationSecretController::class, 'validation'])
            ->middleware('throttle:10,1');
        Route::post('/rotations-secrets/{reference}/execution', [RotationSecretController::class, 'execution'])
            ->middleware('throttle:20,1');

        // CAP-CORE-015 — preuves d'intégrité : lecture, vérification et
        // cycle. Aucune signature de contenu libre ni choix de clé par
        // l'appelant (fiche partie 4 §1) — voir PreuveController.
        Route::get('/preuves', [PreuveController::class, 'index']);
        Route::post('/preuves', [PreuveController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('/preuves/diagnostic', [PreuveController::class, 'diagnostic']);
        Route::post('/preuves/verification-paquet', [PreuveController::class, 'verificationPaquet'])
            ->middleware('throttle:30,1');
        Route::get('/preuves/{reference}', [PreuveController::class, 'show']);
        Route::get('/preuves/{reference}/etat', [PreuveController::class, 'etat']);
        Route::get('/preuves/{reference}/empreintes', [PreuveController::class, 'empreintes']);
        Route::get('/preuves/{reference}/signatures', [PreuveController::class, 'signatures']);
        Route::get('/preuves/{reference}/manifeste', [PreuveController::class, 'manifeste']);
        Route::get('/preuves/{reference}/attestation', [PreuveController::class, 'attestation']);
        Route::get('/preuves/{reference}/checkpoint', [PreuveController::class, 'checkpoint']);
        Route::get('/preuves/{reference}/verifications', [PreuveController::class, 'verifications']);
        Route::get('/preuves/{reference}/liens', [PreuveController::class, 'liens']);
        Route::post('/preuves/{reference}/verification', [PreuveController::class, 'verification'])
            ->middleware('throttle:60,1');
        Route::post('/preuves/{reference}/suspension', [PreuveController::class, 'suspension'])
            ->middleware('throttle:10,1');
        Route::post('/preuves/{reference}/revocation', [PreuveController::class, 'revocation'])
            ->middleware('throttle:10,1');
        Route::post('/preuves/{reference}/compromission', [PreuveController::class, 'compromission'])
            ->middleware('throttle:30,1');
        Route::post('/preuves/{reference}/export', [PreuveController::class, 'export'])
            ->middleware('throttle:20,1');

        // CAP-CORE-021 — Matching : périmètre exposé restreint à ce qui a un
        // code d'action exact dans PolitiqueMatching::ACTIONS — voir la
        // réserve documentée en tête d'AccesMatching (routes de lecture,
        // d'accusé, de suspension et de révocation d'une activation isolée,
        // et d'instruction d'une contestation, non câblées faute d'action
        // dédiée dans le vocabulaire fermé actuel).
        Route::get('/matching/contextes', [MatchingController::class, 'contextes']);
        Route::get('/matching/contextes/{reference}', [MatchingController::class, 'contexte']);
        Route::get('/matching/profils/{reference}', [MatchingController::class, 'profil']);
        Route::post('/matching/profils/compilation', [MatchingController::class, 'compilerProfil'])
            ->middleware('throttle:20,1');
        Route::get('/matching/demandes', [MatchingController::class, 'demandes']);
        Route::post('/matching/demandes', [MatchingController::class, 'soumettreDemande'])
            ->middleware('throttle:60,1');
        Route::get('/matching/demandes/{reference}', [MatchingController::class, 'demande']);
        Route::get('/matching/demandes/{reference}/historique', [MatchingController::class, 'historiqueDemande']);
        Route::post('/matching/demandes/{reference}/annulation', [MatchingController::class, 'annulerDemande'])
            ->middleware('throttle:20,1');
        Route::post('/matching/demandes/{reference}/execution', [MatchingController::class, 'executerDemande'])
            ->middleware('throttle:20,1');
        Route::get('/matching/resultats/{reference}', [MatchingController::class, 'resultat']);
        Route::get('/matching/resultats/{reference}/explication', [MatchingController::class, 'explicationResultat']);
        Route::post('/matching/segments', [MatchingController::class, 'construireSegment'])
            ->middleware('throttle:20,1');
        Route::get('/matching/segments/{reference}', [MatchingController::class, 'segment']);
        Route::post('/matching/segments/{reference}/appartenance', [MatchingController::class, 'appartenanceSegment'])
            ->middleware('throttle:60,1');
        Route::post('/matching/segments/{reference}/activation', [MatchingController::class, 'activerSegment'])
            ->middleware('throttle:20,1');
        Route::post('/matching/segments/{reference}/suspension', [MatchingController::class, 'suspensionSegment'])
            ->middleware('throttle:10,1');
        Route::post('/matching/segments/{reference}/revocation', [MatchingController::class, 'revocationSegment'])
            ->middleware('throttle:10,1');
        Route::post('/matching/activations/{reference}/mesures', [MatchingController::class, 'mesureActivation'])
            ->middleware('throttle:60,1');
        Route::post('/matching/contestations', [MatchingController::class, 'ouvrirContestation'])
            ->middleware('throttle:20,1');
        Route::post('/matching/contestations/{reference}/reexecution', [MatchingController::class, 'reexecutionContestation'])
            ->middleware('throttle:20,1');
    });
});
