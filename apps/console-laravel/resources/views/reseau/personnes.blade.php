<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($fiche ?? false) ? $profils[0]['nom'] : 'Découvrir les personnes' }} — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <header class="bandeau">
        <div class="conteneur barre">
            <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD</a>
            <a href="{{ route('personnes.index') }}">Annuaire</a>
            <a class="bouton" href="{{ route('compte.profil') }}">Mon profil</a>
        </div>
    </header>
    <main class="conteneur section">
        @unless ($fiche ?? false)
            <h1>Découvrir les personnes</h1>
            <form action="{{ route('personnes.index') }}" role="search">
                <label for="q">Rechercher un nom ou une compétence</label>
                <input id="q" name="q" type="search" value="{{ $recherche }}" maxlength="100">
                <button type="submit">Rechercher</button>
            </form>
        @endunless
        @forelse ($profils as $profil)
            <article class="section">
                @if ($fiche ?? false)
                    <h1>{{ $profil['nom'] }}</h1>
                @else
                    <h2><a href="{{ route('personnes.show', $profil['reference_publique']) }}">{{ $profil['nom'] }}</a></h2>
                @endif
                @if ($profil['bio'])<p>{{ $profil['bio'] }}</p>@endif
                @if ($profil['apportees'])<p><strong>Peut apporter</strong> {{ implode(', ', $profil['apportees']) }}</p>@endif
                @if ($profil['recherchees'])<p><strong>Veut apprendre</strong> {{ implode(', ', $profil['recherchees']) }}</p>@endif
                @if (($fiche ?? false) && $profil['peut_bloquer'])
                    <form method="post" class="formulaire-reseau" action="{{ route('relations.demander', $profil['reference_publique']) }}">
                        @csrf
                        <label for="note">Pourquoi souhaitez-vous faire connaissance ?</label>
                        <textarea name="note" id="note" maxlength="280" required>{{ old('note') }}</textarea>
                        @error('note')<p role="alert">{{ $message }}</p>@enderror
                        <button class="bouton" type="submit">Entrer en relation</button>
                    </form>
                    <form method="post" action="{{ route('blocages.modifier', $profil['reference_publique']) }}">
                        @csrf
                        <button class="bouton" type="submit">Bloquer cette personne</button>
                    </form>
                @endif
            </article>
        @empty
            <p>Aucun profil visible pour cette recherche.</p>
        @endforelse
    </main>
</body>
</html>
