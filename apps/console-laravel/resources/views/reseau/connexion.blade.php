<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — GAMAD Réseau</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body class="reseau-public">
    @include('reseau.navigation-publique')
    <main class="conteneur reseau-acces">
        <section class="reseau-acces-intro">
            <p class="etiquette">Le réseau social d’action</p>
            <p class="reseau-acces-titre">Retrouvez votre espace <span>GAMAD.</span></p>
            <p>Reconnectez-vous pour continuer à échanger, à découvrir, à apprendre et à agir selon vos envies.</p>
            <ul>
                <li><strong>Vos relations</strong> Retrouvez les personnes que vous suivez.</li>
                <li><strong>Vos activités</strong> Consultez vos notifications et vos messages.</li>
                <li><strong>Vos opportunités</strong> Découvrez de nouvelles perspectives.</li>
            </ul>
        </section>
        <img class="reseau-portrait" src="{{ asset('images/gamad-connexion-portrait.webp') }}" alt="" width="388" height="532">
        <section class="reseau-carte" aria-labelledby="connexion-titre">
            <h1 id="connexion-titre">Se connecter à GAMAD Réseau</h1>
            <p>Accédez à votre espace GAMAD.</p>
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
                <button class="bouton reseau-valider" type="submit">Se connecter</button>
            </form>
            <p><a href="{{ route('inscription.formulaire') }}">Créer un compte</a></p>
            <p><a href="{{ route('reseau.decouvrir') }}">Découvrir GAMAD sans compte</a></p>
        </section>
    </main>
    @include('reseau.pied-public')
</body>
</html>
