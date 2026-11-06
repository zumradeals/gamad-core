<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — GAMAD Réseau</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <header class="bandeau">
        <div class="conteneur barre">
            <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD</a>
            <a href="{{ route('reseau.aide') }}">Aide</a>
        </div>
    </header>
    <main class="conteneur section">
        <h1>Se connecter à GAMAD Réseau</h1>
        @if (session('inscription_statut'))
            <p role="status">{{ session('inscription_statut') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert">
                @foreach ($errors->all() as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
        @endif
        <form class="formulaire-reseau" method="POST" action="{{ route('reseau.connecter') }}">
            @csrf
            <label for="entite">E-mail, téléphone ou nom d’utilisateur</label>
            <input id="entite" name="entite" value="{{ old('entite') }}" autocomplete="username" maxlength="256" required>
            <label for="secret">Mot de passe</label>
            <input id="secret" name="secret" type="password" autocomplete="current-password" required>
            <button class="bouton accent" type="submit">Se connecter</button>
        </form>
        <p><a href="{{ route('inscription.formulaire') }}">Créer un compte</a></p>
        <p><a href="{{ route('reseau.accueil') }}">Découvrir GAMAD sans compte</a></p>
    </main>
</body>
</html>
