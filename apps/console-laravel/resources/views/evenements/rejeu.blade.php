@extends('layouts.console')

@section('title', $rejeu['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.evenements.index') }}">Événements</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('console.rejeux.index') }}">Rejeux</a>
    <span aria-hidden="true">/</span>
    <span>{{ $rejeu['reference'] }}</span>
</nav>

@if(session('succes'))
    <div class="alert alert--success" style="margin-bottom:18px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span><span class="alert__title">{{ session('succes') }}</span></span>
    </div>
@endif
@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span><span>{{ $errors->first() }}</span>
    </div>
@endif

<header class="page-header">
    <div>
        <p class="eyebrow">Abonnement <span class="technical-reference">{{ $rejeu['abonnement_reference'] }}</span></p>
        <h1 class="page-title technical-reference">{{ $rejeu['reference'] }}</h1>
        <p class="page-subtitle">{{ $rejeu['motif'] }}</p>
    </div>
    <span class="status {{ $rejeu['etat'] === 'TERMINEE' ? 'status--success' : (in_array($rejeu['etat'], ['REFUSEE','ANNULEE']) ? 'status--danger' : 'status--warning') }}">
        {{ $rejeu['etat'] }}
    </span>
</header>

@if(in_array($rejeu['etat'], ['DEMANDEE', 'VALIDEE']))
<div class="action-bar">
    @if($rejeu['etat'] === 'DEMANDEE')
        <form method="POST" action="{{ route('console.rejeux.valider', $rejeu['reference']) }}" onsubmit="return confirm('Valider ce rejeu de '+{{ (int) ($rejeu['volume_estime'] ?? 0) }}+' événement(s) estimé(s) ?');">
            @csrf<button class="button button--primary" type="submit">Valider</button>
        </form>
    @endif
    <form method="POST" action="{{ route('console.rejeux.annuler', $rejeu['reference']) }}" onsubmit="return confirm('Annuler ce rejeu avant exécution ?');">
        @csrf<button class="button button--danger" type="submit">Annuler</button>
    </form>
</div>
@endif

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Portée</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Séquence début</dt><dd>{{ $rejeu['sequence_debut'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Séquence fin</dt><dd>{{ $rejeu['sequence_fin'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Date début</dt><dd>{{ $rejeu['date_debut'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Date fin</dt><dd>{{ $rejeu['date_fin'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Volume estimé</dt><dd>{{ $rejeu['volume_estime'] ?? '—' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Suivi</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Demandeur</dt><dd class="technical-reference">{{ $rejeu['demandeur_reference'] }}</dd></div>
                <div class="summary-row"><dt>Créé le</dt><dd>{{ $rejeu['cree_le'] }}</dd></div>
                <div class="summary-row"><dt>Terminé le</dt><dd>{{ $rejeu['termine_le'] ?? '—' }}</dd></div>
            </dl>
            @if($rejeu['etat'] === 'VALIDEE' || $rejeu['etat'] === 'EN_COURS')
                <p class="field-help" style="margin-top:12px">
                    L’exécution reste une opération de fond :
                    <span class="technical-reference">core:evenements:traiter-rejeux</span>.
                </p>
            @endif
        </div>
    </section>
</div>
@endsection
