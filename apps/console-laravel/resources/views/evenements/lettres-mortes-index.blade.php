@extends('layouts.console')

@section('title', 'Lettres mortes')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.evenements.index') }}">Événements</a>
    <span aria-hidden="true">/</span>
    <span>Lettres mortes</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Livraisons en échec définitif</p>
        <h1 class="page-title">Lettres mortes</h1>
        <p class="page-subtitle">
            Une lettre morte n’est jamais effacée. Elle se relance, gouvernée et motivée, ou se clôture, motivée.
        </p>
    </div>
</header>

<form method="GET" action="{{ route('console.lettres-mortes.index') }}" class="filter-bar">
    <input class="input" type="text" name="abonnement" value="{{ $filtreAbonnement }}" placeholder="Filtrer par référence d’abonnement…">
    <button class="button" type="submit">Filtrer</button>
</form>

@unless($autorise)
    <div class="form-error" role="alert"><span aria-hidden="true">!</span><span>{{ $motif }}</span></div>
@else
    <div class="identity-list">
        @forelse($lettresMortes as $lm)
            <a class="identity-row" href="{{ route('console.lettres-mortes.show', $lm['reference']) }}">
                <span class="technical-reference">{{ $lm['reference'] }}</span>
                <span>{{ $lm['raison_code'] }}</span>
                <span>{{ $lm['tentatives_total'] }} tentative(s)</span>
                <span class="status {{ ($lm['cloturee'] ?? false) ? 'status--danger' : 'status--warning' }}">
                    {{ ($lm['cloturee'] ?? false) ? 'CLÔTURÉE' : 'OUVERTE' }}
                </span>
                <span aria-hidden="true">→</span>
            </a>
        @empty
            <p style="padding:16px">Aucune lettre morte.</p>
        @endforelse
    </div>
@endunless
@endsection
