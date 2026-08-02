@extends('layouts.console')

@section('title', 'Sources')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Registre des sources</p>
        <h1 class="page-title">Sources</h1>
        <p class="page-subtitle">
            La fiche de provenance de chaque source reconnue du Core : d’où vient une information,
            qui en répond, pour quelles finalités elle est utilisable. Les données produites par la
            source restent chez elle.
        </p>
    </div>
    @if($autorite)
        <a class="button button--primary" href="{{ route('console.sources.create') }}">Inscrire une source</a>
    @endif
</header>

<form method="GET" action="{{ route('console.sources.index') }}" class="filter-bar">
    <input class="input" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Rechercher une source…">
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
        <h2>{{ count($sources) }} source{{ count($sources) > 1 ? 's' : '' }} visible{{ count($sources) > 1 ? 's' : '' }}</h2>
        @unless($autorite)
            <p>Les sources non actives ne sont visibles que par leur propriétaire ou par l’autorité.</p>
        @endunless
    </div>
</div>

<div class="identity-list">
    @foreach($sources as $source)
        <a class="identity-row" href="{{ route('console.sources.show', $source['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($source['nom_affichage'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $source['nom_affichage'] }}</span>
                    <span class="technical-reference">{{ $source['reference'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Type</span>
                {{ $source['type_source'] }}
            </span>
            <span>
                <span class="status {{ $source['etat'] === 'ACTIVE' ? 'status--success' : ($source['etat'] === 'RETIREE' ? 'status--danger' : 'status--warning') }}">
                    {{ $source['etat'] }}
                </span>
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@endsection
