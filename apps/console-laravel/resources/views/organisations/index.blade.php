@extends('layouts.console')

@section('title', 'Organisations')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Registre des organisations</p>
        <h1 class="page-title">Organisations</h1>
        <p class="page-subtitle">
            La fiche organisationnelle liée à une identité canonique GAMAD : son état, sa structure,
            ses affiliations et ses fonctions internes descriptives. Une affiliation « dirigeant » ou
            « représentant » n'est jamais, à elle seule, une représentation opposable.
        </p>
    </div>
    @if($autorite)
        <a class="button button--primary" href="{{ route('console.organisations.create') }}">Inscrire une organisation</a>
    @endif
</header>

<form method="GET" action="{{ route('console.organisations.index') }}" class="filter-bar">
    <input class="input" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Rechercher une organisation…">
    <select class="select" name="etat">
        <option value="">Tous les états</option>
        @foreach($etats as $valeur)
            <option value="{{ $valeur }}" @selected($filtres['etat'] === $valeur)>{{ $valeur }}</option>
        @endforeach
    </select>
    <select class="select" name="type">
        <option value="">Tous les types</option>
        @foreach($types as $valeur)
            <option value="{{ $valeur }}" @selected($filtres['type'] === $valeur)>{{ $valeur }}</option>
        @endforeach
    </select>
    <button class="button" type="submit">Filtrer</button>
</form>

<div class="section-heading">
    <div>
        <h2>{{ count($organisations) }} organisation{{ count($organisations) > 1 ? 's' : '' }} visible{{ count($organisations) > 1 ? 's' : '' }}</h2>
        @unless($autorite)
            <p>Les organisations non actives ne sont visibles que par leur propriétaire ou par l’autorité.</p>
        @endunless
    </div>
</div>

<div class="identity-list">
    @foreach($organisations as $organisation)
        <a class="identity-row" href="{{ route('console.organisations.show', $organisation['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($organisation['revision']['denomination_officielle'] ?? $organisation['reference'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $organisation['revision']['denomination_officielle'] ?? '(aucune révision)' }}</span>
                    <span class="technical-reference">{{ $organisation['reference'] }} — {{ $organisation['identite_reference'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Type</span>
                {{ $organisation['type_organisation_reference'] }}
            </span>
            <span>
                <span class="status {{ $organisation['etat'] === 'ACTIVE' ? 'status--success' : (in_array($organisation['etat'], ['DISSOUTE','RETIREE']) ? 'status--danger' : 'status--warning') }}">
                    {{ $organisation['etat'] }}
                </span>
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@endsection
