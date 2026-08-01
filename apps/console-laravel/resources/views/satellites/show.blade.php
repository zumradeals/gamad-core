@extends('layouts.console')

@section('title', $satellite['libelle'])

@section('content')
@php
    $reference = (string) $satellite['reference'];
    $jeton = session('jeton_federe');
    $identifiantLivre = session('identifiant_livre');
@endphp

<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.satellites.index') }}">Satellites</a>
    <span aria-hidden="true">/</span>
    <span>{{ $satellite['libelle'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($satellite['libelle'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">Produit de l’écosystème</p>
        <h1 class="detail-hero__title">{{ $satellite['libelle'] }}</h1>
        <div class="detail-hero__meta">
            <span class="status {{ $satellite['federable'] ? 'status--success' : 'status--warning' }}">
                {{ $satellite['federable'] ? 'Ouvrable' : 'Non entériné' }}
            </span>
            <span class="status {{ $identifiantsConfigures ? 'status--success' : 'status--warning' }}">
                {{ $identifiantsConfigures ? 'Identifiants Core configurés' : 'Identifiants Core à créer' }}
            </span>
        </div>
    </div>
    <span class="technical-reference">{{ $reference }}</span>
</section>

@if($errors->has('acces'))
    <div class="form-error" role="alert" style="margin-bottom:18px">{{ $errors->first('acces') }}</div>
@endif
@if($errors->has('identifiant'))
    <div class="form-error" role="alert" style="margin-bottom:18px">{{ $errors->first('identifiant') }}</div>
@endif

@if($identifiantLivre)
    <section class="card card--raised" style="margin-bottom:22px" aria-labelledby="secret-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="secret-titre">Secret de raccordement — notez-le maintenant</h2>
                <p class="card__description">
                    Il n’est affiché qu’ici et ne sera jamais réaffiché : le Core n’en conserve
                    qu’une empreinte irréversible. Si vous quittez cette page sans le noter,
                    il faudra en délivrer un autre.
                </p>
            </div>
        </div>
        <div class="card__body">
            <p class="technical-reference" style="word-break:break-all;font-size:16px;line-height:1.6">
                {{ $identifiantLivre['secret'] }}
            </p>
            <dl class="detail-list" style="margin-top:14px">
                <div class="detail-row">
                    <dt>Pour le produit</dt>
                    <dd class="technical-reference">{{ $identifiantLivre['produit'] }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Référence de l’identifiant</dt>
                    <dd class="technical-reference">{{ $identifiantLivre['reference'] }}</dd>
                </div>
            </dl>
            <div class="alert alert--danger" style="margin-top:16px">
                <span class="alert__dot" aria-hidden="true"></span>
                <span>
                    <span class="alert__title">Transmettez-le par un canal sûr</span>
                    <span class="alert__detail">
                        Ni e-mail, ni message instantané, ni capture d’écran. S’il fuite,
                        retirez-le depuis cette page : les sessions ouvertes avec lui tombent
                        immédiatement.
                    </span>
                </span>
            </div>
        </div>
    </section>
@endif

@if($jeton)
    <section class="card card--raised" style="margin-bottom:22px" aria-labelledby="jeton-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="jeton-titre">Jeton d’ouverture — affiché une seule fois</h2>
                <p class="card__description">
                    Il vaut {{ $dureeJeton }} secondes, ne sert qu’une fois, et n’ouvre que
                    {{ $satellite['libelle'] }}. En usage normal, le produit obtient ce jeton
                    automatiquement : celui-ci sert à vérifier le raccordement.
                </p>
            </div>
        </div>
        <div class="card__body">
            <p class="technical-reference" style="word-break:break-all;font-size:15px">{{ $jeton['jeton'] }}</p>
            <dl class="detail-list" style="margin-top:14px">
                <div class="detail-row">
                    <dt>Destiné à</dt>
                    <dd class="technical-reference">{{ $jeton['audience'] }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Pour la personne</dt>
                    <dd class="technical-reference">{{ $jeton['identite'] }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Expire le</dt>
                    <dd>{{ $jeton['expire_le'] }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Référence du jeton</dt>
                    <dd class="technical-reference">{{ $jeton['reference'] }}</dd>
                </div>
            </dl>
        </div>
    </section>
@endif

<div class="detail-grid">
    <section class="card span-7" aria-labelledby="raccordement-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="raccordement-titre">Fiche de raccordement</h2>
                <p class="card__description">Les informations à remettre à l’équipe du produit.</p>
            </div>
        </div>
        <div class="card__body">
            <dl class="detail-list">
                <div class="detail-row">
                    <dt>1. Référence du produit</dt>
                    <dd class="technical-reference">{{ $reference }}</dd>
                </div>
                <div class="detail-row">
                    <dt>2. Adresse du Core</dt>
                    <dd class="technical-reference">{{ $adresseApi }}</dd>
                </div>
                <div class="detail-row">
                    <dt>3. Secret de raccordement</dt>
                    <dd>
                        @if($identifiantsConfigures)
                            Déjà délivré. Il n’est jamais réaffiché : s’il est perdu, en délivrer
                            un nouveau puis retirer l’ancien, ci-dessous.
                        @else
                            À délivrer ci-dessous. Le Core l’engendre lui-même et ne le montre
                            qu’une fois.
                        @endif
                    </dd>
                </div>
                <div class="detail-row">
                    <dt>4. Les trois portes</dt>
                    <dd>
                        <span class="technical-reference">POST /produits/{{ $reference }}/ouverture</span><br>
                        <span class="technical-reference">POST /produits/{{ $reference }}/verification</span><br>
                        <span class="technical-reference">POST /produits/{{ $reference }}/revocation</span>
                    </dd>
                </div>
            </dl>
            <div class="alert" style="margin-top:18px">
                <span class="alert__dot" aria-hidden="true"></span>
                <span>
                    <span class="alert__title">Ce que le produit doit savoir</span>
                    <span class="alert__detail">
                        Le jeton qu’il reçoit vaut {{ $dureeJeton }} secondes, ne sert qu’une fois et
                        n’ouvre que chez lui. Le jour où un accès est révoqué ici, le jeton suivant
                        est refusé sans qu’il ait rien à faire.
                    </span>
                </span>
            </div>
        </div>
    </section>

    <section class="card span-5" aria-labelledby="ouvrir-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="ouvrir-titre">Ouvrir un accès</h2>
                <p class="card__description">Pour une personne déjà inscrite au registre des identités.</p>
            </div>
        </div>
        <div class="card__body">
            @if(! $satellite['federable'])
                <div class="alert alert--danger">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">Produit non entériné</span>
                        <span class="alert__detail">
                            Son état est « {{ $satellite['etat'] }} ». Le Core refuse d’ouvrir un accès
                            tant que l’écosystème ne l’a pas reconnu.
                        </span>
                    </span>
                </div>
            @elseif(! $peutAdministrer)
                <div class="alert">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">Administration réservée</span>
                        <span class="alert__detail">
                            Seuls ce produit et l’autorité d’inscription ouvrent ou révoquent un accès.
                        </span>
                    </span>
                </div>
            @else
                <form method="POST" action="{{ route('console.satellites.ouvrir', $reference) }}">
                    @csrf
                    <div class="field">
                        <label class="field-label" for="identite">Référence de la personne</label>
                        <input class="input"
                               id="identite"
                               name="identite"
                               type="text"
                               required
                               maxlength="64"
                               placeholder="IDN-PER-000000001"
                               value="{{ old('identite') }}">
                        <span class="field-help">
                            Elle se trouve sur la fiche de la personne, dans
                            <a href="{{ route('console.identites.index') }}">Identités</a>.
                        </span>
                    </div>
                    <div class="field">
                        <label class="field-label" for="relation_type">Niveau d’accès</label>
                        <select class="select" id="relation_type" name="relation_type" required>
                            @foreach($relations as $relation)
                                <option value="{{ $relation }}" @selected(old('relation_type', 'UTILISATEUR') === $relation)>
                                    {{ ucfirst(mb_strtolower(str_replace('_', ' ', $relation))) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="field-help">
                            Ce que la personne est pour ce produit. Le rôle métier détaillé reste chez lui.
                        </span>
                    </div>
                    <button class="button button--primary button--full" type="submit">Ouvrir l’accès</button>
                </form>
            @endif
        </div>
    </section>

    <section class="card span-12" aria-labelledby="identifiants-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="identifiants-titre">Identifiants de raccordement</h2>
                <p class="card__description">
                    Le secret avec lequel {{ $satellite['libelle'] }} s’authentifie auprès du Core.
                    Il ne donne aucun accès aux données d’une personne : il sert au produit à se
                    présenter, rien de plus.
                </p>
            </div>
            @if($peutDelivrer)
                <span class="status {{ $identifiantsConfigures ? 'status--success' : 'status--warning' }}">
                    {{ count($identifiants) }} / {{ $maxIdentifiants }} actif{{ count($identifiants) > 1 ? 's' : '' }}
                </span>
            @endif
        </div>
        <div class="card__body">
            @if(! $peutDelivrer)
                <div class="alert">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">Réservé à l’autorité d’inscription</span>
                        <span class="alert__detail">
                            Le raccordement est
                            {{ $identifiantsConfigures ? 'déjà fait pour ce produit' : 'à faire par l’autorité' }}.
                            Un satellite ne délivre pas ses propres identifiants.
                        </span>
                    </span>
                </div>
            @else
                @if($identifiants === [])
                    <div class="empty-state" style="padding:24px 18px">
                        <div class="empty-state__symbol" aria-hidden="true">⚿</div>
                        <h2>Aucun identifiant actif</h2>
                        <p>Ce produit ne peut pas encore se présenter au Core.</p>
                    </div>
                @else
                    <dl class="detail-list">
                        @foreach($identifiants as $identifiant)
                            <div class="detail-row">
                                <dt class="technical-reference">{{ $identifiant['reference'] }}</dt>
                                <dd style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
                                    <span>
                                        {{ $identifiant['delivre_par_console'] ? 'Délivré' : 'Créé en ligne de commande' }}
                                        le {{ substr((string) $identifiant['cree_le'], 0, 10) }}
                                    </span>
                                    <form method="POST"
                                          action="{{ route('console.satellites.retirer', $reference) }}"
                                          onsubmit="return confirm('Retirer cet identifiant ? {{ $satellite['libelle'] }} ne pourra plus se présenter au Core avec lui, et les sessions ouvertes avec lui seront fermées.');">
                                        @csrf
                                        <input type="hidden" name="authentificateur" value="{{ $identifiant['reference'] }}">
                                        <button class="button button--secondary" type="submit">Retirer</button>
                                    </form>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if(! $satellite['federable'])
                    <div class="alert alert--danger" style="margin-top:18px">
                        <span class="alert__dot" aria-hidden="true"></span>
                        <span>
                            <span class="alert__title">Produit non entériné</span>
                            <span class="alert__detail">
                                Le Core ne délivre pas la clé d’une porte qu’il refuse d’ouvrir.
                            </span>
                        </span>
                    </div>
                @elseif(count($identifiants) >= $maxIdentifiants)
                    <div class="alert" style="margin-top:18px">
                        <span class="alert__dot" aria-hidden="true"></span>
                        <span>
                            <span class="alert__title">Maximum atteint</span>
                            <span class="alert__detail">
                                {{ $maxIdentifiants }} identifiants actifs au plus. Retirez-en un avant
                                d’en délivrer un nouveau — c’est ce qui empêche un secret oublié de
                                rester valable indéfiniment.
                            </span>
                        </span>
                    </div>
                @else
                    <form method="POST"
                          action="{{ route('console.satellites.delivrer', $reference) }}"
                          style="margin-top:18px"
                          onsubmit="return confirm('Délivrer un secret de raccordement ? Il ne sera affiché qu’une seule fois.');">
                        @csrf
                        <button class="button button--primary" type="submit">
                            {{ $identifiants === [] ? 'Délivrer le secret' : 'Délivrer un secret de remplacement' }}
                        </button>
                    </form>
                    <p class="field-help" style="margin-top:10px">
                        Le Core engendre le secret ; vous ne le tapez pas. Il est affiché une seule
                        fois, conservé sous forme d’empreinte irréversible, et n’entre jamais au
                        journal d’audit. Session en cours : {{ $assuranceSession ?: 'assurance inconnue' }}.
                    </p>
                @endif
            @endif
        </div>
    </section>

    <section class="card span-12" aria-labelledby="porteurs-titre">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="porteurs-titre">Personnes ayant accès</h2>
                <p class="card__description">
                    Ouvrir deux fois le même accès ne crée pas un second compte : la ligne reste unique.
                </p>
            </div>
            @if($lisible)
                <span class="status">{{ count($porteurs) }} accès actif{{ count($porteurs) > 1 ? 's' : '' }}</span>
            @endif
        </div>
        <div class="card__body">
            @if(! $lisible)
                <div class="empty-state" style="padding:28px 18px">
                    <h2>Liste non lisible depuis cette session</h2>
                    <p>{{ $motifIllisible }}</p>
                </div>
            @elseif($porteurs === [])
                <div class="empty-state" style="padding:28px 18px">
                    <div class="empty-state__symbol" aria-hidden="true">⌕</div>
                    <h2>Personne n’a encore accès à ce produit</h2>
                    <p>Ouvrez un premier accès pour une personne inscrite au registre.</p>
                </div>
            @else
                <div class="identity-list">
                    @foreach($porteurs as $porteur)
                        <div class="identity-row" style="cursor:default">
                            <span class="identity-main">
                                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($porteur['libelle'], 0, 2) }}</span>
                                <span style="min-width:0">
                                    <span class="identity-name">{{ $porteur['libelle'] }}</span>
                                    <span class="technical-reference">{{ $porteur['identite'] }}</span>
                                </span>
                            </span>
                            <span>
                                <span class="identity-cell-label">Niveau d’accès</span>
                                {{ ucfirst(mb_strtolower(str_replace('_', ' ', $porteur['niveau_acces']))) }}
                            </span>
                            <span>
                                <span class="identity-cell-label">Depuis</span>
                                {{ $porteur['depuis'] }}
                            </span>
                            <span>
                                <span class="identity-cell-label">Dernière ouverture</span>
                                {{ $porteur['derniere_ouverture'] ? substr((string) $porteur['derniere_ouverture'], 0, 16) : 'Jamais' }}
                            </span>
                            @if($peutAdministrer)
                                <form method="POST"
                                      action="{{ route('console.satellites.revoquer', $reference) }}"
                                      onsubmit="return confirm('Révoquer l’accès de {{ $porteur['libelle'] }} à {{ $satellite['libelle'] }} ? Les jetons encore ouverts seront fermés. L’identité et les données du produit ne sont pas supprimées.');">
                                    @csrf
                                    <input type="hidden" name="identite" value="{{ $porteur['identite'] }}">
                                    <button class="button button--secondary" type="submit">Révoquer</button>
                                </form>
                            @else
                                <span aria-hidden="true"></span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
