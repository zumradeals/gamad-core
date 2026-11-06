<header class="bandeau">
    <div class="conteneur barre">
        <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD</a>
        <nav aria-label="Navigation principale">
            <a href="{{ route('reseau.accueil') }}">Accueil</a>
            <a href="{{ route('reseau.decouvrir') }}">Découvrir</a>
            <a href="{{ route('reseau.accueil') }}#comment">Comment ça marche</a>
            <a href="{{ route('reseau.accueil') }}#confiance">À propos</a>
        </nav>
        <div class="reseau-actions">
            @unless (request()->routeIs('reseau.connexion'))
                <a class="bouton" href="{{ route('reseau.connexion') }}">Se connecter</a>
            @endunless
            @unless (request()->routeIs('inscription.formulaire'))
                <a class="bouton accent" href="{{ route('inscription.formulaire') }}">Créer un compte</a>
            @endunless
        </div>
    </div>
</header>
