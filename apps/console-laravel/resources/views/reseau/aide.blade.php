<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aide — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <header class="bandeau">
        <div class="conteneur barre">
            <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD</a>
            <a class="bouton" href="{{ route('reseau.connexion') }}">Se connecter</a>
        </div>
    </header>
    <main class="conteneur section">
        <h1>Aide</h1>
        <p>Des réponses courtes aux questions les plus fréquentes. <a href="{{ route('reseau.contact') }}">Écrivez-nous</a>.</p>

        <section aria-labelledby="aide-compte" class="section">
            <h2 id="aide-compte">Mon compte</h2>
            <details>
                <summary>Comment me connecter ?</summary>
                <p>Utilisez votre adresse e-mail, votre téléphone ou votre nom d’utilisateur associé à votre compte. L’adresse e-mail et le téléphone doivent être vérifiés avant de servir à la connexion.</p>
            </details>
            <details>
                <summary>Comment modifier ma présentation ?</summary>
                <p>Après la connexion, ouvrez <a href="{{ route('compte.profil') }}">Mon profil GAMAD</a>. Vous pouvez modifier ou effacer votre présentation.</p>
            </details>
        </section>

        <section aria-labelledby="aide-securite" class="section">
            <h2 id="aide-securite">Sécurité et confiance</h2>
            <details>
                <summary>Comment gérer mes moyens de connexion ?</summary>
                <p>Ouvrez <a href="{{ route('console.acces.index') }}">Mon accès</a> pour consulter vos moyens de connexion et gérer vos clés d’accès et codes de secours.</p>
            </details>
        </section>
    </main>
</body>
</html>
