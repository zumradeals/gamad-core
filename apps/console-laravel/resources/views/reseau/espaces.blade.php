<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projets et ZUMRA — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body>
<main class="conteneur section">
    <nav class="barre" aria-label="Réseau">
        <a href="{{ route('reseau.accueil') }}">GAMAD</a>
        <a href="{{ route('projets.index') }}">Projets</a>
        <a href="{{ route('zumras.index') }}">ZUMRA</a>
    </nav>
    @if ($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @switch($ecran)
        @case('zumras')
            <h1>Découvrir les ZUMRA</h1>
            <a class="bouton" href="{{ route('zumras.create') }}">Créer une ZUMRA</a>
            @forelse ($zumras as $entree)
                <h2><a href="{{ route('zumras.show', $entree['reference']) }}">{{ $entree['nom'] }}</a></h2>
            @empty
                <p>Aucune ZUMRA à afficher.</p>
            @endforelse
            @break
        @case('zumra')
            <h1>{{ $zumra['nom'] }}</h1>
            <p class="texte-reseau">{{ $zumra['presentation'] }}</p>
            @if ($connecte && !$fondateur)
                <form method="post" action="{{ route('zumras.adhesion', $zumra['reference']) }}">
                    @csrf
                    @if ($adhesion)
                        <p>{{ $adhesion === 'accepted' ? 'Membre de la ZUMRA' : 'Adhésion en attente' }}</p>
                        <button class="bouton" name="action" value="quitter">Quitter ou retirer la demande</button>
                    @else
                        <button class="bouton" name="action" value="rejoindre">{{ $mode_adhesion === 'open' ? 'Rejoindre la ZUMRA' : 'Demander à rejoindre' }}</button>
                    @endif
                </form>
            @endif
            @if ($responsable)
                <form class="formulaire-reseau" method="post" action="{{ route('zumras.mode-adhesion', $zumra['reference']) }}">
                    @csrf
                    <label for="mode-adhesion">Mode d’adhésion</label>
                    <select id="mode-adhesion" name="mode">
                        <option value="approval" @selected($mode_adhesion === 'approval')>Sur validation</option>
                        <option value="open" @selected($mode_adhesion === 'open')>Ouverte</option>
                    </select>
                    <button class="bouton">Enregistrer</button>
                </form>
                <section aria-labelledby="accueils">
                    <h2 id="accueils">Demandes d’accueil</h2>
                    @forelse ($demandes as $demande)
                        <form method="post" action="{{ route('zumras.accueil', [$zumra['reference'], $demande['reference']]) }}">
                            @csrf
                            <p>{{ $demande['titre'] }}</p>
                            <button class="bouton" name="decision" value="accepter">Accepter</button>
                            <button class="bouton" name="decision" value="refuser">Refuser</button>
                        </form>
                    @empty
                        <p>Aucune demande en attente.</p>
                    @endforelse
                </section>
                <h2>Adhésions</h2>
                @foreach ($membres as $membre)
                    <form class="barre" method="post" action="{{ route('zumras.membres', $zumra['reference']) }}">
                        @csrf
                        <input type="hidden" name="cible" value="{{ $membre['membre_reference'] }}">
                        <strong>{{ $membre['nom'] }}</strong>
                        @if ($membre['etat'] === 'pending')
                            <button class="bouton" name="action" value="accepter">Accepter l’adhésion</button>
                            <button class="bouton" name="action" value="refuser">Refuser</button>
                        @elseif ($fondateur || !$membre['delegue'])
                            <button class="bouton" name="action" value="retirer">Retirer le membre</button>
                        @endif
                    </form>
                    @if ($fondateur && $membre['etat'] === 'accepted')
                        <form method="post" action="{{ route('zumras.deleguer', $zumra['reference']) }}">
                            @csrf
                            <input type="hidden" name="cible" value="{{ $membre['membre_reference'] }}">
                            <button class="bouton" name="action" value="{{ $membre['delegue'] ? 'revoquer' : 'nommer' }}">
                                {{ $membre['delegue'] ? 'Retirer la responsabilité' : 'Nommer responsable' }}
                            </button>
                        </form>
                    @endif
                @endforeach
            @endif
            @break
        @case('projets')
            <h1>Projets GAMAD</h1>
            <a class="bouton" href="{{ route('projets.create') }}">Créer un projet</a>
            @forelse ($projets as $entree)
                <h2><a href="{{ route('projets.show', $entree['reference']) }}">{{ $entree['titre'] }}</a></h2>
            @empty
                <p>Aucun projet visible.</p>
            @endforelse
            @break
        @case('projet')
            <h1>{{ $projet['titre'] }}</h1>
            @if ($projet['prive'])
                <h2>Dossier privé</h2>
                <p class="texte-reseau">{{ $projet['dossier'] }}</p>
                <section aria-labelledby="etapes-projet">
                    <h2 id="etapes-projet">Étapes du projet</h2>
                    @foreach ($projet['etapes'] as $etape)
                        @if ($projet['porteur'])
                            <form class="formulaire-reseau" method="post" action="{{ route('projets.etapes', [$projet['reference'], $etape['reference']]) }}">
                                @csrf
                                <label for="titre-{{ $etape['reference'] }}">Étape {{ $etape['position'] }}</label>
                                <input id="titre-{{ $etape['reference'] }}" name="titre" value="{{ $etape['titre'] }}" maxlength="80" required>
                                <select name="etat" aria-label="État de l’étape">
                                    @foreach ($etatsEtape as $valeur => $libelle)
                                        <option value="{{ $valeur }}" @selected($etape['etat'] === $valeur)>{{ $libelle }}</option>
                                    @endforeach
                                </select>
                                <div>
                                    <button class="bouton" name="action" value="enregistrer">Enregistrer</button>
                                    @unless ($loop->first)<button class="bouton" name="action" value="monter">Monter</button>@endunless
                                    @unless ($loop->last)<button class="bouton" name="action" value="descendre">Descendre</button>@endunless
                                    <button class="bouton" name="action" value="retirer">Retirer</button>
                                </div>
                            </form>
                        @else
                            <p>{{ $etape['position'] }}. {{ $etape['titre'] }} · {{ $etatsEtape[$etape['etat']] }}</p>
                        @endif
                    @endforeach
                    @if ($projet['porteur'])
                        <form class="formulaire-reseau" method="post" action="{{ route('projets.etapes', $projet['reference']) }}">
                            @csrf
                            <label for="nouvelle-etape">Nouvelle étape</label>
                            <input id="nouvelle-etape" name="titre" maxlength="80" required>
                            <button class="bouton" type="submit">Ajouter l’étape</button>
                        </form>
                    @endif
                </section>
                <p>Accueil par la ZUMRA
                    {{ ['en_attente' => 'En attente', 'accepte' => 'Accepté', 'refuse' => 'Refusé'][$projet['accueil']] }}
                </p>
                @if ($projet['porteur'] && $projet['accueil'] === 'accepte')
                    <form class="formulaire-reseau" method="post" action="{{ route('projets.vitrine', $projet['reference']) }}">
                        @csrf
                        <label for="vitrine">Présentation publique</label>
                        <textarea id="vitrine" name="vitrine" maxlength="500">{{ old('vitrine', $projet['vitrine']) }}</textarea>
                        <button class="bouton accent" name="action" value="publier">Publier la présentation</button>
                        @if ($projet['vitrine'] !== null)
                            <button class="bouton" name="action" value="retirer">Retirer la présentation</button>
                        @endif
                    </form>
                @endif
                @if ($projet['porteur'])
                    <h2>Propositions d’aide</h2>
                    @forelse ($projet['participations'] as $demande)
                        <form class="barre" method="post" action="{{ route('projets.participations', $projet['reference']) }}">
                            @csrf
                            <input type="hidden" name="cible" value="{{ $demande['candidat_reference'] }}">
                            <strong>{{ $demande['nom'] }}</strong>
                            @if ($demande['message'])<p class="texte-reseau">{{ $demande['message'] }}</p>@endif
                            <button class="bouton" name="action" value="accepter">Accepter l’aide</button>
                            <button class="bouton" name="action" value="refuser">Refuser</button>
                        </form>
                    @empty
                        <p>Aucune proposition en attente.</p>
                    @endforelse
                    <h2>Équipe du projet</h2>
                    <form class="formulaire-reseau" method="post" action="{{ route('projets.inviter', $projet['reference']) }}">
                        @csrf
                        <label for="profil">Référence publique du profil à inviter</label>
                        <input id="profil" name="profil" maxlength="24" required>
                        <button class="bouton">Inviter</button>
                    </form>
                    @foreach ($projet['equipe'] as $membre)
                        <form class="barre" method="post" action="{{ route('projets.equipe', $projet['reference']) }}">
                            @csrf
                            <input type="hidden" name="cible" value="{{ $membre['membre_reference'] }}">
                            <span>{{ $membre['nom'] }} · {{ $membre['etat'] === 'pending' ? 'Invitation en attente' : 'Membre' }}</span>
                            <button class="bouton" name="action" value="retirer">Retirer</button>
                        </form>
                    @endforeach
                @endif
            @elseif ($projet['invitation'] ?? false)
                <p>Vous êtes invité à rejoindre ce projet.</p>
                <form method="post" action="{{ route('projets.equipe', $projet['reference']) }}">
                    @csrf
                    <button class="bouton" name="action" value="accepter">Accepter</button>
                    <button class="bouton" name="action" value="refuser">Refuser</button>
                </form>
            @else
                <p class="texte-reseau">{{ $projet['vitrine'] }}</p>
                @if ($projet['participation_autorisee'] && in_array($projet['participation'], [null, 'pending', 'withdrawn'], true))
                    <form class="formulaire-reseau" method="post" action="{{ route('projets.participations', $projet['reference']) }}">
                        @csrf
                        @if ($projet['participation'] === 'pending')
                            <p>Proposition d’aide en attente.</p>
                            <button class="bouton" name="action" value="retirer">Retirer ma proposition</button>
                        @else
                            <label for="message-aide">Un mot au porteur (facultatif)</label>
                            <textarea id="message-aide" name="message" maxlength="280">{{ old('message') }}</textarea>
                            <button class="bouton" name="action" value="proposer">Proposer mon aide</button>
                        @endif
                    </form>
                @endif
            @endif
            @break
        @case('creer-zumra')
            <h1>Créer une ZUMRA</h1>
            <form class="formulaire-reseau" method="post" action="{{ route('zumras.store') }}">
                @csrf
                <label for="nom">Nom</label>
                <input id="nom" name="nom" value="{{ old('nom') }}" maxlength="100" required>
                <label for="presentation">Présentation publique</label>
                <textarea id="presentation" name="presentation" maxlength="500" required>{{ old('presentation') }}</textarea>
                <button class="bouton accent">Créer</button>
            </form>
            @break
        @case('creer-projet')
            <h1>Créer un projet</h1>
            @if ($zumras)
                <form class="formulaire-reseau" method="post" action="{{ route('projets.store') }}">
                    @csrf
                    <label for="zumra">ZUMRA d’accueil</label>
                    <select id="zumra" name="zumra" required>
                        @foreach ($zumras as $entree)
                            <option value="{{ $entree['reference'] }}" @selected(old('zumra') === $entree['reference'])>{{ $entree['nom'] }}</option>
                        @endforeach
                    </select>
                    <label for="titre">Titre</label>
                    <input id="titre" name="titre" value="{{ old('titre') }}" maxlength="120" required>
                    <label for="dossier">Dossier privé</label>
                    <textarea id="dossier" name="dossier" maxlength="4000" required>{{ old('dossier') }}</textarea>
                    <button class="bouton accent">Créer le projet</button>
                </form>
            @else
                <p>Une ZUMRA est nécessaire pour accueillir le projet.</p>
                <a href="{{ route('zumras.create') }}">Créer une ZUMRA</a>
            @endif
            @break
    @endswitch
</main>
</body>
</html>
