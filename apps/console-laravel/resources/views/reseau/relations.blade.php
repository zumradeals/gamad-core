<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes relations — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <main class="conteneur section">
        <nav aria-label="Réseau">
            <a href="{{ route('compte.profil') }}">Mon profil</a>
            <a href="{{ route('personnes.index') }}">Annuaire</a>
            <a href="{{ route('notifications') }}">Notifications</a>
        </nav>
        <h1>Mes relations</h1>
        @forelse ($relations as $relation)
            <article class="section">
                <h2>{{ $relation['nom'] }}</h2>
                @if ($relation['etat'] === 'accepted')
                    <p>En relation</p>
                    <a href="{{ route('conversations.show', $relation['reference_publique']) }}">Ouvrir la conversation</a>
                    @php($actions = ['remove' => 'Retirer la relation'])
                @elseif ($relation['expediteur_reference'] === $sujet)
                    <p>Demande envoyée</p>
                    @php($actions = ['withdraw' => 'Retirer la demande'])
                @else
                    <p>Demande reçue</p>
                    @php($actions = ['accept' => 'Accepter', 'decline' => 'Refuser'])
                @endif
                @if ($relation['note'])<p class="texte-reseau">{{ $relation['note'] }}</p>@endif
                <form method="post" action="{{ route('relations.decider', $relation['reference_publique']) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="revision" value="{{ $relation['revision'] }}">
                    @foreach ($actions as $action => $libelle)
                        <button class="bouton" type="submit" name="action" value="{{ $action }}">{{ $libelle }}</button>
                    @endforeach
                </form>
            </article>
        @empty
            <p>Aucune demande ou relation en cours.</p>
        @endforelse
    </main>
</body>
</html>
