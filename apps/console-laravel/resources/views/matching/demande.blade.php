@extends('layouts.console')

@section('title', $demande['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.matching.index') }}">Matching</a>
    <span aria-hidden="true">/</span>
    <span>{{ $demande['reference'] }}</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $demande['mode_resultat'] }}</p>
        <h1 class="page-title technical-reference">{{ $demande['reference'] }}</h1>
        <p class="page-subtitle">{{ $demande['consommateur_produit'] }} · {{ $demande['finalite_reference'] }} · realm {{ $demande['realm_reference'] }}</p>
    </div>
    <span class="status {{ $demande['etat'] === 'TERMINEE' ? 'status--success' : '' }}">{{ $demande['etat'] }}</span>
</header>

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Gouvernance</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Contexte</dt><dd><a href="{{ route('console.matching.contextes.show', $demande['contexte_reference']) }}" class="technical-reference">{{ $demande['contexte_reference'] }}</a></dd></div>
                <div class="summary-row"><dt>Profil d’exécution</dt><dd class="technical-reference">{{ $demande['profil_execution_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Politique</dt><dd class="technical-reference">{{ $demande['politique_reference'] }} · {{ $demande['politique_version'] }}</dd></div>
                <div class="summary-row"><dt>Contrat</dt><dd class="technical-reference">{{ $demande['contrat_reference'] }} · {{ $demande['contrat_version'] }}</dd></div>
                <div class="summary-row"><dt>Classification</dt><dd>{{ $demande['classification'] }}</dd></div>
                <div class="summary-row"><dt>Soumise par</dt><dd class="technical-reference">{{ $demande['soumise_par'] }}</dd></div>
                <div class="summary-row"><dt>Créée le</dt><dd>{{ $demande['created_at'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Historique ({{ count($historique) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>État</th><th>Effet</th><th>Motif</th><th>Acteur</th></tr></thead>
                <tbody>
                    @forelse($historique as $etape)
                        <tr>
                            <td>{{ $etape['etat_reference'] }}</td>
                            <td>{{ $etape['date_effet'] }}</td>
                            <td>{{ $etape['motif_detail'] ?? '—' }}</td>
                            <td class="technical-reference">{{ $etape['acteur_reference'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Aucune transition enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Exécutions ({{ count($executions) }})</h2></div>
        <div class="card__body">
            <p class="field-help">Une exécution terminée est immuable ; une reprise crée une nouvelle exécution liée, jamais une réécriture.</p>
            <table class="data-table">
                <thead><tr><th>Référence</th><th>État</th><th>Candidats</th><th>Résultats</th><th>Démarrée le</th></tr></thead>
                <tbody>
                    @forelse($executions as $execution)
                        <tr>
                            <td class="technical-reference">{{ $execution['reference'] }}</td>
                            <td><span class="status {{ $execution['etat'] === 'TERMINEE' ? 'status--success' : '' }}">{{ $execution['etat'] }}</span></td>
                            <td>{{ $execution['candidats_evalues'] }}/{{ $execution['candidats_total'] }}</td>
                            <td>{{ $execution['resultats_total'] }}</td>
                            <td>{{ $execution['demarre_le'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aucune exécution.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card span-12">
        <div class="card__header">
            <h2 class="card__title">
                Résultats de la dernière exécution ({{ count($resultats) }})
                @if($derniereExecution) <span class="technical-reference">{{ $derniereExecution['reference'] }}</span> @endif
            </h2>
        </div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Rang</th><th>Candidat</th><th>Classe</th><th>Pertinence</th><th>Confiance</th><th>Explication</th></tr></thead>
                <tbody>
                    @forelse($resultats as $resultat)
                        <tr>
                            <td>{{ $resultat['rang'] ?? '—' }}</td>
                            <td class="technical-reference">{{ $resultat['candidat_reference'] }}</td>
                            <td>{{ $resultat['classe_resultat'] }}</td>
                            <td>{{ $resultat['pertinence'] ?? '—' }}</td>
                            <td>{{ $resultat['confiance'] ?? '—' }}</td>
                            <td>
                                @php($explication = $explications[$resultat['reference']] ?? null)
                                @if($explication)
                                    <details>
                                        <summary>Voir</summary>
                                        <p class="field-help">
                                            Favorables : {{ implode(', ', $explication['facteurs_favorables']) ?: '—' }}<br>
                                            Défavorables : {{ implode(', ', $explication['facteurs_defavorables']) ?: '—' }}<br>
                                            Non établis : {{ implode(', ', $explication['facteurs_non_etablis']) ?: '—' }}<br>
                                            Obligations : {{ implode(', ', $explication['obligations']) ?: '—' }}<br>
                                            Expire le : {{ $explication['expire_le'] }}
                                        </p>
                                    </details>
                                @else
                                    <span class="field-help">Expirée ou indisponible.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun résultat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if(count($segments) > 0)
        <section class="card span-12">
            <div class="card__header"><h2 class="card__title">Segments ({{ count($segments) }})</h2></div>
            <div class="card__body">
                <p class="field-help">Aucun membre n’est jamais listé ici — voir la fiche du segment pour son état et son expiration.</p>
                <table class="data-table">
                    <thead><tr><th>Référence</th><th>État</th><th>Population</th><th>Expire le</th></tr></thead>
                    <tbody>
                        @foreach($segments as $segment)
                            <tr>
                                <td><a href="{{ route('console.matching.segments.show', $segment['reference']) }}" class="technical-reference">{{ $segment['reference'] }}</a></td>
                                <td><span class="status {{ $segment['etat'] === 'ACTIF' ? 'status--success' : '' }}">{{ $segment['etat'] }}</span></td>
                                <td>{{ $segment['population_nombre'] }}</td>
                                <td>{{ $segment['expire_le'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
