<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Interactions — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
<main class="conteneur section">
    @if ($ecran === 'moderation')
        <a href="{{ route('console.accueil') }}">Console Core</a>
    @else
        <nav class="barre" aria-label="Réseau">
            <a href="{{ route('relations.index') }}">Mes relations</a>
            <a href="{{ route('notifications') }}">Notifications</a>
        </nav>
    @endif
    @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @if ($ecran === 'conversation')
        <h1>Conversation avec {{ $nom }}</h1>
        @forelse ($messages as $message)
            <article><strong>{{ $message['auteur_reference'] === $sujet ? 'Vous' : $nom }}</strong><p class="texte-reseau">{{ $message['texte'] }}</p></article>
        @empty
            <p>Aucun message.</p>
        @endforelse
        <form class="formulaire-reseau" method="post" action="{{ route('conversations.envoyer', $reference) }}">
            @csrf
            <label for="texte">Votre message</label>
            <textarea id="texte" name="texte" maxlength="1000" required>{{ old('texte') }}</textarea>
            <button class="bouton accent" type="submit">Envoyer</button>
        </form>
    @elseif ($ecran === 'notifications')
        <h1>Notifications</h1>
        @forelse ($notifications as $notice)
            <article class="section">
                <strong>{{ $notice['nom'] }}</strong>
                @switch($notice['type'])
                    @case('message') vous a envoyé un message. @break
                    @case('demande') vous a envoyé une demande de relation. @break
                    @case('acceptation') a accepté votre demande de relation. @break
                @endswitch
                <a href="{{ $notice['type'] === 'message' ? route('conversations.show', $notice['reference_publique']) : route('relations.index') }}">Voir</a>
                @unless ($notice['lue_le'])
                    <form method="post" action="{{ route('notifications.lire', $notice['reference']) }}">
                        @csrf
                        <button class="bouton" type="submit">Marquer comme lue</button>
                    </form>
                @endunless
            </article>
        @empty
            <p>Aucune notification.</p>
        @endforelse
    @else
        <h1>Modération du Réseau</h1>
        @forelse ($signalements as $signalement)
            <article class="section">
                <h2>{{ $motifs[$signalement['motif']] }}</h2>
                <p class="texte-reseau">{{ $signalement['contenu'] }}</p>
                <form method="post" action="{{ route('reseau.moderation.traiter', $signalement['reference']) }}">
                    @csrf
                    <button class="bouton" type="submit" name="action" value="masquer">Masquer le contenu</button>
                    <button class="bouton" type="submit" name="action" value="classer">Classer</button>
                </form>
            </article>
        @empty
            <p>Aucun signalement en attente.</p>
        @endforelse
    @endif
</main>
</body>
</html>
