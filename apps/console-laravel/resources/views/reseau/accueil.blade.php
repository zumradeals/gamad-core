<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0e2a3f">
    <title>GAMAD — Le réseau social d’action</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body class="reseau-public">
    @include('reseau.navigation-publique')

    <main>
        <section class="conteneur hero" aria-labelledby="accroche">
            <div>
                <p class="etiquette">Le réseau social d’action</p>
                <h1 id="accroche">Des personnes qui inspirent. <span>De nouvelles possibilités.</span></h1>
                <p>Sur GAMAD, découvrez des personnes, apprenez de leurs expériences, créez des liens et avancez à votre rythme.</p>
                <div class="reseau-actions">
                    <a class="bouton accent" href="{{ route('personnes.index') }}">Découvrir les personnes</a>
                    <a class="bouton" href="{{ route('inscription.formulaire') }}">Créer un compte</a>
                </div>
            </div>
            <figure>
                <img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="Quatre personnes échangent autour d’un ordinateur." width="510" height="280" fetchpriority="high">
                <figcaption>
                    <strong>Une rencontre peut ouvrir de nouvelles possibilités.</strong>
                    <span>Un savoir partagé, un besoin exprimé, une relation qui commence.</span>
                </figcaption>
            </figure>
        </section>

        <section class="reseau-recherche">
            <form class="conteneur" method="get" action="{{ route('personnes.index') }}">
                <label for="recherche-personnes">Que souhaitez-vous découvrir ?</label>
                <input id="recherche-personnes" name="q" type="search" maxlength="100" placeholder="Un nom ou une compétence">
                <button class="bouton accent" type="submit">Rechercher</button>
            </form>
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
                <div>
                    <h2 id="confiance-titre">Des relations qui se construisent avec confiance.</h2>
                    <p>Vous choisissez ce que vous partagez, qui vous suivez et les personnes avec qui vous échangez.</p>
                </div>
                <ul>
                    <li><strong>Votre parcours vous appartient.</strong> Partagez les compétences et expériences que vous souhaitez rendre visibles.</li>
                    <li><strong>Vous choisissez votre visibilité.</strong> Gérez la visibilité de votre fiche dans l’annuaire.</li>
                    <li><strong>L’action reste un choix.</strong> Découvrez les communautés et participez à votre rythme.</li>
                </ul>
            </div>
        </section>
        <section class="conteneur section reseau-invitation">
            <h2>Votre place existe déjà sur GAMAD.</h2>
            <p>Commencez par découvrir. La suite viendra naturellement.</p>
            <a class="bouton accent" href="{{ route('inscription.formulaire') }}">Créer un compte</a>
        </section>
    </main>
    @include('reseau.pied-public')
</body>
</html>
