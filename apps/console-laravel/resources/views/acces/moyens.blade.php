@extends('layouts.console')

@section('title', 'Mon accès')

@section('content')
@php
    $codes = session('codes_secours');
    $moyens = $inventaire['moyens'] ?? [];
    $forts = array_filter($moyens, static fn (array $m): bool => $m['fort']);
    $restants = (int) ($inventaire['codes_restants'] ?? 0);
    $seul = count($moyens) <= 1 && $restants === 0;
    $libellesTypes = [
        'mot_de_passe' => 'Mot de passe',
        'passkey_webauthn' => 'Passkey',
        'secret_raccordement_satellite' => 'Secret de raccordement',
    ];
@endphp

<header class="page-header">
    <div>
        <p class="eyebrow">Vos moyens d’entrer</p>
        <h1 class="page-title">Mon accès</h1>
        <p class="page-subtitle">
            Ce que vous possédez pour ouvrir le Core. Tant qu’il n’y en a qu’un,
            le perdre ferme la porte à tout le monde.
        </p>
    </div>
</header>

@if($errors->has('acces'))
    <div class="form-error" role="alert" style="margin-bottom:18px">{{ $errors->first('acces') }}</div>
@endif

@if($codes)
    <section class="card card--raised" style="margin-bottom:22px" aria-labelledby="codes-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="codes-titre">Vos codes de secours — notez-les maintenant</h2>
                <p class="card__description">
                    Affichés une seule fois. Chacun ouvre une session, une fois, puis cesse
                    d’exister. Ils remplacent votre mot de passe le jour où vous le perdez.
                </p>
            </div>
        </div>
        <div class="card__body">
            <div class="type-grid">
                @foreach($codes as $code)
                    <span class="technical-reference" style="font-size:16px;letter-spacing:.04em">{{ $code }}</span>
                @endforeach
            </div>
            <div class="alert alert--danger" style="margin-top:16px">
                <span class="alert__dot" aria-hidden="true"></span>
                <span>
                    <span class="alert__title">Rangez-les hors de ce serveur</span>
                    <span class="alert__detail">
                        Sur papier, dans un coffre, ou dans un gestionnaire de mots de passe.
                        Ni dans un e-mail, ni sur cette machine. Le Core n’en garde qu’une
                        empreinte : personne, pas même lui, ne peut vous les redonner.
                    </span>
                </span>
            </div>
        </div>
    </section>
@endif

@if($seul)
    <div class="alert alert--danger" style="margin-bottom:22px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span>
            <span class="alert__title">Vous n’avez qu’un seul moyen d’entrer</span>
            <span class="alert__detail">
                Si vous le perdez, plus personne n’ouvre le Core — ni vous, ni un satellite,
                ni un secours. Engendrez vos codes de secours ci-dessous : c’est deux minutes.
            </span>
        </span>
    </div>
@endif

<div class="detail-grid">
    <section class="card span-7" aria-labelledby="moyens-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="moyens-titre">Ce que vous possédez</h2>
                <p class="card__description">
                    Identité <span class="technical-reference">{{ $entite }}</span>.
                </p>
            </div>
            <span class="status {{ $forts !== [] ? 'status--success' : 'status--warning' }}">
                {{ $forts !== [] ? 'Facteur fort en place' : 'Aucun facteur fort' }}
            </span>
        </div>
        <div class="card__body">
            @unless($inventaire['disponible'] ?? false)
                <div class="empty-state" style="padding:24px 18px">
                    <h2>Magasin d’accès indisponible</h2>
                    <p>Impossible de lire vos moyens d’accès pour l’instant.</p>
                </div>
            @else
                <dl class="detail-list">
                    @foreach($moyens as $moyen)
                        <div class="detail-row">
                            <dt>
                                {{ $libellesTypes[$moyen['type']] ?? $moyen['type'] }}
                                @if($moyen['libelle'])
                                    — {{ $moyen['libelle'] }}
                                @endif
                            </dt>
                            <dd style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
                                <span class="status {{ $moyen['fort'] ? 'status--success' : '' }}">
                                    {{ $moyen['fort'] ? 'Fort' : 'Simple' }}
                                </span>
                                <span>depuis le {{ substr((string) $moyen['cree_le'], 0, 10) }}</span>
                                <form method="POST"
                                      action="{{ route('console.acces.retirer') }}"
                                      onsubmit="return confirm('Retirer ce moyen d’accès ? Les sessions ouvertes avec lui seront fermées.');">
                                    @csrf
                                    <input type="hidden" name="reference" value="{{ $moyen['reference'] }}">
                                    <button class="button button--secondary" type="submit">Retirer</button>
                                </form>
                            </dd>
                        </div>
                    @endforeach
                    <div class="detail-row">
                        <dt>Codes de secours utilisables</dt>
                        <dd>
                            <span class="status {{ $restants > 0 ? 'status--success' : 'status--danger' }}">
                                {{ $restants }}
                            </span>
                            @if($restants === 0) aucun second chemin @endif
                        </dd>
                    </div>
                </dl>
                <p class="field-help" style="margin-top:12px">
                    Le Core refuse de retirer votre dernier moyen d’accès. C’est ce qui vous
                    empêche de vous enfermer dehors.
                </p>
            @endunless
        </div>
    </section>

    <section class="card span-5" aria-labelledby="renforcer-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="renforcer-titre">Renforcer votre accès</h2>
                <p class="card__description">Deux gestes, dans cet ordre.</p>
            </div>
        </div>
        <div class="card__body">
            <form method="POST"
                  action="{{ route('console.acces.codes') }}"
                  onsubmit="return confirm('Engendrer de nouveaux codes de secours ? Les codes précédents cesseront immédiatement de valoir.');">
                @csrf
                <p style="margin-bottom:10px"><strong>1. Vos codes de secours</strong></p>
                <p class="field-help" style="margin-bottom:12px">
                    Huit codes à usage unique. Ils ouvrent une session si vous perdez votre
                    mot de passe. Engendrer un nouveau jeu annule le précédent.
                </p>
                <button class="button button--primary button--full" type="submit">
                    {{ $restants > 0 ? 'Engendrer de nouveaux codes' : 'Engendrer mes codes de secours' }}
                </button>
            </form>

            <hr style="margin:22px 0;border:none;border-top:1px solid rgba(128,128,128,.25)">

            <form method="POST" action="{{ route('console.acces.passkey') }}">
                @csrf
                <p style="margin-bottom:10px"><strong>2. Une passkey</strong></p>
                <p class="field-help" style="margin-bottom:12px">
                    Votre empreinte, votre visage ou votre clé physique. C’est le moyen le plus
                    sûr, et il ne se recopie pas.
                </p>
                @if(str_contains($assurance, 'A2'))
                    <p class="field-help" style="margin-bottom:12px">
                        Votre session est déjà forte : aucun code n’est demandé pour ajouter
                        un appareil de plus.
                    </p>
                @else
                    <div class="field">
                        <label class="field-label" for="code_secours">Un code de secours</label>
                        <input class="input"
                               id="code_secours"
                               name="code_secours"
                               type="text"
                               autocomplete="off"
                               maxlength="64"
                               placeholder="XXXX-XXXX-XXXX-XXXX"
                               @required($restants > 0)>
                        <span class="field-help">
                            Un mot de passe seul n’attache pas un facteur fort : quelqu’un qui
                            vous l’aurait volé pourrait sinon s’installer chez vous
                            définitivement. Le code prouve que c’est bien vous.
                            @if($restants === 0)
                                <br><strong>Engendrez d’abord vos codes de secours.</strong>
                            @endif
                        </span>
                    </div>
                @endif
                <button class="button button--secondary button--full"
                        type="submit"
                        @disabled(! str_contains($assurance, 'A2') && $restants === 0)>
                    Ajouter une passkey
                </button>
            </form>
        </div>
    </section>
</div>
@endsection
