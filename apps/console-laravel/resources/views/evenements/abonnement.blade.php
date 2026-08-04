@extends('layouts.console')

@section('title', $abonnement['nom'] ?? $abonnement['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.evenements.index') }}">Événements</a>
    <span aria-hidden="true">/</span>
    <span>{{ $abonnement['nom'] }}</span>
</nav>

@if(session('succes'))
    <div class="alert alert--success" style="margin-bottom:18px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span><span class="alert__title">{{ session('succes') }}</span>
        @if(session('preuve'))
            <span class="alert__detail technical-reference">preuve {{ session('preuve')['reference'] ?? '' }}</span>
        @endif
        </span>
    </div>
@endif
@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span><span>{{ $errors->first() }}</span>
    </div>
@endif

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $abonnement['mode_livraison'] }} — realm {{ $abonnement['realm_reference'] }}</p>
        <h1 class="page-title">{{ $abonnement['nom'] }}</h1>
        <p class="page-subtitle technical-reference">{{ $abonnement['reference'] }}</p>
    </div>
    <span class="status {{ $abonnement['etat'] === 'ACTIF' ? 'status--success' : ($abonnement['etat'] === 'RETIRE' ? 'status--danger' : 'status--warning') }}">
        {{ $abonnement['etat'] }}
    </span>
</header>

<div class="action-bar">
    @if(in_array($abonnement['etat'], ['PREPARATION', 'SUSPENDU']))
        <form method="POST" action="{{ route('console.abonnements.activer', $abonnement['reference']) }}" onsubmit="return confirm('Activer cet abonnement ?');">
            @csrf<button class="button button--primary" type="submit">Activer</button>
        </form>
    @endif
    @if($abonnement['etat'] === 'ACTIF')
        <form method="POST" action="{{ route('console.abonnements.suspendre', $abonnement['reference']) }}" onsubmit="return confirm('Suspendre cet abonnement ?');">
            @csrf<button class="button" type="submit">Suspendre</button>
        </form>
    @endif
    @if($abonnement['etat'] !== 'RETIRE')
        <form method="POST" action="{{ route('console.abonnements.retirer', $abonnement['reference']) }}" onsubmit="return confirm('Retirer irréversiblement cet abonnement ?');">
            @csrf<button class="button button--danger" type="submit">Retirer</button>
        </form>
    @endif
    <a class="button" href="{{ route('console.rejeux.create', ['abonnement' => $abonnement['reference']]) }}">Demander un rejeu</a>
</div>

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Fiche</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Consommateur</dt><dd class="technical-reference">{{ $abonnement['consommateur_reference'] }}</dd></div>
                <div class="summary-row"><dt>Organisation</dt><dd class="technical-reference">{{ $abonnement['organisation_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Finalité</dt><dd>{{ $abonnement['finalite_reference'] }}</dd></div>
                <div class="summary-row"><dt>Taille de lot max.</dt><dd>{{ $abonnement['taille_lot_max'] }}</dd></div>
                <div class="summary-row"><dt>Durée de bail</dt><dd>{{ $abonnement['duree_bail_secondes'] }} s</dd></div>
                <div class="summary-row"><dt>Tentatives max.</dt><dd>{{ $abonnement['tentatives_max'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Curseur et retard</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Dernière séquence contiguë accusée</dt><dd>{{ $curseur['derniere_sequence_contigue_accusee'] ?? 0 }}</dd></div>
                <div class="summary-row"><dt>Livraisons en attente</dt><dd>{{ $retard['livraisons_en_attente'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Plus ancienne disponible depuis</dt><dd>{{ $retard['plus_ancienne_disponible_le'] ?? '—' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Types déclarés</h2></div>
        <div class="card__body">
            @forelse($declarations['types'] as $type)
                <div class="summary-row"><dt>{{ $type['contrat_reference'] }}</dt><dd>{{ $type['type_evenement'] }}{{ $type['version_contrainte'] ? ' ('.$type['version_contrainte'].')' : '' }}</dd></div>
            @empty
                <p>Aucun type déclaré.</p>
            @endforelse
            @if($abonnement['etat'] === 'PREPARATION')
            <form method="POST" action="{{ route('console.abonnements.types.ajouter', $abonnement['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <input class="input" name="contrat_reference" placeholder="contrat" required>
                <input class="input" name="type_evenement" placeholder="type d’événement" required>
                <button class="button" type="submit">Ajouter</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Producteurs déclarés</h2></div>
        <div class="card__body">
            @forelse($declarations['producteurs'] as $producteur)
                <div class="summary-row"><dd class="technical-reference">{{ $producteur['producteur_reference'] }}</dd></div>
            @empty
                <p>Aucun producteur déclaré.</p>
            @endforelse
            @if($abonnement['etat'] === 'PREPARATION')
            <form method="POST" action="{{ route('console.abonnements.producteurs.ajouter', $abonnement['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <input class="input" name="producteur_reference" placeholder="producteur" required>
                <button class="button" type="submit">Ajouter</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Realms déclarés</h2></div>
        <div class="card__body">
            @forelse($declarations['realms'] as $realm)
                <div class="summary-row"><dt>{{ $realm['realm_reference'] }}</dt><dd>{{ $realm['portee'] }}</dd></div>
            @empty
                <p>Aucun realm déclaré.</p>
            @endforelse
            @if($abonnement['etat'] === 'PREPARATION')
            <form method="POST" action="{{ route('console.abonnements.realms.ajouter', $abonnement['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <input class="input" name="realm_reference" placeholder="realm" required>
                <select class="select" name="portee">
                    @foreach($porteesRealm as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <button class="button" type="submit">Ajouter</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card span-12">
        <div class="card__header"><h2 class="card__title">Lettres mortes de cet abonnement</h2></div>
        <div class="card__body">
            @forelse($lettresMortes as $lm)
                <a class="identity-row" href="{{ route('console.lettres-mortes.show', $lm['reference']) }}">
                    <span class="technical-reference">{{ $lm['reference'] }}</span>
                    <span>{{ $lm['raison_code'] }}</span>
                    <span>{{ $lm['tentatives_total'] }} tentative(s)</span>
                    <span aria-hidden="true">→</span>
                </a>
            @empty
                <p>Aucune lettre morte.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
