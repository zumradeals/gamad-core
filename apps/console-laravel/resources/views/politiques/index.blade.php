@extends('layouts.console')

@section('title', 'Politiques')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Registre des politiques</p>
        <h1 class="page-title">Politiques</h1>
        <p class="page-subtitle">
            Les politiques et règles que CAP-CORE-004 évalue pour décider. Chaque version
            est immuable une fois soumise, simulée avant activation, et une seule version
            reste active à la fois.
        </p>
    </div>
    @if($autorite)
        <a class="button button--primary" href="{{ route('console.politiques.create') }}">Inscrire une politique</a>
    @endif
</header>

<div class="section-heading">
    <div>
        <h2>{{ count($politiques) }} politique{{ count($politiques) > 1 ? 's' : '' }} visible{{ count($politiques) > 1 ? 's' : '' }}</h2>
        @unless($autorite)
            <p>Les politiques sans version active ne sont visibles que par leur propriétaire ou par l’autorité.</p>
        @endunless
    </div>
</div>

<div class="identity-list">
    @foreach($politiques as $politique)
        <a class="identity-row" href="{{ route('console.politiques.show', $politique['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($politique['libelle'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $politique['libelle'] }}</span>
                    <span class="technical-reference">{{ $politique['reference'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Domaine</span>
                {{ $politique['domaine'] ?? '—' }}
            </span>
            <span>
                @if($politique['version_active'])
                    <span class="status status--success">Active — {{ $politique['version_active'] }}</span>
                @else
                    <span class="status status--warning">Aucune version active</span>
                @endif
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>
@endsection
