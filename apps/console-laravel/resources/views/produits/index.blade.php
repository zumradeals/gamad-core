@extends('layouts.console')

@section('title', 'Produits')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Registre des produits</p>
        <h1 class="page-title">Produits</h1>
        <p class="page-subtitle">
            La fiche opérationnelle de chaque produit connu du Core : son cycle de vie, ses
            environnements et sa fédérabilité explicite. Le compte local, les rôles métier, les
            abonnements et les données du produit restent chez lui.
        </p>
    </div>
    @if($autorite)
        <a class="button button--primary" href="{{ route('console.produits.create') }}">Inscrire un produit</a>
    @endif
</header>

<form method="GET" action="{{ route('console.produits.index') }}" class="filter-bar">
    <input class="input" type="search" name="q" value="{{ $filtres['q'] }}" placeholder="Rechercher un produit…">
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
        <h2>{{ count($produits) }} produit{{ count($produits) > 1 ? 's' : '' }} visible{{ count($produits) > 1 ? 's' : '' }}</h2>
        @unless($autorite)
            <p>Les produits non actifs ne sont visibles que par leur propriétaire ou par l’autorité.</p>
        @endunless
    </div>
</div>

<div class="identity-list">
    @foreach($produits as $produit)
        <a class="identity-row" href="{{ route('console.produits.show', $produit['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($produit['nom_affichage'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $produit['nom_affichage'] }}</span>
                    <span class="technical-reference">{{ $produit['reference'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Type</span>
                {{ $produit['type_produit'] }}
            </span>
            <span>
                <span class="status {{ $produit['etat'] === 'ACTIF' ? 'status--success' : ($produit['etat'] === 'RETIRE' ? 'status--danger' : 'status--warning') }}">
                    {{ $produit['etat'] }}
                </span>
            </span>
            <span>
                @if($produit['federation_autorisee'])
                    <span class="status status--success">Fédérable</span>
                @else
                    <span class="status status--warning">Non fédérable</span>
                @endif
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@endsection
