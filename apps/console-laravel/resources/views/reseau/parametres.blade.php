@extends('layouts.console')

@section('title', 'Paramètres du Réseau')

@section('content')
    <header class="page-header">
        <h1 class="page-title">Paramètres du Réseau</h1>
        <p class="page-subtitle">Les réglages du Réseau sont administrés depuis le Core.</p>
        <a href="{{ route('reseau.administration') }}">Retour à l’administration du Réseau</a>
    </header>

    <section class="card" aria-labelledby="reglages-core">
        <h2 id="reglages-core">Réglages transversaux du Core</h2>
        <ul>
            <li><a href="{{ route('console.continuite.index') }}">Continuité et sauvegardes</a></li>
            <li><a href="{{ route('console.politiques.index') }}">Politiques et autorisations</a></li>
        </ul>
    </section>

    <section class="card" aria-labelledby="listes-reference">
        <h2 id="listes-reference">Listes de référence</h2>
        <p>Consultation des listes utilisées par les besoins et l’annuaire.</p>
        <details>
            <summary>Domaines ({{ count($domaines) }})</summary>
            <ul>
                @foreach ($domaines as $libelle)<li>{{ $libelle }}</li>@endforeach
            </ul>
        </details>
        <details>
            <summary>Localités ({{ count($territoires) }})</summary>
            <ul>
                @foreach ($territoires as $libelle)<li>{{ $libelle }}</li>@endforeach
            </ul>
        </details>
    </section>
@endsection
