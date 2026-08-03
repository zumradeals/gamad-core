<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Organisations\AccesOrganisations;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Gamad\RegistreOrganisations\RegistreOrganisations;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration du registre des organisations (CAP-CORE-002).
 *
 * Les lectures interrogent directement le registre, comme les écrans
 * Produits et Contrats le font pour leurs capacités respectives. Toute
 * écriture passe par `AccesOrganisations`, le même cas d'usage gouverné que
 * l'API v1 : la console n'ouvre aucun chemin parallèle et n'écrit jamais en
 * direct dans le magasin des organisations.
 */
final class OrganisationConsoleController
{
    public function index(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        $etat = trim((string) $request->query('etat', ''));
        $type = trim((string) $request->query('type', ''));
        $recherche = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');

        $tous = $this->registre()->listerOrganisations();
        $visibles = array_values(array_filter(
            $tous,
            static fn (array $o): bool => $o['etat'] === 'ACTIVE' || $autorite || $o['proprietaire_reference'] === $acteur,
        ));
        $organisations = array_values(array_filter(
            $visibles,
            static function (array $o) use ($etat, $type, $recherche): bool {
                if ($etat !== '' && $o['etat'] !== $etat) {
                    return false;
                }
                if ($type !== '' && $o['type_organisation_reference'] !== $type) {
                    return false;
                }
                if ($recherche === '') {
                    return true;
                }
                $libelle = (string) ($o['revision']['denomination_officielle'] ?? '');
                $texte = mb_strtolower($libelle . ' ' . $o['reference'], 'UTF-8');

                return str_contains($texte, $recherche);
            },
        ));

        return view('organisations.index', [
            'organisations' => $organisations,
            'total' => count($visibles),
            'autorite' => $autorite,
            'filtres' => ['q' => $request->query('q'), 'etat' => $etat, 'type' => $type],
            'etats' => PolitiqueOrganisations::ETATS_CYCLE,
            'types' => PolitiqueOrganisations::TYPES_ORGANISATION,
        ]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, PolitiqueOrganisations::ACTION_INSCRIRE, null);

        return view('organisations.create', [
            'acteur' => $acteur,
            'decision' => $decision,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS',
            'types' => PolitiqueOrganisations::TYPES_ORGANISATION,
            'formes' => PolitiqueOrganisations::FORMES_ORGANISATION,
            'classifications' => PolitiqueOrganisations::CLASSIFICATIONS,
        ]);
    }

    public function store(Request $request, AccesOrganisations $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'type_organisation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_ORGANISATION)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'denomination_officielle' => ['required', 'string', 'max:500'],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
            'personnalite_juridique' => ['nullable', 'boolean'],
        ]);
        $donnees['personnalite_juridique'] = $request->boolean('personnalite_juridique');

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['inscription' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()
            ->route('console.organisations.show', $reference)
            ->with('succes', 'Organisation inscrite en PREPARATION.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $registre = $this->registre();
        $organisation = $registre->resoudreOrganisation($reference);
        abort_if($organisation === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless(
            $organisation['etat'] === 'ACTIVE' || $autorite || $organisation['proprietaire_reference'] === $acteur,
            404,
        );

        return view('organisations.show', [
            'organisation' => $organisation,
            'historique' => $registre->resoudreHistorique($reference),
            'identifiants' => $registre->resoudreIdentifiants($reference),
            'structure' => $registre->resoudreStructure($reference),
            'relations' => $registre->resoudreRelations($reference),
            'affiliations' => $autorite || $organisation['proprietaire_reference'] === $acteur
                ? $registre->resoudreAffiliationsOrganisation($reference) : [],
            'fonctions' => $registre->resoudreFonctions($reference),
            'autorite' => $autorite,
            'typesUnite' => PolitiqueOrganisations::TYPES_UNITE,
            'typesRelation' => PolitiqueOrganisations::TYPES_RELATION,
            'typesAffiliation' => PolitiqueOrganisations::TYPES_AFFILIATION,
            'typesFonction' => PolitiqueOrganisations::TYPES_FONCTION,
            'niveauxAssurance' => PolitiqueOrganisations::NIVEAUX_ASSURANCE,
            'classifications' => PolitiqueOrganisations::CLASSIFICATIONS,
            'typesIdentifiant' => PolitiqueOrganisations::TYPES_IDENTIFIANT_EXTERNE,
        ]);
    }

    public function activer(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'activer', 'Organisation activée.');
    }

    public function suspendre(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'suspendre', 'Organisation suspendue. Aucune nouvelle affiliation active n’y est possible.');
    }

    public function dissoudre(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'dissoudre', 'Organisation dissoute. Son historique reste consultable.');
    }

    public function retirer(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->retirer($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['organisation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Organisation retirée. Sa fiche reste consultable, sans suppression.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerIdentifiant(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'systeme_reference' => ['required', 'string', 'max:128'],
            'type_identifiant_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_IDENTIFIANT_EXTERNE)],
            'valeur_normalisee' => ['required', 'string', 'max:255'],
            'verifie' => ['nullable', 'boolean'],
        ]);
        $donnees['verifie'] = $request->boolean('verifie');
        $execution = $acces->declarerIdentifiant($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['identifiant' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Identifiant externe déclaré.')->with('preuve', $execution['corps']['preuve']);
    }

    public function creerUnite(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'unite_parente_reference' => ['nullable', 'string', 'max:64'],
            'type_unite_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_UNITE)],
            'nom' => ['required', 'string', 'max:255'],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
        ]);
        $execution = $acces->creerUnite($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['unite' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Unité créée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerRelation(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'organisation_cible_reference' => ['required', 'string', 'max:64'],
            'type_relation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_RELATION)],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
        ]);
        $donnees['organisation_source_reference'] = $reference;
        $execution = $acces->declarerRelation($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['relation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Relation interorganisationnelle déclarée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function proposerAffiliation(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'identite_reference' => ['required', 'string', 'max:64'],
            'type_affiliation_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_AFFILIATION)],
            'niveau_assurance_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::NIVEAUX_ASSURANCE)],
            'classification_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::CLASSIFICATIONS)],
        ]);
        $donnees['organisation_reference'] = $reference;
        $donnees['producteur_reference'] = $reference;
        $execution = $acces->proposerAffiliation($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['affiliation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Affiliation proposée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function activerAffiliation(Request $request, AccesOrganisations $acces, string $reference, string $affiliation): RedirectResponse
    {
        $execution = $acces->activerAffiliation($affiliation, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['affiliation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Affiliation activée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function suspendreAffiliation(Request $request, AccesOrganisations $acces, string $reference, string $affiliation): RedirectResponse
    {
        $execution = $acces->suspendreAffiliation($affiliation, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['affiliation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Affiliation suspendue. La représentation associée n’est plus opposable.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function fermerAffiliation(Request $request, AccesOrganisations $acces, string $reference, string $affiliation): RedirectResponse
    {
        $execution = $acces->fermerAffiliation($affiliation, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['affiliation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Affiliation fermée, sans suppression.')->with('preuve', $execution['corps']['preuve']);
    }

    public function creerFonction(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'type_fonction_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueOrganisations::TYPES_FONCTION)],
            'libelle' => ['required', 'string', 'max:500'],
            'mandat_fonction_reference' => ['nullable', 'string', 'max:64'],
        ]);
        $execution = $acces->creerFonction($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['fonction' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.organisations.show', $reference)
            ->with('succes', 'Fonction interne créée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function verifierRepresentation(Request $request, AccesOrganisations $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['identite_reference' => ['required', 'string', 'max:64']]);
        $execution = $acces->verifierRepresentation($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['representation' => $this->motif($execution['corps'])]);
        }
        $resultat = $execution['corps'];
        $message = ($resultat['opposable'] ?? false)
            ? "Représentation opposable, mandat {$resultat['mandat']}."
            : 'Représentation NON opposable : ' . implode(', ', $resultat['motifs'] ?? []);

        return redirect()->route('console.organisations.show', $reference)->with('succes', $message);
    }

    private function transition(
        Request $request,
        AccesOrganisations $acces,
        string $reference,
        string $methode,
        string $messageSucces,
    ): RedirectResponse {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->{$methode}($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['organisation' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.organisations.show', $reference)
            ->with('succes', $messageSucces)
            ->with('preuve', $execution['corps']['preuve']);
    }

    private function registre(): RegistreOrganisations
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
        try {
            $ctr02 = new Ctr02($index);
        } catch (\Throwable) {
            $ctr02 = null;
        }

        return new RegistreOrganisations($index, $registreIdentites, OrganisationsMagasin::connecter(), $ctr01, $ctr02);
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
