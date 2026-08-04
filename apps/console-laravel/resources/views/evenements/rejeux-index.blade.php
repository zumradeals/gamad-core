@extends('layouts.console')

@section('title', 'Rejeux')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.evenements.index') }}">Événements</a>
    <span aria-hidden="true">/</span>
    <span>Rejeux</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Rejeu borné et gouverné</p>
        <h1 class="page-title">Rejeux</h1>
        <p class="page-subtitle">
            Un rejeu ne crée jamais un nouvel événement : il remet à disposition des livraisons déjà acceptées.
        </p>
    </div>
    <a class="button button--primary" href="{{ route('console.rejeux.create') }}">Demander un rejeu</a>
</header>

<form method="GET" action="{{ route('console.rejeux.index') }}" class="filter-bar">
    <input class="input" type="text" name="abonnement" value="{{ $filtreAbonnement }}" placeholder="Filtrer par référence d’abonnement…">
    <button class="button" type="submit">Filtrer</button>
</form>

@unless($autorise)
    <div class="form-error" role="alert"><span aria-hidden="true">!</span><span>{{ $motif }}</span></div>
@else
    <div class="identity-list">
        @forelse($rejeux as $rejeu)
            <a class="identity-row" href="{{ route('console.rejeux.show', $rejeu['reference']) }}">
                <span class="technical-reference">{{ $rejeu['reference'] }}</span>
                <span class="technical-reference">{{ $rejeu['abonnement_reference'] }}</span>
                <span>{{ $rejeu['volume_estime'] ?? '—' }} événement(s) estimé(s)</span>
                <span class="status {{ $rejeu['etat'] === 'TERMINEE' ? 'status--success' : (in_array($rejeu['etat'], ['REFUSEE','ANNULEE']) ? 'status--danger' : 'status--warning') }}">
                    {{ $rejeu['etat'] }}
                </span>
                <span aria-hidden="true">→</span>
            </a>
        @empty
            <p style="padding:16px">Aucun rejeu.</p>
        @endforelse
    </div>
@endunless
@endsection
