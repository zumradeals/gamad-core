@extends('layouts.console')

@section('title', $segment['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.matching.index') }}">Matching</a>
    <span aria-hidden="true">/</span>
    <span>{{ $segment['reference'] }}</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Segment protégé</p>
        <h1 class="page-title technical-reference">{{ $segment['reference'] }}</h1>
        <p class="page-subtitle">{{ $segment['consommateur_produit'] }} · {{ $segment['finalite_reference'] }} · realm {{ $segment['realm_reference'] }}</p>
    </div>
    <span class="status {{ $segment['etat'] === 'ACTIF' ? 'status--success' : '' }}">{{ $segment['etat'] }}</span>
</header>

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Population et gouvernance</h2></div>
        <div class="card__body">
            <p class="field-help">Aucun membre n’est jamais listé par cette console ni par l’API — seule une vérification d’appartenance par jeton individuel est possible (doc 01 §4, doc 04 §6).</p>
            <dl class="summary-list">
                <div class="summary-row"><dt>Population</dt><dd>{{ $segment['population_nombre'] }}</dd></div>
                <div class="summary-row"><dt>Export brut</dt><dd>{{ $segment['export_brut_autorise'] ? 'Autorisé' : 'Interdit' }}</dd></div>
                <div class="summary-row"><dt>Activation</dt><dd>{{ $segment['activation_autorisee'] ? 'Autorisée' : 'Non autorisée' }}</dd></div>
                <div class="summary-row"><dt>Classification</dt><dd>{{ $segment['classification'] }}</dd></div>
                <div class="summary-row"><dt>Créé le</dt><dd>{{ $segment['cree_le'] }}</dd></div>
                <div class="summary-row"><dt>Expire le</dt><dd>{{ $segment['expire_le'] }}</dd></div>
            </dl>
        </div>
    </section>
</div>
@endsection
