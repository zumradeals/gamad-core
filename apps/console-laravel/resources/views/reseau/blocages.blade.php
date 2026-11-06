<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personnes bloquées — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <main class="conteneur section">
        <a href="{{ route('compte.profil') }}">Mon profil</a>
        <h1>Personnes bloquées</h1>
        @forelse ($blocages as $blocage)
            <form class="barre" method="post" action="{{ route('blocages.modifier', $blocage['reference_publique']) }}">
                @csrf
                @method('DELETE')
                <strong>{{ $blocage['nom'] }}</strong>
                <button class="bouton" type="submit">Débloquer</button>
            </form>
        @empty
            <p>Aucune personne bloquée.</p>
        @endforelse
    </main>
</body>
</html>
