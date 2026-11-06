<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0e2a3f">
    <title>GAMAD — Le réseau social d’action</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <header class="bandeau">
        <div class="conteneur barre">
            <a class="marque" href="{{ route('reseau.accueil') }}" aria-current="page">GAMAD</a>
            <nav aria-label="Navigation principale">
                <a href="#comment">Comment ça marche</a>
                <a href="#confiance">À propos</a>
                <a href="{{ route('reseau.aide') }}">Aide</a>
                <a href="{{ route('projets.index') }}">Projets</a>
                <a href="{{ route('zumras.index') }}">ZUMRA</a>
            </nav>
            <a class="bouton" href="{{ route('acces.formulaire') }}">Se connecter</a>
        </div>
    </header>

    <main>
        <section class="conteneur hero" aria-labelledby="accroche">
            <div>
                <p class="etiquette">Le réseau social d’action</p>
                <h1 id="accroche">Des personnes qui inspirent. <span>De nouvelles possibilités.</span></h1>
                <p>Sur GAMAD, découvrez des personnes, apprenez de leurs expériences, créez des liens et avancez à votre rythme.</p>
                <a class="bouton accent" href="{{ route('inscription.formulaire') }}">Créer un compte</a>
                <a class="bouton" href="{{ route('personnes.index') }}">Découvrir les personnes</a>
            </div>
            <figure>
                <img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="Quatre personnes échangent autour d’un ordinateur." width="510" height="280" fetchpriority="high">
                <figcaption>
                    <strong>Une rencontre peut ouvrir de nouvelles possibilités.</strong>
                    <span>Un savoir partagé, un besoin exprimé, une relation qui commence.</span>
                </figcaption>
            </figure>
        </section>

        <section class="conteneur section" id="comment" aria-labelledby="comment-titre">
            <h2 id="comment-titre">Découvrez, connectez-vous, grandissez</h2>
            <p>Un parcours simple, sans obligation. Chacun avance à son rythme.</p>
            <div class="etapes">
                <article><h3>Explorer</h3><p>Découvrez des personnes, des idées et des expériences inspirantes.</p></article>
                <article><h3>Se connecter</h3><p>Suivez les profils qui vous intéressent et échangez en toute confiance.</p></article>
                <article><h3>Grandir</h3><p>Apprenez, partagez et agissez quand vous le souhaitez.</p></article>
            </div>
        </section>

        <section class="conteneur section" id="confiance" aria-labelledby="confiance-titre">
            <div class="confiance">
                <h2 id="confiance-titre">Des relations qui se construisent avec confiance.</h2>
                <p>Vous choisissez ce que vous partagez, qui vous suivez et les personnes avec qui vous échangez.</p>
            </div>
        </section>
    </main>
</body>
</html>
