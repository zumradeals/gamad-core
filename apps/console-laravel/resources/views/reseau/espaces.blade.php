<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projets et ZUMRA — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
<main class="conteneur section">
    <nav class="barre" aria-label="Réseau">
        <a href="{{ route('reseau.accueil') }}">GAMAD</a>
        <a href="{{ route('projets.index') }}">Projets</a>
        <a href="{{ route('zumras.index') }}">ZUMRA</a>
    </nav>
    @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @switch($ecran)
        @case('zumras')
            <h1>Découvrir les ZUMRA</h1>
            <a class="bouton" href="{{ route('zumras.create') }}">Créer une ZUMRA</a>
            @forelse ($zumras as $entree)
                <h2><a href="{{ route('zumras.show', $entree['reference']) }}">{{ $entree['nom'] }}</a></h2>
            @empty
                <p>Aucune ZUMRA à afficher.</p>
            @endforelse
            @break
        @case('zumra')
            <h1>{{ $zumra['nom'] }}</h1>
            <p class="texte-reseau">{{ $zumra['presentation'] }}</p>
            @if ($responsable)
                <section aria-labelledby="accueils">
                    <h2 id="accueils">Demandes d’accueil</h2>
                    @forelse ($demandes as $demande)
                        <form method="post" action="{{ route('zumras.accueil', [$zumra['reference'], $demande['reference']]) }}">
                            @csrf
                            <p>{{ $demande['titre'] }}</p>
                            <button class="bouton" name="decision" value="accepter">Accepter</button>
                            <button class="bouton" name="decision" value="refuser">Refuser</button>
                        </form>
                    @empty
                        <p>Aucune demande en attente.</p>
                    @endforelse
                </section>
            @endif
            @break
        @case('projets')
            <h1>Projets GAMAD</h1>
            <a class="bouton" href="{{ route('projets.create') }}">Créer un projet</a>
            @forelse ($projets as $entree)
                <h2><a href="{{ route('projets.show', $entree['reference']) }}">{{ $entree['titre'] }}</a></h2>
            @empty
                <p>Aucun projet visible.</p>
            @endforelse
            @break
        @case('projet')
            <h1>{{ $projet['titre'] }}</h1>
            @if ($projet['prive'])
                <h2>Dossier privé</h2>
                <p class="texte-reseau">{{ $projet['dossier'] }}</p>
                <p>Accueil par la ZUMRA
                    {{ ['en_attente' => 'En attente', 'accepte' => 'Accepté', 'refuse' => 'Refusé'][$projet['accueil']] }}
                </p>
                @if ($projet['accueil'] === 'accepte')
                    <form class="formulaire-reseau" method="post" action="{{ route('projets.vitrine', $projet['reference']) }}">
                        @csrf
                        <label for="vitrine">Présentation publique</label>
                        <textarea id="vitrine" name="vitrine" maxlength="500">{{ old('vitrine', $projet['vitrine']) }}</textarea>
                        <button class="bouton accent" name="action" value="publier">Publier la présentation</button>
                        @if ($projet['vitrine'] !== null)
                            <button class="bouton" name="action" value="retirer">Retirer la présentation</button>
                        @endif
                    </form>
                @endif
            @else
                <p class="texte-reseau">{{ $projet['vitrine'] }}</p>
            @endif
            @break
        @case('creer-zumra')
            <h1>Créer une ZUMRA</h1>
            <form class="formulaire-reseau" method="post" action="{{ route('zumras.store') }}">
                @csrf
                <label for="nom">Nom</label>
                <input id="nom" name="nom" value="{{ old('nom') }}" maxlength="100" required>
                <label for="presentation">Présentation publique</label>
                <textarea id="presentation" name="presentation" maxlength="500" required>{{ old('presentation') }}</textarea>
                <button class="bouton accent">Créer</button>
            </form>
            @break
        @case('creer-projet')
            <h1>Créer un projet</h1>
            @if ($zumras)
                <form class="formulaire-reseau" method="post" action="{{ route('projets.store') }}">
                    @csrf
                    <label for="zumra">ZUMRA d’accueil</label>
                    <select id="zumra" name="zumra" required>
                        @foreach ($zumras as $entree)
                            <option value="{{ $entree['reference'] }}" @selected(old('zumra') === $entree['reference'])>{{ $entree['nom'] }}</option>
                        @endforeach
                    </select>
                    <label for="titre">Titre</label>
                    <input id="titre" name="titre" value="{{ old('titre') }}" maxlength="120" required>
                    <label for="dossier">Dossier privé</label>
                    <textarea id="dossier" name="dossier" maxlength="4000" required>{{ old('dossier') }}</textarea>
                    <button class="bouton accent">Créer le projet</button>
                </form>
            @else
                <p>Une ZUMRA est nécessaire pour accueillir le projet.</p>
                <a href="{{ route('zumras.create') }}">Créer une ZUMRA</a>
            @endif
            @break
    @endswitch
</main>
</body>
</html>
