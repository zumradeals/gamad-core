@extends('layouts.console')

@section('title', 'Vocabulaire')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Registre du vocabulaire canonique</p>
        <h1 class="page-title">Vocabulaire</h1>
        <p class="page-subtitle">
            Les valeurs canoniques du Core — codes, libellés localisés, alias, relations
            sémantiques, mappings externes et usages déclarés — décrites, versionnées et
            opposables. Une seule version active à la fois, jamais modifiée en place une fois
            soumise.
        </p>
    </div>
    @if($autorite)
        <a class="button button--primary" href="{{ route('console.vocabulaires.create') }}">Inscrire un vocabulaire</a>
    @endif
</header>

<div class="section-heading">
    <div>
        <h2>{{ count($vocabulaires) }} vocabulaire{{ count($vocabulaires) > 1 ? 's' : '' }} visible{{ count($vocabulaires) > 1 ? 's' : '' }}</h2>
        @unless($autorite)
            <p>Les vocabulaires sans version active ne sont visibles que par leur propriétaire ou par l’autorité.</p>
        @endunless
    </div>
</div>

<div class="identity-list">
    @foreach($vocabulaires as $vocabulaire)
        <a class="identity-row" href="{{ route('console.vocabulaires.show', $vocabulaire['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($vocabulaire['nom'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $vocabulaire['nom'] }}</span>
                    <span class="technical-reference">{{ $vocabulaire['reference'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Namespace</span>
                {{ $vocabulaire['namespace'] }}
            </span>
            <span>
                <span class="identity-cell-label">Portée</span>
                {{ $vocabulaire['portee'] }}
            </span>
            <span>
                @if($vocabulaire['version_active'])
                    <span class="status status--success">Active — {{ $vocabulaire['version_active'] }}</span>
                @else
                    <span class="status status--warning">Aucune version active</span>
                @endif
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@endsection
