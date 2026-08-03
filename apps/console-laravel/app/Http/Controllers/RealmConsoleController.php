<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Realms\AccesRealms;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\PolitiqueRealms;
use Gamad\RegistreRealms\RegistreRealms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration du registre des realms (CAP-CORE-012).
 *
 * Les lectures interrogent directement le registre, comme les écrans
 * Organisations et Produits le font pour leurs capacités respectives. Toute
 * écriture passe par `AccesRealms`, le même cas d'usage gouverné que l'API
 * v1 : la console n'ouvre aucun chemin parallèle et n'écrit jamais en direct
 * dans le magasin des realms.
 */
final class RealmConsoleController
{
    public function index(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        $etat = trim((string) $request->query('etat', ''));
        $type = trim((string) $request->query('type', ''));
        $recherche = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');

        $tous = $this->registre()->listerRealms();
        $visibles = array_values(array_filter(
            $tous,
            static fn (array $r): bool => $r['etat'] === 'ACTIF' || $autorite,
        ));
        $realms = array_values(array_filter(
            $visibles,
            static function (array $r) use ($etat, $type, $recherche): bool {
                if ($etat !== '' && $r['etat'] !== $etat) {
                    return false;
                }
                if ($type !== '' && $r['type_realm_reference'] !== $type) {
                    return false;
                }
                if ($recherche === '') {
                    return true;
                }
                $libelle = (string) ($r['revision']['nom_affichage'] ?? '');
                $texte = mb_strtolower($libelle . ' ' . $r['reference'] . ' ' . $r['code_canonique'], 'UTF-8');

                return str_contains($texte, $recherche);
            },
        ));

        return view('realms.index', [
            'realms' => $realms,
            'total' => count($visibles),
            'autorite' => $autorite,
            'filtres' => ['q' => $request->query('q'), 'etat' => $etat, 'type' => $type],
            'etats' => PolitiqueRealms::ETATS_CYCLE,
            'types' => PolitiqueRealms::TYPES_REALM,
        ]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, PolitiqueRealms::ACTION_INSCRIRE, null);

        return view('realms.create', [
            'acteur' => $acteur,
            'decision' => $decision,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS',
            'types' => PolitiqueRealms::TYPES_REALM,
            'classifications' => PolitiqueRealms::CLASSIFICATIONS,
        ]);
    }

    public function store(Request $request, AccesRealms $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'code_canonique' => ['required', 'string', 'max:128'],
            'type_realm_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::TYPES_REALM)],
            'nom_affichage' => ['required', 'string', 'max:500'],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::CLASSIFICATIONS)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['inscription' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()
            ->route('console.realms.show', $reference)
            ->with('succes', 'Realm inscrit en PREPARATION.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $registre = $this->registre();
        $realm = $registre->resoudreRealm($reference);
        abort_if($realm === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless($realm['etat'] === 'ACTIF' || $autorite, 404);

        return view('realms.show', [
            'realm' => $realm,
            'historique' => $registre->resoudreHistorique($reference),
            'parents' => $registre->resoudreParents($reference),
            'enfants' => $registre->resoudreEnfants($reference),
            'relations' => $registre->resoudreRelations($reference),
            'perimetres' => $registre->resoudrePerimetres($reference),
            'identifiants' => $registre->resoudreIdentifiantsExternes($reference),
            'organisations' => $registre->resoudreOrganisations($reference),
            'produits' => $registre->resoudreProduits($reference),
            'contrats' => $registre->resoudreContrats($reference),
            'franchissements' => $registre->resoudreFranchissements($reference),
            'verification' => $registre->resoudreVerificationCourante($reference),
            'autorite' => $autorite,
            'typesRelation' => PolitiqueRealms::TYPES_RELATION,
            'dimensionsPerimetre' => PolitiqueRealms::DIMENSIONS_PERIMETRE,
            'rolesOrganisation' => PolitiqueRealms::ROLES_ORGANISATION,
            'rolesProduit' => PolitiqueRealms::ROLES_PRODUIT,
            'rolesContrat' => PolitiqueRealms::ROLES_CONTRAT,
            'effetsFranchissement' => PolitiqueRealms::EFFETS_FRANCHISSEMENT,
            'typesVerification' => PolitiqueRealms::TYPES_VERIFICATION,
            'resultatsVerification' => PolitiqueRealms::RESULTATS_VERIFICATION,
            'classifications' => PolitiqueRealms::CLASSIFICATIONS,
        ]);
    }

    public function activer(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'activer', 'Realm activé.');
    }

    public function suspendre(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'suspendre', 'Realm suspendu. Aucun nouveau rattachement ou franchissement n’y est possible.');
    }

    public function fermer(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'fermer', 'Realm fermé. Son historique reste consultable.');
    }

    public function retirer(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['motif_reference' => ['required', 'string', 'max:64']]);
        $execution = $acces->retirer($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['realm' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Realm retiré, irréversiblement. Sa fiche reste consultable, sans suppression.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerRelation(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'realm_cible_reference' => ['required', 'string', 'max:64'],
            'type_relation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::TYPES_RELATION)],
        ]);
        $donnees['realm_source_reference'] = $reference;
        $execution = $acces->declarerRelation($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['relation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Relation entre realms déclarée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerPerimetre(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'dimension_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::DIMENSIONS_PERIMETRE)],
            'valeur_reference' => ['required', 'string', 'max:255'],
        ]);
        $execution = $acces->declarerPerimetre($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['perimetre' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Périmètre déclaré.')->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerIdentifiant(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'systeme_reference' => ['required', 'string', 'max:128'],
            'valeur' => ['required', 'string', 'max:255'],
        ]);
        $execution = $acces->declarerIdentifiant($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['identifiant' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Identifiant externe déclaré.')->with('preuve', $execution['corps']['preuve']);
    }

    public function rattacherOrganisation(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'organisation_reference' => ['required', 'string', 'max:64'],
            'role_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::ROLES_ORGANISATION)],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::CLASSIFICATIONS)],
        ]);
        $execution = $acces->rattacherOrganisation($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['organisation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Organisation rattachée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function rattacherProduit(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'produit_reference' => ['required', 'string', 'max:64'],
            'role_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::ROLES_PRODUIT)],
            'environnement_reference' => ['nullable', 'string', 'max:64'],
        ]);
        $execution = $acces->rattacherProduit($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['produit' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Produit rattaché.')->with('preuve', $execution['corps']['preuve']);
    }

    public function rattacherContrat(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'contrat_reference' => ['required', 'string', 'max:64'],
            'role_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::ROLES_CONTRAT)],
        ]);
        $execution = $acces->rattacherContrat($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['contrat' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Contrat rattaché.')->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerFranchissement(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'realm_cible_reference' => ['required', 'string', 'max:64'],
            'objet_reference' => ['required', 'string', 'max:255'],
            'type_objet_reference' => ['required', 'string', 'max:64'],
            'effet_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::EFFETS_FRANCHISSEMENT)],
            'finalite_reference' => ['required', 'string', 'max:255'],
        ]);
        $donnees['realm_source_reference'] = $reference;
        $execution = $acces->declarerFranchissement($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['franchissement' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Franchissement déclaré.')->with('preuve', $execution['corps']['preuve']);
    }

    public function enregistrerVerification(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'type_verification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::TYPES_VERIFICATION)],
            'resultat_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueRealms::RESULTATS_VERIFICATION)],
            'verifie_par_reference' => ['required', 'string', 'max:64'],
            'expire_le' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->enregistrerVerification($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['verification' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.realms.show', $reference)
            ->with('succes', 'Vérification enregistrée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function verifierPortee(Request $request, AccesRealms $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'organisation' => ['nullable', 'string', 'max:64'],
            'produit' => ['nullable', 'string', 'max:64'],
            'contrat' => ['nullable', 'string', 'max:64'],
            'realm_cible' => ['nullable', 'string', 'max:64'],
            'finalite' => ['nullable', 'string', 'max:255'],
        ]);
        if (($donnees['realm_cible'] ?? null) !== null) {
            $donnees['realm_source'] = $reference;
        }
        $execution = $acces->verifierPortee($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        $resultat = $execution['corps'];
        $message = ($resultat['dans_portee'] ?? false)
            ? 'Dans la portée déclarée du realm.'
            : 'Hors portée : ' . implode(', ', $resultat['motifs'] ?? []);

        return redirect()->route('console.realms.show', $reference)->with('succes', $message);
    }

    private function transition(
        Request $request,
        AccesRealms $acces,
        string $reference,
        string $methode,
        string $messageSucces,
    ): RedirectResponse {
        $donnees = $request->validate([
            'motif_reference' => ['nullable', 'string', 'max:64'],
            'motif_detail' => ['nullable', 'string', 'max:500'],
        ]);
        $execution = $acces->{$methode}($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['realm' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.realms.show', $reference)
            ->with('succes', $messageSucces)
            ->with('preuve', $execution['corps']['preuve']);
    }

    private function registre(): RegistreRealms
    {
        $index = Db::connect();
        try {
            $vide = ((int) $index->query('SELECT count(*) FROM entite')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            BaselineOperationnelle::standard()->reconstruire($index);
        }
        $registreIdentites = IdentiteMagasin::connecter();
        $ctr01 = new Ctr01($index, $registreIdentites);
        $organisations = new RegistreOrganisations($index, $registreIdentites, OrganisationsMagasin::connecter(), $ctr01);
        $produits = new RegistreProduits($index, $registreIdentites, ProduitsMagasin::connecter(), $ctr01);
        $contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);

        return new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01, $organisations, $produits, $contrats);
    }

    /** @param array<string,mixed> $corps */
    private function motif(array $corps): string
    {
        return (string) ($corps['resultat']['detail']
            ?? $corps['message']
            ?? $corps['decision']['motif']
            ?? 'Le Core a refusé cette opération.');
    }
}
