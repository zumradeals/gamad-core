@extends('layouts.console')

@section('title', $contexte['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.matching.index') }}">Matching</a>
    <span aria-hidden="true">/</span>
    <span>{{ $contexte['reference'] }}</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $contexte['code_canonique'] }}</p>
        <h1 class="page-title technical-reference">{{ $contexte['reference'] }}</h1>
        <p class="page-subtitle">{{ $contexte['nom'] }} · {{ $contexte['finalite'] }}</p>
    </div>
    <span class="status {{ $contexte['etat'] === 'ACTIF' ? 'status--success' : '' }}">{{ $contexte['etat'] }}</span>
</header>

<div class="detail-grid">
    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Autorisations</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Score de pertinence</dt><dd>{{ $contexte['score_autorise'] ? 'Autorisé' : 'Non autorisé' }}</dd></div>
                <div class="summary-row"><dt>Segment protégé</dt><dd>{{ $contexte['segment_autorise'] ? 'Autorisé' : 'Non autorisé' }}</dd></div>
                <div class="summary-row"><dt>Activation</dt><dd>{{ $contexte['activation_autorisee'] ? 'Autorisée' : 'Non autorisée' }}</dd></div>
                <div class="summary-row"><dt>Mesure</dt><dd>{{ $contexte['mesure_autorisee'] ? 'Autorisée' : 'Non autorisée' }}</dd></div>
                <div class="summary-row"><dt>Supervision humaine</dt><dd>{{ $contexte['supervision_humaine'] }}</dd></div>
                <div class="summary-row"><dt>Classification</dt><dd>{{ $contexte['classification'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Profil d’exécution actif</h2></div>
        <div class="card__body">
            @if($profilActif)
                <dl class="summary-list">
                    <div class="summary-row"><dt>Référence</dt><dd class="technical-reference">{{ $profilActif['reference'] }}</dd></div>
                    <div class="summary-row"><dt>Algorithme</dt><dd>{{ $profilActif['algorithme_code'] }} · {{ $profilActif['algorithme_version'] }}</dd></div>
                    <div class="summary-row"><dt>Empreinte du plan</dt><dd class="technical-reference">{{ $profilActif['plan_hash'] }}</dd></div>
                    <div class="summary-row"><dt>Activé le</dt><dd>{{ $profilActif['active_le'] }}</dd></div>
                </dl>
            @else
                <p class="field-help">Aucun profil actif : ce contexte ne peut accepter aucune demande tant qu’aucun profil n’est compilé et activé.</p>
            @endif
        </div>
    </section>
</div>
@endsection
