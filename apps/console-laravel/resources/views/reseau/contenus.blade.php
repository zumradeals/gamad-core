<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($publication) ? 'Publication' : 'Fil' }} — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
    <header class="bandeau">
        <div class="conteneur barre">
            <a class="marque" href="{{ route('reseau.accueil') }}">GAMAD</a>
            <nav aria-label="Réseau">
                <a href="{{ route('fil') }}">Fil</a>
                <a href="{{ route('reseau.decouvrir') }}">Découvrir</a>
                @unless (isset($publication))
                    <a href="{{ route('reseau.agir') }}">Agir</a>
                @endunless
                <a href="{{ route('compte.profil') }}">Mon profil</a>
            </nav>
        </div>
    </header>
    <main class="conteneur section">
        @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        @isset($publication)
            <h1>{{ $types[$publication['type']] }}</h1>
            <p><strong>{{ $publication['auteur'] }}</strong></p>
            <p class="texte-reseau">{{ $publication['contenu'] }}</p>
            @if (session('signalement_envoye'))<p role="status">Signalement transmis.</p>@endif
            @if ($peut_commenter)@include('reseau.signalement')@endif
            <section aria-labelledby="commentaires">
                <h2 id="commentaires">Commentaires</h2>
                @if ($peut_commenter)
                    <form method="post" action="{{ route('publications.commenter', $publication['reference']) }}" class="formulaire-reseau">
                        @csrf
                        <label for="contenu">Votre commentaire</label>
                        <textarea name="contenu" id="contenu" maxlength="500" rows="3" required>{{ old('parent') === null ? old('contenu') : '' }}</textarea>
                        <button class="bouton accent" type="submit">Commenter</button>
                    </form>
                @endif
                @forelse ($commentaires as $commentaire)
                    <article id="commentaire-{{ $commentaire['reference'] }}">
                        @include('reseau.commentaire', ['ligne' => $commentaire])
                        @if ($peut_commenter && $commentaire['retire_le'] === null)
                            <details @if (old('parent') === $commentaire['reference']) open @endif>
                                <summary>Répondre</summary>
                                <form method="post" action="{{ route('publications.commenter', $publication['reference']) }}" class="formulaire-reseau">
                                    @csrf
                                    <input type="hidden" name="parent" value="{{ $commentaire['reference'] }}">
                                    <label for="reponse-{{ $commentaire['reference'] }}">Votre réponse</label>
                                    <textarea name="contenu" id="reponse-{{ $commentaire['reference'] }}" maxlength="500" rows="3" required>{{ old('parent') === $commentaire['reference'] ? old('contenu') : '' }}</textarea>
                                    <button class="bouton accent" type="submit">Envoyer la réponse</button>
                                </form>
                            </details>
                        @endif
                        @isset ($reponses[$commentaire['reference']])
                            <ul aria-label="Réponses au commentaire">
                                @foreach ($reponses[$commentaire['reference']] as $reponse)
                                    <li id="commentaire-{{ $reponse['reference'] }}">
                                        @include('reseau.commentaire', ['ligne' => $reponse])
                                    </li>
                                @endforeach
                            </ul>
                        @endisset
                    </article>
                @empty
                    <p>Aucun commentaire.</p>
                @endforelse
            </section>
        @else
            <h1>Fil GAMAD</h1>
            <form id="publier" method="post" action="{{ route('publications.store') }}" class="formulaire-reseau">
                @csrf
                <label for="type">Type de publication</label>
                <select id="type" name="type">
                    @foreach ($types as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(old('type', $typeInitial) === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
                <label for="visibilite">Visibilité</label>
                <select id="visibilite" name="visibilite">
                    <option value="members">Membres vérifiés</option>
                    <option value="public" @selected(old('visibilite') === 'public')>Public</option>
                </select>
                <label for="contenu">Votre publication</label>
                <textarea name="contenu" id="contenu" maxlength="500" rows="4" required>{{ old('contenu') }}</textarea>
                <button class="bouton accent" type="submit">Publier</button>
            </form>
            @forelse ($publications as $entree)
                <article class="section">
                    <h2><a href="{{ route('publications.show', $entree['reference']) }}">{{ $types[$entree['type']] }}</a></h2>
                    <strong>{{ $entree['auteur'] }}</strong>
                    <p class="texte-reseau">{{ $entree['contenu'] }}</p>
                </article>
            @empty
                <p>Aucune publication visible sur cette page.</p>
            @endforelse
            @if ($page > 1)<a href="{{ route('fil', ['page' => $page - 1]) }}">Page précédente</a>@endif
            @if ($page_suivante)<a href="{{ route('fil', ['page' => $page + 1]) }}">Page suivante</a>@endif
        @endisset
    </main>
</body>
</html>
