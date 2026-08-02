<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Contrats\AccesContrats;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration du registre des contrats (CAP-CORE-009).
 *
 * Les lectures interrogent directement le registre ; toute écriture passe par
 * `AccesContrats`, le même cas d'usage gouverné que l'API v1.
 */
final class ContratConsoleController
{
    public function index(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;

        $tous = $this->registre()->listerContrats();
        $contrats = array_values(array_filter(
            $tous,
            static fn (array $c): bool => $c['version_active'] !== null
                || $autorite
                || $c['proprietaire_reference'] === $acteur,
        ));

        return view('contrats.index', ['contrats' => $contrats, 'total' => count($contrats), 'autorite' => $autorite]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $decision = (new Ctr03(PolitiquesMagasin::connecter()))
            ->autoriser($acteur, PolitiqueContrats::ACTION_INSCRIRE, null);

        return view('contrats.create', [
            'acteur' => $acteur, 'decision' => $decision,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS',
            'types' => PolitiqueContrats::TYPES_CONTRAT,
        ]);
    }

    public function store(Request $request, AccesContrats $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'nom' => ['required', 'string', 'max:255'],
            'type_contrat' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::TYPES_CONTRAT)],
            'finalite_reference' => ['required', 'string', 'max:500'],
            'producteur_capacite_reference' => ['nullable', 'string', 'max:64'],
            'producteur_produit_reference' => ['nullable', 'string', 'max:64'],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['inscription' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()->route('console.contrats.show', $reference)
            ->with('succes', 'Contrat inscrit.')->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $registre = $this->registre();
        $contrat = $registre->resoudreContrat($reference);
        abort_if($contrat === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless($contrat['version_active'] !== null || $autorite || $contrat['proprietaire_reference'] === $acteur, 404);

        return view('contrats.show', [
            'contrat' => $contrat,
            'versions' => $registre->listerVersions($reference),
            'historique' => $registre->resoudreHistorique($reference),
            'autorite' => $autorite,
        ]);
    }

    public function creerVersion(Request $request, AccesContrats $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'compatibilite_annoncee' => ['nullable', 'string', 'in:' . implode(',', PolitiqueContrats::COMPATIBILITES_ANNONCEES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->creerVersion($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.version', [$reference, $donnees['version']])
            ->with('succes', 'Version créée en BROUILLON.')->with('preuve', $execution['corps']['preuve']);
    }

    public function versionShow(Request $request, string $reference, string $version): View
    {
        $registre = $this->registre();
        $contrat = $registre->resoudreContrat($reference);
        abort_if($contrat === null, 404);
        $v = $registre->resoudreVersion($reference, $version);
        abort_if($v === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless($autorite || $contrat['proprietaire_reference'] === $acteur, 404);

        return view('contrats.version', [
            'contrat' => $contrat, 'version' => $v, 'autorite' => $autorite,
            'compatibilites' => $registre->resoudreCompatibilite($reference, $version),
            'conformites' => $registre->resoudreConformite($reference, $version),
            'rolesPartie' => PolitiqueContrats::ROLES_PARTIE,
            'typesPartie' => PolitiqueContrats::TYPES_PARTIE,
            'typesOperation' => PolitiqueContrats::TYPES_OPERATION,
        ]);
    }

    public function declarerPartie(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::ROLES_PARTIE)],
            'partie_type' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::TYPES_PARTIE)],
            'partie_reference' => ['required', 'string', 'max:64'],
        ]);

        $execution = $acces->declarerPartie($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['partie' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.version', [$reference, $version])
            ->with('succes', 'Partie déclarée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerOperation(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'reference_operation' => ['required', 'string', 'max:128'],
            'type_operation' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::TYPES_OPERATION)],
            'methode_http' => ['nullable', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'chemin_http' => ['nullable', 'string', 'max:256'],
            'idempotente' => ['nullable', 'boolean'],
            'audit_obligatoire' => ['nullable', 'boolean'],
        ]);

        $execution = $acces->declarerOperation($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['operation' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.version', [$reference, $version])
            ->with('succes', 'Opération déclarée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerSchema(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'operation_reference' => ['nullable', 'string', 'max:128'],
            'sens' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::SENS_SCHEMA)],
            'format' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::FORMATS_SCHEMA)],
            'contenu' => ['nullable', 'string', 'max:20000'],
        ]);

        $execution = $acces->declarerSchema($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['schema' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.version', [$reference, $version])
            ->with('succes', 'Schéma déclaré.')->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerErreur(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:128'],
            'statut_http' => ['nullable', 'integer', 'min:100', 'max:599'],
            'retentable' => ['nullable', 'boolean'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $execution = $acces->declarerErreur($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['erreur' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.version', [$reference, $version])
            ->with('succes', 'Erreur déclarée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function soumettre(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $execution = $acces->soumettreVersion($reference, $version, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.version', [$reference, $version])
            ->with('succes', 'Version soumise à validation. Elle est désormais immuable.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function analyser(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $execution = $acces->analyserCompatibilite($reference, $version, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['analyse' => $this->motif($execution['corps'])]);
        }
        $resultat = $execution['corps']['resultat']['resultat'] ?? 'INCONNU';

        return redirect()->route('console.contrats.version', [$reference, $version])
            ->with('succes', "Analyse de compatibilité : {$resultat}.")->with('preuve', $execution['corps']['preuve']);
    }

    public function activer(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'motif' => ['nullable', 'string', 'max:500'],
            'plan_migration' => ['nullable', 'string', 'max:2000'],
            'date_limite_migration' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $execution = $acces->activerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withInput()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.show', $reference)
            ->with('succes', 'Version activée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function deprecier(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->deprecierVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.show', $reference)
            ->with('succes', 'Version dépréciée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function suspendre(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendreVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.show', $reference)
            ->with('succes', 'Version suspendue. Elle ne permet plus rien immédiatement.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function retirer(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.show', $reference)
            ->with('succes', 'Version retirée. Son historique reste consultable.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function enregistrerConformite(Request $request, AccesContrats $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'resultat' => ['required', 'string', 'in:' . implode(',', PolitiqueContrats::RESULTATS_CONFORMITE)],
            'artefact_reference' => ['required', 'string', 'max:256'],
            'resume' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->enregistrerConformite($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['conformite' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.contrats.version', [$reference, $version])
            ->with('succes', 'Conformité enregistrée.')->with('preuve', $execution['corps']['preuve']);
    }

    private function registre(): RegistreContrats
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

        return new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), new Ctr01($index, $registreIdentites));
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
