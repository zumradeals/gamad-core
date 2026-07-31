<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Identites\InscrireIdentite;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class IdentiteConsoleController
{
    private function ctr01(): Ctr01
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

        return new Ctr01($index, Magasin::connecter());
    }

    public function index(Request $request): View
    {
        $type = trim((string) $request->query('type', ''));
        $etat = trim((string) $request->query('etat', ''));
        $recherche = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');
        $types = ['personne', 'organisation', 'produit', 'realm', 'agent', 'service'];
        $inventaire = $this->ctr01()->resoudreInventaire(
            in_array($type, $types, true) ? $type : null,
        );

        $identites = array_values(array_filter(
            $inventaire,
            static function (array $identite) use ($etat, $recherche): bool {
                if ($etat !== '' && (string) ($identite['etat'] ?? '') !== $etat) {
                    return false;
                }
                if ($recherche === '') {
                    return true;
                }
                $texte = mb_strtolower(
                    (string) $identite['libelle'].' '.(string) $identite['reference'],
                    'UTF-8',
                );

                return str_contains($texte, $recherche);
            },
        ));

        return view('identites.index', [
            'identites' => $identites,
            'total' => count($inventaire),
            'filtres' => ['q' => $request->query('q'), 'type' => $type, 'etat' => $etat],
        ]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $index = Db::connect();
        $decision = (new Ctr03($index))->autoriser($acteur, 'inscrire une identité', 'personne');
        $mandat = (new Ctr02($index))->resoudreMandat(null, $acteur, gmdate('Y-m-d'));
        $mandatActif = is_array($mandat)
            && str_starts_with((string) ($mandat['etat'] ?? ''), 'ACTIF');

        return view('identites.create', [
            'acteur' => $acteur,
            'decision' => $decision,
            'mandat' => $mandat,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS' && $mandatActif,
        ]);
    }

    public function store(Request $request, InscrireIdentite $inscrire): RedirectResponse
    {
        $donnees = $request->validate([
            'type' => ['required', 'string', 'in:personne,organisation,produit,realm,agent,service'],
            'libelle' => ['required', 'string', 'max:256'],
            'classification' => ['required', 'string', 'in:PUBLIC_ECOSYSTEME,INTERNE,CONFIDENTIEL,RESTREINT,SECRET_CORE'],
            'provisoire' => ['nullable', 'boolean'],
        ]);
        $donnees['canal'] = in_array($donnees['type'], ['agent', 'service', 'produit', 'realm'], true)
            ? 'CREATION_TECHNIQUE'
            : 'AUTORITE';
        $donnees['provisoire'] = $donnees['provisoire'] ?? false;

        $execution = $inscrire->executer(
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
        );
        if ($execution['statut'] !== 201) {
            $message = $execution['corps']['message']
                ?? $execution['corps']['resultat']['detail']
                ?? $execution['corps']['decision']['motif']
                ?? 'Le Core a refusé cette inscription.';

            return back()->withInput()->withErrors(['inscription' => $message]);
        }

        $reference = (string) $execution['corps']['identite']['reference'];

        return redirect()
            ->route('console.identites.show', $reference)
            ->with('succes', 'Identité inscrite avec succès.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $ctr01 = $this->ctr01();
        $identite = $ctr01->resoudreIdentite($reference);
        abort_if($identite === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $assurance = in_array($acteur, [$reference, PolitiqueInscription::AUTORITE_INSCRIPTION], true)
            ? $ctr01->resoudreAssurance($reference)
            : null;

        return view('identites.show', [
            'identite' => $identite,
            'regime' => $ctr01->resoudreRegimeVerite($reference),
            'assurance' => $assurance,
        ]);
    }
}
