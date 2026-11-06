<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Découvrir — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <header class="bandeau">
        <div class="conteneur barre">
            <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD</a>
        </div>
    </header>
    <main class="conteneur section">
        <h1>Que cherchez-vous ?</h1>
        <p>Découvrez les personnes, les communautés et les initiatives du Réseau.</p>
        <nav class="etapes" aria-label="Découvrir le Réseau">
            <article>
                <h2><a href="{{ route('personnes.index') }}">Personnes</a></h2>
                <p>Trouvez des personnes et découvrez leur parcours.</p>
            </article>
            <article>
                <h2><a href="{{ route('projets.index') }}">Projets</a></h2>
                <p>Découvrez les initiatives et leurs prochaines étapes.</p>
            </article>
            <article>
                <h2><a href="{{ route('entreprises.index') }}">Entreprises</a></h2>
                <p>Parcourez les entreprises référencées sur GAMAD.</p>
            </article>
            <article>
                <h2><a href="{{ route('zumras.index') }}">ZUMRA</a></h2>
                <p>Explorez les communautés qui portent des actions collectives.</p>
            </article>
            <article>
                <h2><a href="{{ route('opportunites.index') }}">Opportunités</a></h2>
                <p>Consultez les opportunités publiées par les entreprises.</p>
            </article>
        </nav>
    </main>
</body>
</html>
