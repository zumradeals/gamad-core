@extends('layouts.console')

@section('title', 'Secrets & clés')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Secrets &amp; clés</p>
        <h1 class="page-title">Secrets &amp; clés</h1>
        <p class="page-subtitle">
            Registre de gouvernance (CAP-CORE-016) : références, versions, usages et rotations des secrets et
            clés du Core. Cet écran n’affiche jamais une valeur secrète — seulement des métadonnées.
        </p>
    </div>
    <div>
        <a class="button button--primary" href="{{ route('console.parametres.verification.index') }}">Configurer email &amp; SMS</a>
    </div>
</header>

@unless($autorise)
    <div class="form-error" role="alert">
        <span aria-hidden="true">!</span>
        <span>Cet écran est fermé pour cette session. {{ $motif }}</span>
    </div>
@else
    @php($diag = $diagnostic['registre'] ?? [])
    <section class="hero-status" aria-labelledby="etat-secrets">
        <div>
            <p class="eyebrow">État du registre</p>
            <h2 class="hero-status__title" id="etat-secrets">
                {{ ($diag['coherent'] ?? false) ? 'Le registre est cohérent.' : 'Un doublon de version active a été détecté.' }}
            </h2>
            <p class="hero-status__copy">
                {{ $diag['ressources'] ?? 0 }} référence(s) gouvernée(s),
                {{ $diag['versions_actives_ecriture'] ?? 0 }} version(s) active(s) en écriture.
            </p>
        </div>
        <div class="health-orbit">
            <div class="health-orbit__ring {{ ($diag['coherent'] ?? false) ? '' : 'health-orbit__ring--danger' }}">
                <span>
                    <span class="health-orbit__state">{{ ($diag['coherent'] ?? false) ? 'Cohérent' : 'À vérifier' }}</span>
                    <span class="health-orbit__label">{{ $diag['compromissions_ouvertes'] ?? 0 }} compromission(s) ouverte(s)</span>
                </span>
            </div>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="card span-6">
            <div class="card__header"><h2 class="card__title">Rétention</h2></div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row"><dt>Versions compromises actives</dt><dd>{{ $diag['versions_compromises_actives'] ?? 0 }}</dd></div>
                    <div class="summary-row"><dt>Fournisseurs dégradés/suspendus</dt><dd>{{ $diag['fournisseurs_degrades'] ?? 0 }}</dd></div>
                    <div class="summary-row"><dt>Sur fournisseur de transition</dt><dd>{{ $diag['references_transition'] ?? 0 }}</dd></div>
                </dl>
                <p class="field-help" style="margin-top:12px">
                    Vérification des fournisseurs : <span class="technical-reference">core:secrets:fournisseurs-verifier</span>.
                </p>
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:20px">
        <div class="card__header"><h2 class="card__title">Références ({{ count($secrets) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead>
                    <tr><th>Référence</th><th>Nom</th><th>Type</th><th>Environnement</th><th>Classification</th></tr>
                </thead>
                <tbody>
                    @forelse($secrets as $secret)
                        <tr>
                            <td><a href="{{ route('console.secrets-cles.show', $secret['reference']) }}" class="technical-reference">{{ $secret['reference'] }}</a></td>
                            <td>{{ $secret['nom'] }}</td>
                            <td>{{ $secret['type_secret'] }}</td>
                            <td>{{ $secret['environnement_reference'] }}</td>
                            <td>{{ $secret['classification_reference'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune référence inscrite.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endunless
@endsection
