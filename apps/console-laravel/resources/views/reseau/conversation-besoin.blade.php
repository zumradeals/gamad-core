<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Échange privé — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body class="reseau-fil">
    <main class="conteneur section">
        <a href="{{ route('publications.show', $reference) }}">Retour au besoin</a>
        <h1>Échange privé</h1>
        @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        @forelse ($messages as $message)
            <article>
                <strong>{{ $message['de_moi'] ? 'Vous' : 'Votre interlocuteur' }}</strong>
                <p class="texte-reseau">{{ $message['texte'] }}</p>
            </article>
        @empty
            <p>Aucun message pour le moment.</p>
        @endforelse
        <form method="post" action="{{ route('besoins.propositions.messages.store', [$reference, $proposition]) }}" class="formulaire-reseau">
            @csrf
            <label for="texte">Votre message</label>
            <textarea id="texte" name="texte" rows="4" maxlength="1000" required>{{ old('texte') }}</textarea>
            <button class="bouton accent">Envoyer</button>
        </form>
    </main>
</body>
</html>
