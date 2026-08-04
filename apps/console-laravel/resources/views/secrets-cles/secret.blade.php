@extends('layouts.console')

@section('title', $secret['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.secrets-cles.index') }}">Secrets &amp; clés</a>
    <span aria-hidden="true">/</span>
    <span>{{ $secret['reference'] }}</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $secret['type_secret'] }}</p>
        <h1 class="page-title technical-reference">{{ $secret['reference'] }}</h1>
        <p class="page-subtitle">{{ $secret['nom'] }} — {{ $secret['finalite_reference'] }}</p>
    </div>
    <span class="status status--success">{{ $secret['classification_reference'] }}</span>
</header>

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Gouvernance</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Propriétaire</dt><dd class="technical-reference">{{ $secret['proprietaire_reference'] }}</dd></div>
                <div class="summary-row"><dt>Source</dt><dd class="technical-reference">{{ $secret['source_reference'] }}</dd></div>
                <div class="summary-row"><dt>Realm</dt><dd class="technical-reference">{{ $secret['realm_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Environnement</dt><dd>{{ $secret['environnement_reference'] }}</dd></div>
                <div class="summary-row"><dt>Rotation requise</dt><dd>{{ $secret['rotation_requise'] ? 'oui, tous les ' . $secret['duree_rotation_jours'] . ' jours' : 'non' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card span-12">
        <div class="card__header"><h2 class="card__title">Versions ({{ count($versions) }})</h2></div>
        <div class="card__body">
            <p class="field-help">Le handle du fournisseur n’est jamais affiché ici — seule la couche de résolution interne y accède.</p>
            <table class="data-table">
                <thead><tr><th>Version</th><th>État</th><th>Fournisseur</th><th>Algorithme</th><th>Créée le</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($versions as $version)
                        <tr>
                            <td class="technical-reference">{{ $version['version'] }}</td>
                            <td>#{{ $version['id'] }}</td>
                            <td class="technical-reference">{{ $version['fournisseur_reference'] }}</td>
                            <td>{{ $version['algorithme_reference'] ?? '—' }}</td>
                            <td>{{ $version['cree_le'] }}</td>
                            <td>
                                <div class="action-bar">
                                    <form method="POST" action="{{ route('console.secrets-cles.versions.suspendre', [$secret['reference'], $version['id']]) }}" onsubmit="return confirm('Suspendre cette version ?');">
                                        @csrf<input type="hidden" name="motif" value="suspension console"><button class="button" type="submit">Suspendre</button>
                                    </form>
                                    <form method="POST" action="{{ route('console.secrets-cles.versions.revoquer', [$secret['reference'], $version['id']]) }}" onsubmit="return confirm('Révoquer irréversiblement cette version ?');">
                                        @csrf<input type="hidden" name="motif" value="révocation console">
                                        <button class="button button--danger" type="submit">Révoquer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucune version déclarée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Usages ({{ count($usages) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Opération</th><th>Mode</th><th>Consommateur</th></tr></thead>
                <tbody>
                    @forelse($usages as $usage)
                        <tr>
                            <td>{{ $usage['operation_reference'] }}</td>
                            <td>{{ $usage['mode_usage'] }}</td>
                            <td class="technical-reference">{{ $usage['capacite_reference'] ?? $usage['produit_reference'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Aucun usage déclaré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Rotations ({{ count($rotations) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Référence</th><th>Stratégie</th><th>État</th></tr></thead>
                <tbody>
                    @forelse($rotations as $rotation)
                        <tr>
                            <td class="technical-reference">{{ $rotation['reference'] }}</td>
                            <td>{{ $rotation['strategie'] }}</td>
                            <td>{{ $rotation['etat'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Aucune rotation planifiée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card span-12">
        <div class="card__header"><h2 class="card__title">Dépendances historiques ({{ count($dependances) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Type</th><th>Ressource</th><th>Ouverte depuis</th><th>Fermée le</th></tr></thead>
                <tbody>
                    @forelse($dependances as $dependance)
                        <tr>
                            <td>{{ $dependance['type_dependance'] }}</td>
                            <td class="technical-reference">{{ $dependance['ressource_reference'] }}</td>
                            <td>{{ $dependance['date_debut'] }}</td>
                            <td>{{ $dependance['date_fin'] ?? '— (bloque une destruction)' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Aucune dépendance déclarée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
