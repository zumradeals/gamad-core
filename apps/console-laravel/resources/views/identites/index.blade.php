@extends('layouts.console')

@section('title', 'Identités')

@section('content')
@php
    $typeLabels = [
        'personne' => 'Personne',
        'organisation' => 'Organisation',
        'produit' => 'Produit',
        'realm' => 'Realm',
        'agent' => 'Agent',
        'service' => 'Service',
        'INDETERMINE' => 'À déterminer',
    ];
@endphp

<header class="page-header">
    <div>
        <p class="eyebrow">Registre des identités</p>
        <h1 class="page-title">Identités</h1>
        <p class="page-subtitle">
            Recherchez les personnes, organisations et acteurs techniques reconnus par le Core.
            Les informations métier restent dans les produits.
        </p>
    </div>
    <div class="page-actions">
        <a class="button button--primary" href="{{ route('console.identites.create') }}">
            <span class="button__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            Nouvelle identité
        </a>
    </div>
</header>

<form class="filter-bar" method="GET" action="{{ route('console.identites.index') }}" role="search">
    <div class="field">
        <label for="q">Rechercher</label>
        <input class="input"
               id="q"
               name="q"
               type="search"
               value="{{ $filtres['q'] }}"
               placeholder="Nom ou référence…">
    </div>
    <div class="field">
        <label for="type">Type</label>
        <select class="select" id="type" name="type">
            <option value="">Tous les types</option>
            @foreach($typeLabels as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected($filtres['type'] === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="etat">État</label>
        <select class="select" id="etat" name="etat">
            <option value="">Tous les états</option>
            @foreach(['ACTIVE' => 'Active', 'PROVISOIRE' => 'Provisoire', 'SUSPENDUE' => 'Suspendue', 'CLOTUREE' => 'Clôturée', 'DISSOUTE' => 'Dissoute'] as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected($filtres['etat'] === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="align-self:end">
        <button class="button button--secondary" type="submit">Appliquer</button>
    </div>
</form>

<div class="section-heading">
    <div>
        <h2>{{ count($identites) }} résultat{{ count($identites) > 1 ? 's' : '' }}</h2>
        <p>{{ $total }} identité{{ $total > 1 ? 's' : '' }} dans le registre avant filtrage.</p>
    </div>
</div>

@if($identites === [])
    <section class="empty-state">
        <div class="empty-state__symbol" aria-hidden="true">⌕</div>
        <h2>Aucune identité ne correspond</h2>
        <p>Modifiez vos filtres ou inscrivez une nouvelle identité si elle n’existe pas encore.</p>
        <a class="button button--primary" href="{{ route('console.identites.create') }}">Inscrire une identité</a>
    </section>
@else
    <div class="identity-list">
        @foreach($identites as $identite)
            @php
                $etat = (string) ($identite['etat'] ?? 'INDETERMINE');
                $statutClasse = in_array($etat, ['ACTIVE', 'VERIFIEE'], true)
                    ? 'status--success'
                    : ($etat === 'PROVISOIRE' ? 'status--warning' : '');
            @endphp
            <a class="identity-row" href="{{ route('console.identites.show', $identite['reference']) }}">
                <span class="identity-main">
                    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($typeLabels[$identite['type']] ?? $identite['type'], 0, 2) }}</span>
                    <span style="min-width:0">
                        <span class="identity-name">{{ $identite['libelle'] }}</span>
                        <span class="technical-reference">{{ $identite['reference'] }}</span>
                    </span>
                </span>
                <span>
                    <span class="identity-cell-label">Type</span>
                    {{ $typeLabels[$identite['type']] ?? $identite['type'] }}
                </span>
                <span>
                    <span class="status {{ $statutClasse }}">{{ ucfirst(mb_strtolower($etat)) }}</span>
                </span>
                <span aria-hidden="true">→</span>
            </a>
        @endforeach
    </div>
@endif
@endsection
