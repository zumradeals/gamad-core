@extends('layouts.console')

@section('title', 'Realms')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Registre des realms</p>
        <h1 class="page-title">Realms</h1>
        <p class="page-subtitle">
            Le périmètre nommé, borné et gouverné dans lequel des organisations, produits, capacités ou
            contrats peuvent être rattachés. Un realm actif ou un rattachement ne donne jamais, à lui seul,
            une autorisation : seul CAP-CORE-004 décide.
        </p>
    </div>
    @if($autorite)
        <a class="button button--primary" href="{{ route('console.realms.create') }}">Inscrire un realm</a>
    @endif
</header>

<form method="GET" action="{{ route('console.realms.index') }}" class="filter-bar">
    <input class="input" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Rechercher un realm…">
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
        <h2>{{ count($realms) }} realm{{ count($realms) > 1 ? 's' : '' }} visible{{ count($realms) > 1 ? 's' : '' }}</h2>
        @unless($autorite)
            <p>Les realms non actifs ne sont visibles que par l’autorité.</p>
        @endunless
    </div>
</div>

<div class="identity-list">
    @foreach($realms as $realm)
        <a class="identity-row" href="{{ route('console.realms.show', $realm['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($realm['revision']['nom_affichage'] ?? $realm['reference'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $realm['revision']['nom_affichage'] ?? '(aucune révision)' }}</span>
                    <span class="technical-reference">{{ $realm['reference'] }} — {{ $realm['code_canonique'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Type</span>
                {{ $realm['type_realm_reference'] }}
            </span>
            <span>
                <span class="status {{ $realm['etat'] === 'ACTIF' ? 'status--success' : (in_array($realm['etat'], ['FERME','RETIRE']) ? 'status--danger' : 'status--warning') }}">
                    {{ $realm['etat'] }}
                </span>
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@endsection
