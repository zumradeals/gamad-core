@extends('layouts.console')

@section('title', 'Continuité')

@section('content')
@php
    $rapport = $etat['rapport'] ?? [];
    $operation = $rapport['derniere_operation'] ?? null;
    $exercice = $rapport['dernier_exercice'] ?? null;
    $phrase = session('phrase_chiffrement');
    $ageJours = null;
    if (! empty($rapport['derniere_copie'])) {
        $horodatage = substr((string) $rapport['derniere_copie'], 0, 15);
        $date = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', $horodatage, new \DateTimeZone('UTC'));
        $ageJours = $date === false ? null : (int) $date->diff(new \DateTimeImmutable('now'))->days;
    }
@endphp

<header class="page-header">
    <div>
        <p class="eyebrow">Sauvegarde et continuité</p>
        <h1 class="page-title">Continuité</h1>
        <p class="page-subtitle">
            Une sauvegarde qui vit sur le disque qu’elle protège ne protège de rien.
            Cet écran envoie une copie ailleurs, et vérifie qu’elle se relit.
        </p>
    </div>
</header>

@if($errors->has('continuite'))
    <div class="form-error" role="alert" style="margin-bottom:18px">{{ $errors->first('continuite') }}</div>
@endif

@if($phrase)
    <section class="card card--raised" style="margin-bottom:22px" aria-labelledby="phrase-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="phrase-titre">Phrase de déchiffrement — notez-la hors de ce serveur</h2>
                <p class="card__description">
                    Vos copies partent chiffrées avec cette phrase. Elle n’est affichée
                    qu’ici et ne sera jamais réaffichée.
                </p>
            </div>
        </div>
        <div class="card__body">
            <p class="technical-reference" style="word-break:break-all;font-size:16px;line-height:1.6">{{ $phrase }}</p>
            <div class="alert alert--danger" style="margin-top:16px">
                <span class="alert__dot" aria-hidden="true"></span>
                <span>
                    <span class="alert__title">Sans elle, vos sauvegardes sont illisibles</span>
                    <span class="alert__detail">
                        Le jour où ce serveur est perdu, cette phrase est la seule façon de
                        rouvrir les copies. Notez-la sur papier, ou dans un gestionnaire de
                        mots de passe — jamais sur cette machine.
                    </span>
                </span>
            </div>
        </div>
    </section>
@endif

@unless($etat['installe'])
    <section class="card card--raised" style="margin-bottom:22px">
        <div class="card__header">
            <div>
                <h2 class="card__title">Une installation reste à faire, une seule fois</h2>
                <p class="card__description">
                    La console et la sauvegarde tournent sous deux comptes différents. Elles
                    doivent partager un répertoire — c’est ce qui évite de donner à la console
                    le droit de lancer des commandes système.
                </p>
            </div>
        </div>
        <div class="card__body">
            <p>Faites exécuter cette commande une fois, en administrateur :</p>
            <p class="technical-reference" style="word-break:break-all;margin-top:10px">
                sudo /var/www/gamad-core/ops/core-foundation/installer-continuite.sh &amp;&amp;
                sudo systemctl reload php8.3-fpm
            </p>
            <p class="field-help" style="margin-top:10px">
                Elle ne déplace aucune donnée et ne configure aucune destination.
            </p>
        </div>
    </section>
@endunless

<div class="detail-grid">
    <section class="card span-7" aria-labelledby="etat-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="etat-titre">Où en sont vos sauvegardes</h2>
                <p class="card__description">Constaté sur le serveur, jamais déduit.</p>
            </div>
            <span class="status {{ $etat['configuree'] ? 'status--success' : 'status--warning' }}">
                {{ $etat['configuree'] ? 'Copie hors machine active' : 'Copies sur le serveur seulement' }}
            </span>
        </div>
        <div class="card__body">
            @unless($etat['configuree'])
                <div class="alert alert--danger" style="margin-bottom:16px">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">Toutes vos copies sont sur le disque qu’elles protègent</span>
                        <span class="alert__detail">
                            Une panne de ce disque emporterait les données et leurs sauvegardes.
                            Configurez une destination ci-contre.
                        </span>
                    </span>
                </div>
            @endunless

            <dl class="detail-list">
                <div class="detail-row">
                    <dt>Sauvegardes sur ce serveur</dt>
                    <dd>{{ $rapport['lots_locaux'] ?? 0 }} — dernière : {{ $rapport['dernier_lot_local'] ?? 'aucune' }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Copies envoyées ailleurs</dt>
                    <dd>
                        {{ $rapport['copies_hors_machine'] ?? 0 }}
                        @if($ageJours !== null)
                            — la plus récente date de {{ $ageJours === 0 ? "moins d'un jour" : $ageJours.' jour(s)' }}
                        @endif
                    </dd>
                </div>
                @if($etat['configuree'])
                    <div class="detail-row">
                        <dt>Destination</dt>
                        <dd class="technical-reference">{{ $etat['destination'] }}</dd>
                    </div>
                    <div class="detail-row">
                        <dt>Protection du transfert</dt>
                        <dd>
                            @if($etat['tls'] === 'exige')
                                Chiffré, exigé — le transfert échoue si le serveur ne le propose pas.
                            @elseif($etat['tls'] === 'aucun')
                                Aucune. Le mot de passe circule en clair sur le réseau.
                            @else
                                Chiffré si le serveur l’accepte, en clair sinon.
                            @endif
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Copies conservées</dt>
                        <dd>{{ $etat['retention'] }}</dd>
                    </div>
                @endif
                <div class="detail-row">
                    <dt>Dernière opération</dt>
                    <dd>
                        @if(is_array($operation))
                            <span class="status {{ ($operation['resultat'] ?? '') === 'succes' ? 'status--success' : 'status--danger' }}">
                                {{ ($operation['resultat'] ?? '') === 'succes' ? 'Réussie' : 'Échouée' }}
                            </span>
                            {{ $operation['action'] ?? '' }} — {{ $operation['le'] ?? '' }}
                        @else
                            Aucune depuis cet écran.
                        @endif
                    </dd>
                </div>
                <div class="detail-row">
                    <dt>Dernier exercice de restauration</dt>
                    <dd>
                        @if(is_array($exercice))
                            <span class="status {{ ($exercice['resultat'] ?? '') === 'succes' ? 'status--success' : 'status--danger' }}">
                                {{ ($exercice['resultat'] ?? '') === 'succes' ? 'Réussi' : 'Échoué' }}
                            </span>
                            {{ $exercice['le'] ?? '' }}
                        @else
                            Jamais éprouvé depuis cet écran.
                        @endif
                    </dd>
                </div>
            </dl>

            @if(is_array($operation) && ($operation['resultat'] ?? '') !== 'succes' && ! empty($operation['detail']))
                <div class="alert alert--danger" style="margin-top:16px">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">Ce que le serveur a répondu</span>
                        <span class="alert__detail">{{ $operation['detail'] }}</span>
                    </span>
                </div>
            @endif

            @if($etat['demandes_en_attente'] !== [])
                <div class="alert" style="margin-top:16px">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">Opération en cours</span>
                        <span class="alert__detail">
                            {{ implode(', ', $etat['demandes_en_attente']) }} — rechargez la page
                            dans quelques instants.
                        </span>
                    </span>
                </div>
            @endif

            @if($peutAdministrer && $etat['installe'])
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px">
                    <form method="POST" action="{{ route('console.continuite.declencher', 'sauvegarde') }}">
                        @csrf
                        <button class="button button--primary" type="submit">Sauvegarder maintenant</button>
                    </form>
                    <form method="POST"
                          action="{{ route('console.continuite.declencher', 'exercice') }}"
                          onsubmit="return confirm('Lancer un exercice de restauration ? Il relit la copie distante sur des bases isolées. Vos données de production ne sont pas touchées.');">
                        @csrf
                        <button class="button button--secondary"
                                type="submit"
                                @disabled(! $etat['configuree'])>
                            Éprouver la restauration
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </section>

    <section class="card span-5" aria-labelledby="destination-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="destination-titre">Où envoyer les copies</h2>
                <p class="card__description">Votre espace de sauvegarde FTP.</p>
            </div>
        </div>
        <div class="card__body">
            @unless($peutAdministrer)
                <div class="alert">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">Réservé à l’autorité d’inscription</span>
                        <span class="alert__detail">
                            Vous êtes connecté en tant que
                            <span class="technical-reference">{{ $acteur }}</span>.
                        </span>
                    </span>
                </div>
            @else
                <form method="POST" action="{{ route('console.continuite.configurer') }}">
                    @csrf
                    <div class="field">
                        <label class="field-label" for="hote">Adresse du serveur</label>
                        <input class="input" id="hote" name="hote" type="text" required
                               maxlength="255" placeholder="ftp.mon-hebergeur.com"
                               value="{{ old('hote', $etat['configuree'] ? parse_url((string) $etat['destination'], PHP_URL_HOST) : '') }}">
                    </div>
                    <div class="field">
                        <label class="field-label" for="chemin">Dossier sur ce serveur</label>
                        <input class="input" id="chemin" name="chemin" type="text"
                               maxlength="255" placeholder="gamad-core"
                               value="{{ old('chemin', $etat['configuree'] ? trim((string) parse_url((string) $etat['destination'], PHP_URL_PATH), '/') : '') }}">
                        <span class="field-help">Laissez vide pour déposer à la racine.</span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="utilisateur">Identifiant</label>
                        <input class="input" id="utilisateur" name="utilisateur" type="text" required
                               maxlength="128" autocomplete="off"
                               value="{{ old('utilisateur', $etat['utilisateur']) }}">
                    </div>
                    <div class="field">
                        <label class="field-label" for="secret">Mot de passe</label>
                        <input class="input" id="secret" name="secret" type="password"
                               maxlength="512" autocomplete="new-password"
                               @required(! $etat['secret_present'])>
                        <span class="field-help">
                            @if($etat['secret_present'])
                                Déjà enregistré. Laissez vide pour le conserver.
                            @else
                                Il sera conservé sur ce serveur pour être rejoué chaque nuit,
                                et jamais réaffiché.
                            @endif
                        </span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="tls">Protection du transfert</label>
                        <select class="select" id="tls" name="tls" required>
                            @foreach($modesTls as $mode)
                                <option value="{{ $mode }}" @selected(old('tls', $etat['tls']) === $mode)>
                                    @if($mode === 'exige') Chiffrement obligatoire (le plus sûr)
                                    @elseif($mode === 'opportuniste') Chiffrer si possible (recommandé)
                                    @else Aucune protection
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <span class="field-help">
                            Vos copies partent chiffrées dans tous les cas. Ce réglage protège
                            en plus votre mot de passe pendant le transfert.
                        </span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="retention">Copies à conserver</label>
                        <input class="input" id="retention" name="retention" type="number"
                               min="1" max="365" required
                               value="{{ old('retention', $etat['retention']) }}">
                    </div>
                    <button class="button button--primary button--full" type="submit">
                        Enregistrer la destination
                    </button>
                </form>
                <p class="field-help" style="margin-top:12px">
                    Après enregistrement, lancez une sauvegarde et un exercice de restauration :
                    c’est la seule preuve que votre hébergeur accepte réellement ce transfert.
                </p>
            @endunless
        </div>
    </section>
</div>
@endsection
