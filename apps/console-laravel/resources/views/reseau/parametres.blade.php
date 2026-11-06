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
        <p>Le renommage conserve les codes utilisés par les profils et les besoins.</p>
        @if (session('reference_renommee'))<p role="status">Libellé enregistré.</p>@endif
        @error('libelle')<p role="alert">{{ $message }}</p>@enderror
        @foreach (['domaines' => ['Domaines', $domaines], 'territoires' => ['Localités', $territoires]] as $liste => [$titre, $entrees])
            <details>
                <summary>{{ $titre }} ({{ count($entrees) }})</summary>
                <ul>
                    @foreach ($entrees as $code => $libelle)
                        <li>
                            <form method="post" action="{{ route('reseau.parametres.renommer', [$liste, $code]) }}">
                                @csrf
                                <label>{{ $code }} <input name="libelle" value="{{ $libelle }}" required maxlength="120"></label>
                                <button type="submit">Renommer</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </section>
@endsection
