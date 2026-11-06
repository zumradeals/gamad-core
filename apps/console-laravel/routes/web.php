<?php

declare(strict_types=1);

use App\Http\Controllers\AccesConsoleController;
use App\Http\Controllers\AccesController;
use App\Http\Controllers\AdministrationReseauGamadController;
use App\Http\Controllers\AnnuaireGamadController;
use App\Http\Controllers\BlocagesGamadController;
use App\Http\Controllers\ContenusGamadController;
use App\Http\Controllers\ContactGamadController;
use App\Http\Controllers\CompteReseauCoreController;
use App\Http\Controllers\ProfilCompteGamadController;
use App\Http\Controllers\ContinuiteConsoleController;
use App\Http\Controllers\Ctr01Controller;
use App\Http\Controllers\Ctr02Controller;
use App\Http\Controllers\Ctr03Controller;
use App\Http\Controllers\Ctr04Controller;
use App\Http\Controllers\EntreprisesGamadController;
use App\Http\Controllers\EspacesGamadController;
use App\Http\Controllers\EvenementConsoleController;
use App\Http\Controllers\IdentiteConsoleController;
use App\Http\Controllers\InscriptionGamadController;
use App\Http\Controllers\InteractionsGamadController;
use App\Http\Controllers\OrganisationConsoleController;
use App\Http\Controllers\RealmConsoleController;
use App\Http\Controllers\RelationsGamadController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\ContratConsoleController;
use App\Http\Controllers\PolitiqueConsoleController;
use App\Http\Controllers\MatchingConsoleController;
use App\Http\Controllers\PreuveConsoleController;
use App\Http\Controllers\ProduitConsoleController;
use App\Http\Controllers\SatelliteConsoleController;
use App\Http\Controllers\SecretConsoleController;
use App\Http\Controllers\SourceConsoleController;
use App\Http\Controllers\VocabulaireConsoleController;
use Illuminate\Support\Facades\Route;

/*
 * Console authentifiée : les lectures restent servies par les contrats du
 * Core. La seule écriture web est l'inscription gouvernée d'une identité ;
 * elle partage le même cas d'usage que l'API v1.
 */
Route::view('/', 'reseau.accueil')->name('reseau.accueil');
Route::view('/decouvrir', 'reseau.decouvrir')->name('reseau.decouvrir');
Route::view('/aide', 'reseau.aide')->name('reseau.aide');
Route::get('/contact', [ContactGamadController::class, 'formulaire'])
    ->middleware('gamad.https')->name('reseau.contact');
Route::post('/contact', [ContactGamadController::class, 'envoyer'])
    ->middleware(['gamad.https', 'throttle:15,1,contact-'])->name('reseau.contact.envoyer');
Route::get('/personnes', [AnnuaireGamadController::class, 'index'])->name('personnes.index');
Route::get('/personnes/{reference}', [AnnuaireGamadController::class, 'montrer'])
    ->where('reference', '[a-f0-9]{24}')->name('personnes.show');

Route::get('/entreprises', [EntreprisesGamadController::class, 'index'])->name('entreprises.index');
Route::get('/entreprises/{reference}', [EntreprisesGamadController::class, 'fiche'])
    ->where('reference', '[a-f0-9]{24}')->name('entreprises.show');
Route::get('/opportunites', [EntreprisesGamadController::class, 'opportunites'])->name('opportunites.index');
Route::get('/opportunites/{reference}', [EntreprisesGamadController::class, 'opportunite'])
    ->where('reference', '[a-f0-9]{24}')->name('opportunites.show');
Route::get('/zumra', [EspacesGamadController::class, 'zumras'])->name('zumras.index');
Route::get('/zumra/{reference}', [EspacesGamadController::class, 'zumra'])
    ->where('reference', '[a-f0-9]{24}')->name('zumras.show');
Route::get('/projets', [EspacesGamadController::class, 'projets'])->name('projets.index');
Route::get('/projets/{reference}', [EspacesGamadController::class, 'projet'])
    ->where('reference', '[a-f0-9]{24}')->name('projets.show');
Route::get('/besoins/{reference}', [ContenusGamadController::class, 'besoin'])
    ->where('reference', '[a-f0-9]{24}')->name('besoins.show');
Route::get('/publications/{reference}', [ContenusGamadController::class, 'montrer'])
    ->where('reference', '[a-f0-9]{24}')->name('publications.show');
Route::middleware(['gamad.https', 'gamad.session:reseau'])->group(function (): void {
    Route::get('/entreprises/creer', [EntreprisesGamadController::class, 'formulaire'])->name('entreprises.create');
    Route::post('/entreprises', [EntreprisesGamadController::class, 'soumettre'])
        ->middleware('throttle:5,1,entreprise-')->name('entreprises.store');
    Route::post('/entreprises/{reference}/resoumettre', [EntreprisesGamadController::class, 'resoumettre'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:5,1,entreprise-')->name('entreprises.resoumettre');
    Route::post('/entreprises/{reference}/opportunites', [EntreprisesGamadController::class, 'publier'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:10,1,opportunite-')->name('opportunites.store');
    Route::post('/opportunites/{reference}/candidatures', [EntreprisesGamadController::class, 'candidater'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,candidature-')->name('opportunites.candidater');
    Route::post('/opportunites/{reference}/cloture', [EntreprisesGamadController::class, 'cloturer'])
        ->where('reference', '[a-f0-9]{24}')->name('opportunites.cloturer');
    Route::get('/zumra/nouvelle', [EspacesGamadController::class, 'nouvelleZumra'])->name('zumras.create');
    Route::post('/zumra', [EspacesGamadController::class, 'creerZumra'])
        ->middleware('throttle:10,1,zumra-')->name('zumras.store');
    Route::patch('/projets/{reference}', [EspacesGamadController::class, 'modifierProjet'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,projet-modification-')->name('projets.modifier');
    Route::get('/projets/nouveau', [EspacesGamadController::class, 'nouveauProjet'])->name('projets.create');
    Route::post('/projets', [EspacesGamadController::class, 'creerProjet'])
        ->middleware('throttle:10,1,projet-')->name('projets.store');
    Route::post('/zumra/{reference}/accueil/{projet}', [EspacesGamadController::class, 'accueil'])
        ->where(['reference' => '[a-f0-9]{24}', 'projet' => '[a-f0-9]{24}'])->name('zumras.accueil');
    Route::post('/projets/{reference}/vitrine', [EspacesGamadController::class, 'vitrine'])
        ->where('reference', '[a-f0-9]{24}')->name('projets.vitrine');
    Route::post('/zumra/{reference}/mode-adhesion', [EspacesGamadController::class, 'modeAdhesion'])
        ->where('reference', '[a-f0-9]{24}')->name('zumras.mode-adhesion');
    Route::post('/projets/{reference}/participations', [EspacesGamadController::class, 'participation'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,participation-projet-')->name('projets.participations');
    Route::post('/zumra/{reference}/adhesion', [EspacesGamadController::class, 'adhesion'])
        ->where('reference', '[a-f0-9]{24}')->name('zumras.adhesion');
    Route::post('/zumra/{reference}/responsabilites', [EspacesGamadController::class, 'deleguer'])
        ->where('reference', '[a-f0-9]{24}')->name('zumras.deleguer');
    Route::post('/zumra/{reference}/membres', [EspacesGamadController::class, 'membreZumra'])
        ->where('reference', '[a-f0-9]{24}')->name('zumras.membres');
    Route::post('/projets/{reference}/invitations', [EspacesGamadController::class, 'inviter'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,invitation-projet-')->name('projets.inviter');
    Route::post('/projets/{reference}/etapes/{etape?}', [EspacesGamadController::class, 'etape'])
        ->where(['reference' => '[a-f0-9]{24}', 'etape' => '[a-f0-9]{24}'])
        ->middleware('throttle:30,1,etapes-projet-')->name('projets.etapes');
    Route::post('/projets/{reference}/equipe', [EspacesGamadController::class, 'membreProjet'])
        ->where('reference', '[a-f0-9]{24}')->name('projets.equipe');
    Route::get('/mon-profil/blocages', [BlocagesGamadController::class, 'index'])->name('blocages.index');
    Route::match(['POST', 'DELETE'], '/mon-profil/blocages/{reference}', [BlocagesGamadController::class, 'modifier'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,blocage-')->name('blocages.modifier');
    Route::get('/relations', [RelationsGamadController::class, 'index'])->name('relations.index');
    Route::post('/relations/{reference}', [RelationsGamadController::class, 'demander'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,demande-relation-')->name('relations.demander');
    Route::patch('/relations/{reference}', [RelationsGamadController::class, 'decider'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:30,1,decision-relation-')->name('relations.decider');
    Route::get('/conversations/{reference}', [InteractionsGamadController::class, 'conversation'])
        ->where('reference', '[a-f0-9]{24}')->name('conversations.show');
    Route::post('/conversations/{reference}', [InteractionsGamadController::class, 'envoyer'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:30,1,messagerie-')->name('conversations.envoyer');
    Route::get('/notifications', [InteractionsGamadController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/{reference}/lire', [InteractionsGamadController::class, 'lireNotification'])
        ->where('reference', '[a-f0-9]{24}')->name('notifications.lire');
    Route::post('/publications/{reference}/signalements/{commentaire?}', [InteractionsGamadController::class, 'signaler'])
        ->where(['reference' => '[a-f0-9]{24}', 'commentaire' => '[a-f0-9]{24}'])
        ->middleware('throttle:10,1,signalement-')->name('publications.signaler');
    Route::view('/agir', 'reseau.agir')->name('reseau.agir');
    Route::post('/besoins', [ContenusGamadController::class, 'publierBesoin'])
        ->middleware('throttle:10,1,besoin-')->name('besoins.store');
    Route::get('/besoins/{reference}/propositions', [ContenusGamadController::class, 'propositionsAide'])
        ->where('reference', '[a-f0-9]{24}')->name('besoins.propositions.index');
    Route::post('/besoins/{reference}/propositions', [ContenusGamadController::class, 'proposerAide'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:15,60,aide-')->name('besoins.propositions.store');
    Route::patch('/besoins/{reference}/propositions/{proposition}', [ContenusGamadController::class, 'traiterAide'])
        ->where(['reference' => '[a-f0-9]{24}', 'proposition' => '[a-f0-9]{24}'])
        ->middleware('throttle:30,1,aide-traitement-')->name('besoins.propositions.traiter');
    Route::patch('/besoins/{reference}/etat', [ContenusGamadController::class, 'terminerBesoin'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,besoin-etat-')->name('besoins.etat');
    Route::get('/fil', [ContenusGamadController::class, 'fil'])->name('fil');
    Route::post('/fil', [ContenusGamadController::class, 'publier'])
        ->middleware('throttle:10,1,publication-')->name('publications.store');
    Route::patch('/publications/{reference}/commentaires/{commentaire}', [ContenusGamadController::class, 'modifierCommentaire'])
        ->where(['reference' => '[a-f0-9]{24}', 'commentaire' => '[a-f0-9]{24}'])
        ->middleware('throttle:30,60,commentaire-modification-')->name('publications.commentaires.modifier');
    Route::post('/publications/{reference}/commentaires', [ContenusGamadController::class, 'commenter'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:30,60,commentaire-')->name('publications.commenter');
});

Route::middleware(['gamad.session', 'gamad.console'])->group(function (): void {
    Route::get('/console/reseau', [AdministrationReseauGamadController::class, 'index'])->name('reseau.administration');
    Route::get('/console/contact', [ContactGamadController::class, 'liste'])->name('reseau.contact.console');
    Route::get('/console/contact/{reference}', [ContactGamadController::class, 'ouvrir'])
        ->where('reference', '[a-f0-9]{24}')->name('reseau.contact.ouvrir');
    Route::post('/console/contact/{reference}', [ContactGamadController::class, 'traiter'])
        ->where('reference', '[a-f0-9]{24}')->name('reseau.contact.traiter');
    Route::get('/console/entreprises', [EntreprisesGamadController::class, 'revue'])->name('entreprises.revue');
    Route::get('/console/entreprises/{reference}/justificatif/{type?}', [EntreprisesGamadController::class, 'justificatif'])
        ->where(['reference' => '[a-f0-9]{24}', 'type' => 'rccm|dfe|idu'])->name('entreprises.justificatif');
    Route::post('/console/entreprises/{reference}', [EntreprisesGamadController::class, 'decider'])
        ->where('reference', '[a-f0-9]{24}')->name('entreprises.decider');
    Route::get('/console/moderation-reseau', [InteractionsGamadController::class, 'moderation'])->name('reseau.moderation');
    Route::post('/console/moderation-reseau/{reference}', [InteractionsGamadController::class, 'traiter'])
        ->where('reference', '[a-f0-9]{24}')->middleware('throttle:20,1,moderation-reseau-')->name('reseau.moderation.traiter');
    Route::get('/console', [Ctr04Controller::class, 'tableauDeBord'])->name('console.accueil');
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

    // CAP-CORE-002 — registre des organisations. Toute écriture passe par
    // `AccesOrganisations`, le même cas d'usage gouverné que l'API v1.
    Route::get('/organisations', [OrganisationConsoleController::class, 'index'])
        ->name('console.organisations.index');
    Route::get('/organisations/nouveau', [OrganisationConsoleController::class, 'create'])
        ->name('console.organisations.create');
    Route::post('/organisations', [OrganisationConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.store');
    Route::get('/organisations/{reference}', [OrganisationConsoleController::class, 'show'])
        ->name('console.organisations.show');
    Route::post('/organisations/{reference}/activation', [OrganisationConsoleController::class, 'activer'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.activer');
    Route::post('/organisations/{reference}/suspension', [OrganisationConsoleController::class, 'suspendre'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.suspendre');
    Route::post('/organisations/{reference}/dissolution', [OrganisationConsoleController::class, 'dissoudre'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.dissoudre');
    Route::post('/organisations/{reference}/retrait', [OrganisationConsoleController::class, 'retirer'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.retirer');
    Route::post('/organisations/{reference}/identifiants', [OrganisationConsoleController::class, 'declarerIdentifiant'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.identifiants.declarer');
    Route::post('/organisations/{reference}/unites', [OrganisationConsoleController::class, 'creerUnite'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.unites.creer');
    Route::post('/organisations/{reference}/relations', [OrganisationConsoleController::class, 'declarerRelation'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.relations.declarer');
    Route::post('/organisations/{reference}/affiliations', [OrganisationConsoleController::class, 'proposerAffiliation'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.affiliations.proposer');
    Route::post(
        '/organisations/{reference}/affiliations/{affiliation}/activation',
        [OrganisationConsoleController::class, 'activerAffiliation'],
    )->middleware('throttle:20,1')->name('console.organisations.affiliations.activer');
    Route::post(
        '/organisations/{reference}/affiliations/{affiliation}/suspension',
        [OrganisationConsoleController::class, 'suspendreAffiliation'],
    )->middleware('throttle:20,1')->name('console.organisations.affiliations.suspendre');
    Route::post(
        '/organisations/{reference}/affiliations/{affiliation}/fermeture',
        [OrganisationConsoleController::class, 'fermerAffiliation'],
    )->middleware('throttle:20,1')->name('console.organisations.affiliations.fermer');
    Route::post('/organisations/{reference}/fonctions', [OrganisationConsoleController::class, 'creerFonction'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.fonctions.creer');
    Route::post('/organisations/{reference}/representation/verification', [OrganisationConsoleController::class, 'verifierRepresentation'])
        ->middleware('throttle:20,1')
        ->name('console.organisations.representation.verifier');

    // CAP-CORE-012 — registre des realms. Toute écriture passe par
    // `AccesRealms`, le même cas d'usage gouverné que l'API v1.
    Route::get('/realms', [RealmConsoleController::class, 'index'])
        ->name('console.realms.index');
    Route::get('/realms/nouveau', [RealmConsoleController::class, 'create'])
        ->name('console.realms.create');
    Route::post('/realms', [RealmConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.realms.store');
    Route::get('/realms/{reference}', [RealmConsoleController::class, 'show'])
        ->name('console.realms.show');
    Route::post('/realms/{reference}/activation', [RealmConsoleController::class, 'activer'])
        ->middleware('throttle:20,1')
        ->name('console.realms.activer');
    Route::post('/realms/{reference}/suspension', [RealmConsoleController::class, 'suspendre'])
        ->middleware('throttle:20,1')
        ->name('console.realms.suspendre');
    Route::post('/realms/{reference}/fermeture', [RealmConsoleController::class, 'fermer'])
        ->middleware('throttle:20,1')
        ->name('console.realms.fermer');
    Route::post('/realms/{reference}/retrait', [RealmConsoleController::class, 'retirer'])
        ->middleware('throttle:20,1')
        ->name('console.realms.retirer');
    Route::post('/realms/{reference}/relations', [RealmConsoleController::class, 'declarerRelation'])
        ->middleware('throttle:20,1')
        ->name('console.realms.relations.declarer');
    Route::post('/realms/{reference}/perimetres', [RealmConsoleController::class, 'declarerPerimetre'])
        ->middleware('throttle:20,1')
        ->name('console.realms.perimetres.declarer');
    Route::post('/realms/{reference}/identifiants', [RealmConsoleController::class, 'declarerIdentifiant'])
        ->middleware('throttle:20,1')
        ->name('console.realms.identifiants.declarer');
    Route::post('/realms/{reference}/organisations', [RealmConsoleController::class, 'rattacherOrganisation'])
        ->middleware('throttle:20,1')
        ->name('console.realms.organisations.rattacher');
    Route::post('/realms/{reference}/produits', [RealmConsoleController::class, 'rattacherProduit'])
        ->middleware('throttle:20,1')
        ->name('console.realms.produits.rattacher');
    Route::post('/realms/{reference}/contrats', [RealmConsoleController::class, 'rattacherContrat'])
        ->middleware('throttle:20,1')
        ->name('console.realms.contrats.rattacher');
    Route::post('/realms/{reference}/franchissements', [RealmConsoleController::class, 'declarerFranchissement'])
        ->middleware('throttle:20,1')
        ->name('console.realms.franchissements.declarer');
    Route::post('/realms/{reference}/verifications', [RealmConsoleController::class, 'enregistrerVerification'])
        ->middleware('throttle:20,1')
        ->name('console.realms.verifications.enregistrer');
    Route::post('/realms/{reference}/portee', [RealmConsoleController::class, 'verifierPortee'])
        ->middleware('throttle:60,1')
        ->name('console.realms.portee.verifier');

    // CAP-CORE-014 — journal des événements. Toute lecture et toute écriture
    // passent par `AccesEvenements`, le même cas d'usage gouverné que l'API
    // v1 (fiche partie 4 §9) : contrairement aux consoles ci-dessus, la
    // confidentialité par realm de CAP-CORE-014 s'applique aussi aux
    // lectures, pas seulement aux écritures.
    Route::get('/evenements', [EvenementConsoleController::class, 'index'])
        ->name('console.evenements.index');
    Route::get('/evenements/{reference}', [EvenementConsoleController::class, 'showEvenement'])
        ->name('console.evenements.show');

    Route::get('/abonnements/{reference}', [EvenementConsoleController::class, 'showAbonnement'])
        ->name('console.abonnements.show');
    Route::post('/abonnements/{reference}/activation', [EvenementConsoleController::class, 'activerAbonnement'])
        ->middleware('throttle:20,1')
        ->name('console.abonnements.activer');
    Route::post('/abonnements/{reference}/suspension', [EvenementConsoleController::class, 'suspendreAbonnement'])
        ->middleware('throttle:20,1')
        ->name('console.abonnements.suspendre');
    Route::post('/abonnements/{reference}/retrait', [EvenementConsoleController::class, 'retirerAbonnement'])
        ->middleware('throttle:20,1')
        ->name('console.abonnements.retirer');
    Route::post('/abonnements/{reference}/types', [EvenementConsoleController::class, 'ajouterType'])
        ->middleware('throttle:20,1')
        ->name('console.abonnements.types.ajouter');
    Route::post('/abonnements/{reference}/producteurs', [EvenementConsoleController::class, 'ajouterProducteur'])
        ->middleware('throttle:20,1')
        ->name('console.abonnements.producteurs.ajouter');
    Route::post('/abonnements/{reference}/realms', [EvenementConsoleController::class, 'ajouterRealm'])
        ->middleware('throttle:20,1')
        ->name('console.abonnements.realms.ajouter');

    Route::get('/lettres-mortes', [EvenementConsoleController::class, 'lettresMortesIndex'])
        ->name('console.lettres-mortes.index');
    Route::get('/lettres-mortes/{reference}', [EvenementConsoleController::class, 'lettresMortesShow'])
        ->name('console.lettres-mortes.show');
    Route::post('/lettres-mortes/{reference}/relance', [EvenementConsoleController::class, 'relancerLettreMorte'])
        ->middleware('throttle:20,1')
        ->name('console.lettres-mortes.relancer');
    Route::post('/lettres-mortes/{reference}/cloture', [EvenementConsoleController::class, 'cloturerLettreMorte'])
        ->middleware('throttle:20,1')
        ->name('console.lettres-mortes.cloturer');

    Route::get('/rejeux', [EvenementConsoleController::class, 'rejeuxIndex'])
        ->name('console.rejeux.index');
    Route::get('/rejeux/nouveau', [EvenementConsoleController::class, 'rejeuxCreate'])
        ->name('console.rejeux.create');
    Route::post('/rejeux', [EvenementConsoleController::class, 'rejeuxStore'])
        ->middleware('throttle:10,1')
        ->name('console.rejeux.store');
    Route::get('/rejeux/{reference}', [EvenementConsoleController::class, 'rejeuxShow'])
        ->name('console.rejeux.show');
    Route::post('/rejeux/{reference}/validation', [EvenementConsoleController::class, 'validerRejeu'])
        ->middleware('throttle:10,1')
        ->name('console.rejeux.valider');
    Route::post('/rejeux/{reference}/annulation', [EvenementConsoleController::class, 'annulerRejeu'])
        ->middleware('throttle:10,1')
        ->name('console.rejeux.annuler');

    // CAP-CORE-016 — secrets et clés. Métadonnées seules, comme l'API v1 :
    // aucune vue ne reçoit jamais de valeur secrète (fiche partie 4 §8).
    Route::get('/secrets-cles', [SecretConsoleController::class, 'index'])
        ->name('console.secrets-cles.index');
    Route::get('/secrets-cles/{reference}', [SecretConsoleController::class, 'show'])
        ->name('console.secrets-cles.show');
    Route::post('/secrets-cles/{reference}/versions/{id}/suspension', [SecretConsoleController::class, 'suspendreVersion'])
        ->middleware('throttle:10,1')
        ->name('console.secrets-cles.versions.suspendre');
    Route::post('/secrets-cles/{reference}/versions/{id}/revocation', [SecretConsoleController::class, 'revoquerVersion'])
        ->middleware('throttle:10,1')
        ->name('console.secrets-cles.versions.revoquer');
    Route::post('/secrets-cles/{reference}/versions/{id}/compromission', [SecretConsoleController::class, 'compromettreVersion'])
        ->middleware('throttle:10,1')
        ->name('console.secrets-cles.versions.compromettre');

    // CAP-CORE-015 — preuves d'intégrité. Jamais de signature de texte libre
    // depuis cet écran (fiche partie 4 §9.4) : émission, checkpoint,
    // manifeste et attestation restent des commandes CLI d'exploitation.
    Route::get('/preuves', [PreuveConsoleController::class, 'index'])
        ->name('console.preuves.index');
    Route::get('/preuves/{reference}', [PreuveConsoleController::class, 'show'])
        ->name('console.preuves.show');
    Route::post('/preuves/{reference}/verification', [PreuveConsoleController::class, 'verifier'])
        ->middleware('throttle:60,1')
        ->name('console.preuves.verifier');
    Route::post('/preuves/{reference}/suspension', [PreuveConsoleController::class, 'suspendre'])
        ->middleware('throttle:10,1')
        ->name('console.preuves.suspendre');
    Route::post('/preuves/{reference}/revocation', [PreuveConsoleController::class, 'revoquer'])
        ->middleware('throttle:10,1')
        ->name('console.preuves.revoquer');
    Route::post('/preuves/{reference}/compromission', [PreuveConsoleController::class, 'compromettre'])
        ->middleware('throttle:10,1')
        ->name('console.preuves.compromettre');

    // CAP-CORE-021 — Matching. Console de lecture seule : aucune écriture
    // (soumission, exécution, segment, activation) n'est exposée ici — voir
    // MatchingConsoleController. Aucun membre de segment n'est jamais affiché.
    Route::get('/matching', [MatchingConsoleController::class, 'index'])
        ->name('console.matching.index');
    Route::get('/matching/demandes/{reference}', [MatchingConsoleController::class, 'showDemande'])
        ->name('console.matching.demandes.show');
    Route::get('/matching/contextes/{reference}', [MatchingConsoleController::class, 'showContexte'])
        ->name('console.matching.contextes.show');
    Route::get('/matching/segments/{reference}', [MatchingConsoleController::class, 'showSegment'])
        ->name('console.matching.segments.show');

    // CAP-CORE-006 — registre des sources. Vit sous `/registre-sources` : le
    // chemin `/sources/{reference}` reste la route JSON historique de CTR-04
    // ci-dessus, préservée pour compatibilité. Toute écriture passe par
    // `AccesSources`, le même cas d'usage gouverné que l'API v1.
    Route::get('/registre-sources', [SourceConsoleController::class, 'index'])
        ->name('console.sources.index');
    Route::get('/registre-sources/nouvelle', [SourceConsoleController::class, 'create'])
        ->name('console.sources.create');
    Route::post('/registre-sources', [SourceConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.sources.store');
    Route::get('/registre-sources/{reference}', [SourceConsoleController::class, 'show'])
        ->name('console.sources.show');
    Route::post('/registre-sources/{reference}/modification', [SourceConsoleController::class, 'modifier'])
        ->middleware('throttle:20,1')
        ->name('console.sources.modifier');
    Route::post('/registre-sources/{reference}/activation', [SourceConsoleController::class, 'activer'])
        ->middleware('throttle:20,1')
        ->name('console.sources.activer');
    Route::post('/registre-sources/{reference}/suspension', [SourceConsoleController::class, 'suspendre'])
        ->middleware('throttle:20,1')
        ->name('console.sources.suspendre');
    Route::post('/registre-sources/{reference}/retrait', [SourceConsoleController::class, 'retirer'])
        ->middleware('throttle:20,1')
        ->name('console.sources.retirer');
    Route::post('/registre-sources/{reference}/finalites', [SourceConsoleController::class, 'declarerFinalite'])
        ->middleware('throttle:20,1')
        ->name('console.sources.finalites.declarer');
    Route::post(
        '/registre-sources/{reference}/finalites/{id}/fermeture',
        [SourceConsoleController::class, 'fermerFinalite'],
    )->whereNumber('id')->middleware('throttle:20,1')->name('console.sources.finalites.fermer');
    Route::post('/registre-sources/{reference}/verifications', [SourceConsoleController::class, 'enregistrerVerification'])
        ->middleware('throttle:20,1')
        ->name('console.sources.verifications.enregistrer');
    Route::post('/registre-sources/{reference}/lignee', [SourceConsoleController::class, 'declarerLignee'])
        ->middleware('throttle:20,1')
        ->name('console.sources.lignee.declarer');

    // CAP-CORE-007 — registre des politiques. CTR-03 (CAP-CORE-004) lit ce
    // magasin pour décider. Toute écriture passe par `AccesPolitiques`, le
    // même cas d'usage gouverné que l'API v1.
    Route::get('/politiques', [PolitiqueConsoleController::class, 'index'])
        ->name('console.politiques.index');
    Route::get('/politiques/nouvelle', [PolitiqueConsoleController::class, 'create'])
        ->name('console.politiques.create');
    Route::post('/politiques', [PolitiqueConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.politiques.store');
    Route::get('/politiques/{reference}', [PolitiqueConsoleController::class, 'show'])
        ->name('console.politiques.show');
    Route::post('/politiques/{reference}/versions', [PolitiqueConsoleController::class, 'creerVersion'])
        ->middleware('throttle:20,1')
        ->name('console.politiques.versions.creer');
    Route::get('/politiques/{reference}/versions/{version}', [PolitiqueConsoleController::class, 'versionShow'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')
        ->name('console.politiques.version');
    Route::post('/politiques/{reference}/versions/{version}/regles', [PolitiqueConsoleController::class, 'ajouterRegle'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.politiques.versions.regles.ajouter');
    Route::post('/politiques/{reference}/versions/{version}/soumission', [PolitiqueConsoleController::class, 'soumettre'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.politiques.versions.soumettre');
    Route::post('/politiques/{reference}/versions/{version}/simulation', [PolitiqueConsoleController::class, 'simuler'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.politiques.versions.simuler');
    Route::post('/politiques/{reference}/versions/{version}/activation', [PolitiqueConsoleController::class, 'activer'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.politiques.versions.activer');
    Route::post('/politiques/{reference}/versions/{version}/suspension', [PolitiqueConsoleController::class, 'suspendre'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.politiques.versions.suspendre');
    Route::post('/politiques/{reference}/retrait', [PolitiqueConsoleController::class, 'retirer'])
        ->middleware('throttle:20,1')
        ->name('console.politiques.retirer');

    // CAP-CORE-009 — registre des contrats. Toute écriture passe par
    // `AccesContrats`, le même cas d'usage gouverné que l'API v1.
    Route::get('/contrats', [ContratConsoleController::class, 'index'])
        ->name('console.contrats.index');
    Route::get('/contrats/nouveau', [ContratConsoleController::class, 'create'])
        ->name('console.contrats.create');
    Route::post('/contrats', [ContratConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.contrats.store');
    Route::get('/contrats/{reference}', [ContratConsoleController::class, 'show'])
        ->name('console.contrats.show');
    Route::post('/contrats/{reference}/versions', [ContratConsoleController::class, 'creerVersion'])
        ->middleware('throttle:20,1')
        ->name('console.contrats.versions.creer');
    Route::get('/contrats/{reference}/versions/{version}', [ContratConsoleController::class, 'versionShow'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')
        ->name('console.contrats.version');
    Route::post('/contrats/{reference}/versions/{version}/parties', [ContratConsoleController::class, 'declarerPartie'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.parties.declarer');
    Route::post('/contrats/{reference}/versions/{version}/operations', [ContratConsoleController::class, 'declarerOperation'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.operations.declarer');
    Route::post('/contrats/{reference}/versions/{version}/schemas', [ContratConsoleController::class, 'declarerSchema'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.schemas.declarer');
    Route::post('/contrats/{reference}/versions/{version}/erreurs', [ContratConsoleController::class, 'declarerErreur'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.erreurs.declarer');
    Route::post('/contrats/{reference}/versions/{version}/soumission', [ContratConsoleController::class, 'soumettre'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.soumettre');
    Route::post('/contrats/{reference}/versions/{version}/analyse', [ContratConsoleController::class, 'analyser'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.analyser');
    Route::post('/contrats/{reference}/versions/{version}/conformite', [ContratConsoleController::class, 'enregistrerConformite'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.conformite');
    Route::post('/contrats/{reference}/versions/{version}/activation', [ContratConsoleController::class, 'activer'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.activer');
    Route::post('/contrats/{reference}/versions/{version}/depreciation', [ContratConsoleController::class, 'deprecier'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.deprecier');
    Route::post('/contrats/{reference}/versions/{version}/suspension', [ContratConsoleController::class, 'suspendre'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.suspendre');
    Route::post('/contrats/{reference}/versions/{version}/retrait', [ContratConsoleController::class, 'retirer'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.contrats.versions.retirer');

    // CAP-CORE-010 — registre du vocabulaire canonique. Toute écriture passe
    // par `AccesVocabulaire`, le même cas d'usage gouverné que l'API v1.
    Route::get('/vocabulaires', [VocabulaireConsoleController::class, 'index'])
        ->name('console.vocabulaires.index');
    Route::get('/vocabulaires/nouveau', [VocabulaireConsoleController::class, 'create'])
        ->name('console.vocabulaires.create');
    Route::post('/vocabulaires', [VocabulaireConsoleController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('console.vocabulaires.store');
    Route::get('/vocabulaires/{reference}', [VocabulaireConsoleController::class, 'show'])
        ->name('console.vocabulaires.show');
    Route::post('/vocabulaires/{reference}/versions', [VocabulaireConsoleController::class, 'creerVersion'])
        ->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.creer');
    Route::get('/vocabulaires/{reference}/versions/{version}', [VocabulaireConsoleController::class, 'versionShow'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')
        ->name('console.vocabulaires.version');
    Route::post('/vocabulaires/{reference}/versions/{version}/termes', [VocabulaireConsoleController::class, 'ajouterTerme'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.termes.ajouter');
    Route::post('/vocabulaires/{reference}/versions/{version}/termes/evolution', [VocabulaireConsoleController::class, 'evoluerTerme'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.termes.evoluer');
    Route::post('/vocabulaires/{reference}/versions/{version}/termes/libelles', [VocabulaireConsoleController::class, 'ajouterLibelle'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.termes.libelles.ajouter');
    Route::post('/vocabulaires/{reference}/versions/{version}/termes/alias', [VocabulaireConsoleController::class, 'ajouterAlias'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.termes.alias.ajouter');
    Route::post('/vocabulaires/{reference}/versions/{version}/termes/depreciation', [VocabulaireConsoleController::class, 'deprecierTerme'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.termes.deprecier');
    Route::post('/vocabulaires/{reference}/versions/{version}/termes/retrait', [VocabulaireConsoleController::class, 'retirerTerme'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.termes.retirer');
    Route::post('/vocabulaires/{reference}/versions/{version}/soumission', [VocabulaireConsoleController::class, 'soumettre'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.soumettre');
    Route::post('/vocabulaires/{reference}/versions/{version}/analyse', [VocabulaireConsoleController::class, 'analyser'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.analyser');
    Route::post('/vocabulaires/{reference}/versions/{version}/projections', [VocabulaireConsoleController::class, 'genererProjection'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.projections.generer');
    Route::post('/vocabulaires/{reference}/versions/{version}/conformite', [VocabulaireConsoleController::class, 'enregistrerConformite'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.conformite');
    Route::post('/vocabulaires/{reference}/versions/{version}/activation', [VocabulaireConsoleController::class, 'activer'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.activer');
    Route::post('/vocabulaires/{reference}/versions/{version}/depreciation', [VocabulaireConsoleController::class, 'deprecier'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.deprecier');
    Route::post('/vocabulaires/{reference}/versions/{version}/retrait', [VocabulaireConsoleController::class, 'retirer'])
        ->where('version', '[0-9]+\.[0-9]+\.[0-9]+')->middleware('throttle:20,1')
        ->name('console.vocabulaires.versions.retirer');

});

// Gestion personnelle des moyens d'accès : session requise, pas de mandat administratif.
Route::middleware('gamad.session')->group(function (): void {
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

});

// Acces Réseau : session Core requise, sans privilege de console.
Route::middleware('gamad.session:reseau')->group(function (): void {
    Route::get('/reseau/mon-compte', [CompteReseauCoreController::class, 'montrer'])->name('reseau.compte.core');
    Route::get('/mon-profil', [ProfilCompteGamadController::class, 'montrer'])->name('compte.profil');
    Route::patch('/mon-profil', [ProfilCompteGamadController::class, 'modifier'])
        ->middleware('throttle:20,1,profil-')->name('compte.profil.modifier');
});

// Le reste de la console demeure strictement administratif.
Route::middleware(['gamad.session', 'gamad.console'])->group(function (): void {
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

// Inscription personnelle. Le navigateur ne choisit ni le produit ni le sujet du défi.
Route::middleware('gamad.https')->group(function (): void {
    Route::get('/inscription', [InscriptionGamadController::class, 'formulaire'])->name('inscription.formulaire');
    Route::post('/inscription', [InscriptionGamadController::class, 'inscrire'])
        ->middleware('throttle:5,1')->name('inscription.creer');
    Route::get('/inscription/verification', [InscriptionGamadController::class, 'verification'])->name('inscription.verification');
    Route::post('/inscription/verification', [InscriptionGamadController::class, 'verifier'])
        ->middleware('throttle:10,1')->name('inscription.verifier');
    Route::post('/inscription/verification/renvoi', [InscriptionGamadController::class, 'renvoyer'])
        ->middleware('throttle:5,1')->name('inscription.renvoyer');
});

Route::middleware('gamad.https')->group(function (): void {
    Route::view('/reseau/connexion', 'reseau.connexion')->name('reseau.connexion');
    Route::post('/reseau/connexion', [AccesController::class, 'connecterReseau'])
        ->middleware('throttle:10,1')->name('reseau.connecter');
});

// Accès au compte et à la console (CAP-CORE-005).
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
