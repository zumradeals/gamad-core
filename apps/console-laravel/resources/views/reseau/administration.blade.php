@extends('layouts.console')

@section('title', 'Administration du Réseau')

@section('content')
    <header class="page-header">
        <h1 class="page-title">Administration du Réseau</h1>
        <p class="page-subtitle">Consulter les demandes en attente et accéder aux écrans de traitement.</p>
        <a class="button" href="{{ route('reseau.parametres') }}">Paramètres du Réseau</a>
    </header>

    @foreach ($rubriques as $rubrique)
        <section class="card" aria-labelledby="rubrique-{{ $loop->index }}">
            <div class="card__header">
                <div>
                    <h2 class="card__title" id="rubrique-{{ $loop->index }}">{{ $rubrique['titre'] }}</h2>
                    <p class="card__description">{{ $rubrique['attente'] }} en attente</p>
                </div>
                <a class="button" href="{{ route($rubrique['route']) }}">Ouvrir {{ mb_strtolower($rubrique['titre']) }}</a>
            </div>
        </section>
    @endforeach
@endsection
