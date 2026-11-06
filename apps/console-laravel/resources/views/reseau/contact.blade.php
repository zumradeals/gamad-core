<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
<main class="conteneur section">
    @if ($ecran === 'formulaire')
        <a href="{{ route('reseau.accueil') }}">Accueil GAMAD</a>
        <h1>Contacter GAMAD</h1>
        @if (session('contact_envoye'))<p role="status">Votre message a été reçu.</p>@endif
        @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        <form class="formulaire-reseau" method="post" action="{{ route('reseau.contact.envoyer') }}">
            @csrf
            <label for="nom">Votre nom</label>
            <input id="nom" name="nom" value="{{ old('nom') }}" maxlength="80" autocomplete="name" required>
            <label for="email">Votre e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
            <label for="sujet">Sujet</label>
            <select id="sujet" name="sujet" required>
                @foreach ($sujets as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(old('sujet') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="5" minlength="10" maxlength="2000" required>{{ old('message') }}</textarea>
            <div hidden><label for="website">Site web</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <button class="bouton accent">Envoyer</button>
        </form>
    @elseif ($ecran === 'liste')
        <a href="{{ route('console.accueil') }}">Console Core</a>
        <h1>Messages de contact</h1>
        @forelse ($messages as $entree)
            <article>
                <h2><a href="{{ route('reseau.contact.ouvrir', $entree['reference']) }}">{{ $sujets[$entree['sujet']] }}</a></h2>
                <p>{{ ['new' => 'Nouveau', 'read' => 'Lu', 'handled' => 'Traité'][$entree['etat']] }}</p>
            </article>
        @empty
            <p>Aucun message reçu.</p>
        @endforelse
    @else
        <a href="{{ route('reseau.contact.console') }}">Messages de contact</a>
        <h1>{{ $sujets[$message['sujet']] }}</h1>
        <p>{{ $message['nom'] }} · {{ $message['email'] }}</p>
        <p class="texte-reseau">{{ $message['message'] }}</p>
        <form method="post" action="{{ route('reseau.contact.traiter', $message['reference']) }}">
            @csrf
            <button class="bouton" name="action" value="traiter">Marquer comme traité</button>
            <button class="bouton" name="action" value="supprimer">Supprimer</button>
        </form>
    @endif
</main>
</body>
</html>
