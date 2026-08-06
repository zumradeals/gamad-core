@extends('layouts.console')

@section('title', 'Matching')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Matching</p>
        <h1 class="page-title">Moteur de Matching</h1>
        <p class="page-subtitle">
            Registre de gouvernance (CAP-CORE-021) : contextes, demandes, exécutions, résultats, segments et
            activations. Lecture seule — aucune écriture n’est exposée depuis cette console.
        </p>
    </div>
</header>

@unless($autorise)
    <div class="form-error" role="alert">
        <span aria-hidden="true">!</span>
        <span>Cet écran est fermé pour cette session. {{ $motif }}</span>
    </div>
@else
    <section class="card">
        <div class="card__header"><h2 class="card__title">Contextes ({{ count($contextes) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Référence</th><th>Code</th><th>État</th><th>Score</th><th>Segment</th><th>Activation</th></tr></thead>
                <tbody>
                    @forelse($contextes as $contexte)
                        <tr>
                            <td><a href="{{ route('console.matching.contextes.show', $contexte['reference']) }}" class="technical-reference">{{ $contexte['reference'] }}</a></td>
                            <td>{{ $contexte['code_canonique'] }}</td>
                            <td><span class="status {{ $contexte['etat'] === 'ACTIF' ? 'status--success' : '' }}">{{ $contexte['etat'] }}</span></td>
                            <td>{{ $contexte['score_autorise'] ? 'Autorisé' : '—' }}</td>
                            <td>{{ $contexte['segment_autorise'] ? 'Autorisé' : '—' }}</td>
                            <td>{{ $contexte['activation_autorisee'] ? 'Autorisée' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun contexte inscrit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top:20px">
        <div class="card__header"><h2 class="card__title">Demandes récentes ({{ count($demandes) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Référence</th><th>Consommateur</th><th>Contexte</th><th>Mode</th><th>État</th><th>Créée le</th></tr></thead>
                <tbody>
                    @forelse($demandes as $demande)
                        <tr>
                            <td><a href="{{ route('console.matching.demandes.show', $demande['reference']) }}" class="technical-reference">{{ $demande['reference'] }}</a></td>
                            <td class="technical-reference">{{ $demande['consommateur_produit'] }}</td>
                            <td class="technical-reference">{{ $demande['contexte_reference'] }}</td>
                            <td>{{ $demande['mode_resultat'] }}</td>
                            <td><span class="status {{ $demande['etat'] === 'TERMINEE' ? 'status--success' : '' }}">{{ $demande['etat'] }}</span></td>
                            <td>{{ $demande['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucune demande soumise.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endunless
@endsection
