<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Evenements\AccesEvenements;
use Gamad\JournalEvenements\PolitiqueEvenements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Console d'administration du journal des événements (CAP-CORE-014,
 * fiche partie 4 §9).
 *
 * Toute lecture et toute écriture passent par `AccesEvenements`, le même cas
 * d'usage gouverné que l'API v1 — contrairement aux consoles Realms/
 * Organisations/Produits qui lisent directement leur registre, CAP-CORE-014
 * soumet ses lectures à `POL-EVENEMENTS-V1` (partie 4 §4, confidentialité
 * par realm). Seul le tableau de bord fait exception pour ses agrégats de
 * santé, conformément à la fiche partie 5 §12 : « l'administration peut voir
 * la santé sans voir toutes les charges ».
 */
final class EvenementConsoleController
{
    public function index(Request $request, AccesEvenements $acces): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->diagnostiquer($acteur);

        return view('evenements.tableau-de-bord', [
            'autorise' => $execution['statut'] === 200,
            'diagnostic' => $execution['corps'],
            'motif' => $execution['statut'] === 200 ? null : $this->motif($execution['corps']),
        ]);
    }

    public function showEvenement(Request $request, AccesEvenements $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudre($reference, $acteur);
        abort_if($execution['statut'] === 404, 404);
        abort_if($execution['statut'] === 403, 403, $this->motif($execution['corps']));

        $charge = $acces->resoudreCharge($reference, $acteur);

        return view('evenements.evenement', [
            'evenement' => $execution['corps']['evenement'],
            'charge' => $charge['statut'] === 200 ? $charge['corps'] : null,
            'chargeRefusee' => $charge['statut'] === 403,
        ]);
    }

    public function showAbonnement(Request $request, AccesEvenements $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudreAbonnement($reference, $acteur);
        abort_if(in_array($execution['statut'], [403, 404], true), $execution['statut']);

        $declarations = $acces->resoudreDeclarations($reference, $acteur);
        $retard = $acces->resoudreRetard($reference, $acteur);
        $curseur = $acces->resoudreCurseur($reference, $acteur);
        $lettresMortes = $acces->listerLettresMortes($reference, $acteur);
        $rejeux = $acces->listerRejeux($reference, $acteur);

        return view('evenements.abonnement', [
            'abonnement' => $execution['corps']['abonnement'],
            'declarations' => $declarations['statut'] === 200 ? $declarations['corps'] : ['types' => [], 'producteurs' => [], 'realms' => []],
            'retard' => $retard['statut'] === 200 ? $retard['corps'] : null,
            'curseur' => $curseur['statut'] === 200 ? $curseur['corps'] : null,
            'lettresMortes' => $lettresMortes['statut'] === 200 ? $lettresMortes['corps']['lettres_mortes'] : [],
            'rejeux' => $rejeux['statut'] === 200 ? $rejeux['corps']['rejeux'] : [],
            'modesLivraison' => PolitiqueEvenements::MODES_LIVRAISON,
            'porteesRealm' => PolitiqueEvenements::PORTEES_REALM,
        ]);
    }

    public function activerAbonnement(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $execution = $acces->activerAbonnement($reference, [], (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['abonnement' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.abonnements.show', $reference)
            ->with('succes', 'Abonnement activé.')->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function suspendreAbonnement(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $execution = $acces->suspendreAbonnement($reference, [], (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['abonnement' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.abonnements.show', $reference)
            ->with('succes', 'Abonnement suspendu. Aucune nouvelle livraison tant qu’il n’est pas réactivé.')
            ->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function retirerAbonnement(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $execution = $acces->retirerAbonnement($reference, [], (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['abonnement' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.abonnements.show', $reference)
            ->with('succes', 'Abonnement retiré, définitivement.')->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function ajouterType(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'contrat_reference' => ['required', 'string', 'max:64'],
            'type_evenement' => ['required', 'string', 'max:128'],
            'version_contrainte' => ['nullable', 'string', 'max:32'],
        ]);
        $execution = $acces->ajouterType($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['type' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.abonnements.show', $reference)
            ->with('succes', 'Type ajouté à l’abonnement.')->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function ajouterProducteur(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['producteur_reference' => ['required', 'string', 'max:64']]);
        $execution = $acces->ajouterProducteur($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['producteur' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.abonnements.show', $reference)
            ->with('succes', 'Producteur ajouté à l’abonnement.')->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function ajouterRealm(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'realm_reference' => ['required', 'string', 'max:64'],
            'portee' => ['nullable', 'string', 'in:' . implode(',', PolitiqueEvenements::PORTEES_REALM)],
        ]);
        $execution = $acces->ajouterRealm($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['realm' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.abonnements.show', $reference)
            ->with('succes', 'Realm ajouté à l’abonnement.')->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function lettresMortesIndex(Request $request, AccesEvenements $acces): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $abonnement = $request->query('abonnement');
        $execution = $acces->listerLettresMortes($abonnement === null ? null : (string) $abonnement, $acteur);

        return view('evenements.lettres-mortes-index', [
            'autorise' => $execution['statut'] === 200,
            'lettresMortes' => $execution['statut'] === 200 ? $execution['corps']['lettres_mortes'] : [],
            'motif' => $execution['statut'] === 200 ? null : $this->motif($execution['corps']),
            'filtreAbonnement' => (string) ($abonnement ?? ''),
        ]);
    }

    public function lettresMortesShow(Request $request, AccesEvenements $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudreLettreMorte($reference, $acteur);
        abort_if(in_array($execution['statut'], [403, 404], true), $execution['statut']);

        return view('evenements.lettre-morte', ['lettreMorte' => $execution['corps']['lettre_morte']]);
    }

    public function relancerLettreMorte(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->relancerLettreMorte($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['lettre_morte' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.lettres-mortes.show', $reference)
            ->with('succes', 'Lettre morte relancée ; la livraison redevient disponible.')
            ->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function cloturerLettreMorte(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->cloturerLettreMorte($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['lettre_morte' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.lettres-mortes.show', $reference)
            ->with('succes', 'Lettre morte clôturée.')->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function rejeuxIndex(Request $request, AccesEvenements $acces): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $abonnement = $request->query('abonnement');
        $execution = $acces->listerRejeux($abonnement === null ? null : (string) $abonnement, $acteur);

        return view('evenements.rejeux-index', [
            'autorise' => $execution['statut'] === 200,
            'rejeux' => $execution['statut'] === 200 ? $execution['corps']['rejeux'] : [],
            'motif' => $execution['statut'] === 200 ? null : $this->motif($execution['corps']),
            'filtreAbonnement' => (string) ($abonnement ?? ''),
        ]);
    }

    public function rejeuxCreate(Request $request): View
    {
        return view('evenements.rejeu-nouveau', ['abonnementPrerempli' => (string) $request->query('abonnement', '')]);
    }

    public function rejeuxStore(Request $request, AccesEvenements $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'abonnement_reference' => ['required', 'string', 'max:64'],
            'motif' => ['required', 'string', 'max:500'],
            'sequence_debut' => ['nullable', 'integer', 'min:0'],
            'sequence_fin' => ['nullable', 'integer', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);
        $abonnement = $donnees['abonnement_reference'];
        unset($donnees['abonnement_reference']);
        $execution = $acces->demanderRejeu($abonnement, $donnees, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['rejeu' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()->route('console.rejeux.show', $reference)
            ->with('succes', 'Rejeu demandé, en DEMANDEE. Le volume estimé est affiché sur la fiche.')
            ->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function rejeuxShow(Request $request, AccesEvenements $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudreRejeu($reference, $acteur);
        abort_if(in_array($execution['statut'], [403, 404], true), $execution['statut']);

        return view('evenements.rejeu', ['rejeu' => $execution['corps']['rejeu']]);
    }

    public function validerRejeu(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $execution = $acces->validerRejeu($reference, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['rejeu' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.rejeux.show', $reference)
            ->with('succes', 'Rejeu validé. L’exécution reste une opération de fond (core:evenements:traiter-rejeux).')
            ->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    public function annulerRejeu(Request $request, AccesEvenements $acces, string $reference): RedirectResponse
    {
        $execution = $acces->annulerRejeu($reference, (string) $request->attributes->get('gamad_entite'), $this->correlation($request));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['rejeu' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.rejeux.show', $reference)
            ->with('succes', 'Rejeu annulé avant exécution.')->with('preuve', $execution['corps']['preuve'] ?? null);
    }

    private function correlation(Request $request): ?string
    {
        $correlation = $request->attributes->get('gamad_correlation');

        return $correlation === null ? null : (string) $correlation;
    }

    /** @param array<string,mixed> $corps */
    private function motif(array $corps): string
    {
        return (string) ($corps['resultat']['detail']
            ?? $corps['message']
            ?? $corps['decision']['motif']
            ?? $corps['erreur']
            ?? 'Le Core a refusé cette opération.');
    }
}
