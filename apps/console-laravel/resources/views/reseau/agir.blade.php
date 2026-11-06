<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agir — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <header class="bandeau">
        <div class="conteneur barre">
            <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD</a>
            <a href="{{ route('fil') }}">Retour au fil</a>
        </div>
    </header>
    <main class="conteneur section">
        <h1>Que voulez-vous faire ?</h1>
        <p>Choisissez ce que vous souhaitez publier en votre nom.</p>
        <nav class="etapes" aria-label="Actions de publication">
            @foreach (\App\Application\Reseau\ContenusGamad::TYPES as $type => $libelle)
                <article>
                    <h2><a href="{{ route('fil', ['type' => $type]) }}#publier">{{ $libelle }}</a></h2>
                </article>
            @endforeach
        </nav>
    </main>
</body>
</html>
