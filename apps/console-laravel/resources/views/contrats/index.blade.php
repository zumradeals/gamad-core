@extends('layouts.console')

@section('title', 'Contrats')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Registre des contrats</p>
        <h1 class="page-title">Contrats</h1>
        <p class="page-subtitle">
            Les échanges du Core, décrits, versionnés et opposables : producteur, consommateurs,
            opérations, schémas, erreurs, compatibilité et conformité. Une seule version active
            à la fois, jamais modifiée en place une fois soumise.
        </p>
    </div>
    @if($autorite)
        <a class="button button--primary" href="{{ route('console.contrats.create') }}">Inscrire un contrat</a>
    @endif
</header>

<div class="section-heading">
    <div>
        <h2>{{ count($contrats) }} contrat{{ count($contrats) > 1 ? 's' : '' }} visible{{ count($contrats) > 1 ? 's' : '' }}</h2>
        @unless($autorite)
            <p>Les contrats sans version active ne sont visibles que par leur propriétaire ou par l’autorité.</p>
        @endunless
    </div>
</div>

<div class="identity-list">
    @foreach($contrats as $contrat)
        <a class="identity-row" href="{{ route('console.contrats.show', $contrat['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($contrat['nom'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $contrat['nom'] }}</span>
                    <span class="technical-reference">{{ $contrat['reference'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Type</span>
                {{ $contrat['type_contrat'] }}
            </span>
            <span>
                @if($contrat['version_active'])
                    <span class="status status--success">Active — {{ $contrat['version_active'] }}</span>
                @else
                    <span class="status status--warning">Aucune version active</span>
                @endif
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@endsection
