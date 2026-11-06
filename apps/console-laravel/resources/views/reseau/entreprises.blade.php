<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entreprises et opportunités — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
<main class="conteneur section">
    <nav class="barre" aria-label="Réseau">
        <a href="{{ route('reseau.accueil') }}">GAMAD</a>
        <a href="{{ route('entreprises.index') }}">Entreprises</a>
        <a href="{{ route('opportunites.index') }}">Opportunités</a>
    </nav>
    @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @switch($ecran)
        @case('entreprises')
            <h1>Entreprises GAMAD</h1>
            <a class="bouton" href="{{ route('entreprises.create') }}">Soumettre une entreprise</a>
            @forelse ($entreprises as $entree)
                <h2><a href="{{ route('entreprises.show', $entree['reference']) }}">{{ $entree['nom'] }}</a></h2>
                <p>{{ $entree['secteur'] }}</p>
            @empty
                <p>Aucune entreprise visible.</p>
            @endforelse
            @break
        @case('formulaire')
            <h1>Soumettre une entreprise</h1>
            <form class="formulaire-reseau" method="post" enctype="multipart/form-data" action="{{ route('entreprises.store') }}">
                @csrf
                <label for="nom">Nom de l’entreprise</label>
                <input id="nom" name="nom" maxlength="160" value="{{ old('nom') }}" required>
                <label for="pays">Code pays (deux lettres)</label>
                <input id="pays" name="pays" maxlength="2" value="{{ old('pays') }}" required>
                <label for="immatriculation">Numéro d’immatriculation</label>
                <input id="immatriculation" name="immatriculation" maxlength="100" value="{{ old('immatriculation') }}" required>
                <label for="secteur">Secteur</label>
                <input id="secteur" name="secteur" maxlength="120" value="{{ old('secteur') }}" required>
                @include('reseau.documents-entreprise')
                <button class="bouton accent">Soumettre pour vérification</button>
            </form>
            @break
        @case('revue')
            <h1>Vérification des entreprises</h1>
            @forelse ($entreprises as $entree)
                <article class="section">
                    <h2>{{ $entree['nom'] }}</h2>
                    <p>{{ $entree['pays'] }} · {{ $entree['immatriculation'] }} · {{ $entree['secteur'] }}</p>
                    <a href="{{ route('entreprises.justificatif', $entree['reference']) }}">Télécharger le RCCM</a>
                    @if ($entree['dfe'])<a href="{{ route('entreprises.justificatif', [$entree['reference'], 'dfe']) }}">Télécharger la DFE</a>@endif
                    @if ($entree['idu'])<a href="{{ route('entreprises.justificatif', [$entree['reference'], 'idu']) }}">Télécharger l’IDU</a>@endif
                    <form method="post" action="{{ route('entreprises.decider', $entree['reference']) }}">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $entree['revision'] }}">
                        @if ($entree['etat'] === 'pending')
                            <button class="bouton" name="action" value="verified">Vérifier</button>
                            <button class="bouton" name="action" value="declined">Refuser</button>
                        @else
                            <button class="bouton" name="action" value="suspended">Suspendre</button>
                        @endif
                    </form>
                </article>
            @empty
                <p>Aucune entreprise à examiner.</p>
            @endforelse
            @break
        @case('entreprise')
            <h1>{{ $entreprise['nom'] }}</h1>
            <p>{{ $entreprise['secteur'] }}</p>
            @if ($gestion)
                <p>{{ ['pending' => 'Vérification en attente', 'verified' => 'Entreprise vérifiée', 'declined' => 'Vérification refusée', 'suspended' => 'Entreprise suspendue'][$entreprise['etat']] }}</p>
                @if ($entreprise['etat'] === 'declined')
                    <form class="formulaire-reseau" method="post" enctype="multipart/form-data" action="{{ route('entreprises.resoumettre', $entreprise['reference']) }}">
                        @csrf
                        @include('reseau.documents-entreprise')
                        <button class="bouton accent">Soumettre à nouveau</button>
                    </form>
                @endif
                @if ($entreprise['etat'] === 'verified')
                    <form class="formulaire-reseau" method="post" action="{{ route('opportunites.store', $entreprise['reference']) }}">
                        @csrf
                        <label for="titre">Titre de l’opportunité</label>
                        <input id="titre" name="titre" maxlength="160" required>
                        <label for="description">Description</label>
                        <textarea id="description" name="description" maxlength="1800" required></textarea>
                        <button class="bouton accent">Publier l’opportunité</button>
                    </form>
                @endif
            @endif
            @foreach ($opportunites as $entree)
                <h2><a href="{{ route('opportunites.show', $entree['reference']) }}">{{ $entree['titre'] }}</a></h2>
            @endforeach
            @break
        @case('opportunites')
            <h1>Opportunités GAMAD</h1>
            @forelse ($opportunites as $entree)
                <h2><a href="{{ route('opportunites.show', $entree['reference']) }}">{{ $entree['titre'] }}</a></h2>
                <p>{{ $entree['nom'] }}</p>
            @empty
                <p>Aucune opportunité visible.</p>
            @endforelse
            @break
        @case('opportunite')
            <h1>{{ $opportunite['titre'] }}</h1>
            <p>{{ $opportunite['nom'] }} · {{ $opportunite['etat'] === 'published' ? 'Ouverte' : 'Clôturée' }}</p>
            <p class="texte-reseau">{{ $opportunite['description'] }}</p>
            @if ($peut_candidater && !$candidatures)
                <form class="formulaire-reseau" method="post" action="{{ route('opportunites.candidater', $opportunite['reference']) }}">
                    @csrf
                    <label for="message">Votre candidature</label>
                    <textarea id="message" name="message" maxlength="1000" required></textarea>
                    <button class="bouton accent">Envoyer la candidature</button>
                </form>
            @endif
            @if ($gestion && $opportunite['etat'] === 'published')
                <form method="post" action="{{ route('opportunites.cloturer', $opportunite['reference']) }}">
                    @csrf
                    <button class="bouton">Clôturer</button>
                </form>
            @endif
            @foreach ($candidatures as $candidature)
                <article><strong>{{ $candidature['candidat'] }}</strong><p class="texte-reseau">{{ $candidature['message'] }}</p></article>
            @endforeach
            @break
    @endswitch
</main>
</body>
</html>
