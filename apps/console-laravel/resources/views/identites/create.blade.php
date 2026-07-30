@extends('layouts.console')

@section('title', 'Nouvelle identité')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.identites.index') }}">Identités</a>
    <span aria-hidden="true">/</span>
    <span>Nouvelle identité</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Inscription gouvernée</p>
        <h1 class="page-title">Nouvelle identité</h1>
        <p class="page-subtitle">
            Le Core vérifiera votre autorisation et votre mandat, puis produira une preuve avant toute écriture.
        </p>
    </div>
</header>

@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

@if(!$inscriptionDisponible)
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span>
        <span>
            L’inscription est actuellement fermée pour cette session.
            {{ $decision['motif'] ?? 'Aucune politique ne permet cette action.' }}
        </span>
    </div>
@endif

<form method="POST"
      action="{{ route('console.identites.store') }}"
      data-identity-form
      class="form-layout">
    @csrf

    <div class="card">
        <section class="form-section" aria-labelledby="type-title">
            <div>
                <p class="eyebrow">Étape 1</p>
                <h2 class="form-section__title" id="type-title">Qui ou quoi inscrivez-vous ?</h2>
            </div>
            <div class="type-grid">
                @foreach([
                    'personne' => ['◯', 'Personne'],
                    'organisation' => ['◇', 'Organisation'],
                    'produit' => ['▣', 'Produit'],
                    'realm' => ['◎', 'Realm'],
                    'agent' => ['✦', 'Agent'],
                    'service' => ['⌁', 'Service'],
                ] as $valeur => [$symbole, $libelle])
                    <div class="type-option">
                        <input id="type-{{ $valeur }}"
                               name="type"
                               type="radio"
                               value="{{ $valeur }}"
                               @checked(old('type', 'personne') === $valeur)>
                        <label for="type-{{ $valeur }}">
                            <span class="type-option__symbol" aria-hidden="true">{{ $symbole }}</span>
                            <span class="type-option__label">{{ $libelle }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="form-section" aria-labelledby="identity-title">
            <div>
                <p class="eyebrow">Étape 2</p>
                <h2 class="form-section__title" id="identity-title">Informations essentielles</h2>
            </div>
            <div class="field">
                <label for="libelle">Nom ou libellé</label>
                <input class="input"
                       id="libelle"
                       name="libelle"
                       value="{{ old('libelle') }}"
                       maxlength="256"
                       autocomplete="off"
                       required
                       placeholder="Ex. Aïssata Koné">
                <p class="field-help">Utilisez le nom permettant de reconnaître clairement cette identité.</p>
            </div>
            <div class="field">
                <label for="classification">Visibilité des données</label>
                <select class="select" id="classification" name="classification" required>
                    @foreach([
                        'INTERNE' => 'Interne — visible dans le Core',
                        'PUBLIC_ECOSYSTEME' => 'Public dans l’écosystème',
                        'CONFIDENTIEL' => 'Confidentiel',
                        'RESTREINT' => 'Restreint',
                        'SECRET_CORE' => 'Secret Core',
                    ] as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(old('classification', 'INTERNE') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <label class="check-row">
                <input name="provisoire" type="checkbox" value="1" @checked(old('provisoire'))>
                <span>
                    <strong>Identité provisoire</strong>
                    <span class="field-help" style="display:block">Son assurance restera limitée tant qu’elle n’aura pas été vérifiée.</span>
                </span>
            </label>
        </section>
    </div>

    <aside class="form-aside">
        <section class="card card--raised">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Avant confirmation</h2>
                    <p class="card__description">Résumé de l’écriture qui sera demandée.</p>
                </div>
            </div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row">
                        <dt>Nom</dt>
                        <dd data-summary-name>À renseigner</dd>
                    </div>
                    <div class="summary-row">
                        <dt>Type</dt>
                        <dd data-summary-type>Personne</dd>
                    </div>
                    <div class="summary-row">
                        <dt>Canal</dt>
                        <dd data-summary-channel>Autorité</dd>
                    </div>
                    <div class="summary-row">
                        <dt>Classification</dt>
                        <dd data-summary-classification>Interne</dd>
                    </div>
                    <div class="summary-row">
                        <dt>État initial</dt>
                        <dd data-summary-state>Active</dd>
                    </div>
                    <div class="summary-row">
                        <dt>Assurance</dt>
                        <dd>A3 sous autorité</dd>
                    </div>
                    <div class="summary-row">
                        <dt>Politique</dt>
                        <dd class="technical-reference">{{ $decision['politique'] ?? 'Non résolue' }}</dd>
                    </div>
                </dl>
                <div class="alert {{ $inscriptionDisponible ? 'alert--success' : 'alert--danger' }}" style="margin-top:18px">
                    <span class="alert__dot" aria-hidden="true"></span>
                    <span>
                        <span class="alert__title">{{ $inscriptionDisponible ? 'Prêt à inscrire' : 'Inscription fermée' }}</span>
                        <span class="alert__detail">
                            {{ $inscriptionDisponible ? 'Mandat actif et politique vérifiée.' : 'Aucune écriture ne sera tentée.' }}
                        </span>
                    </span>
                </div>
                <button class="button button--primary button--full"
                        type="submit"
                        style="margin-top:16px"
                        @disabled(!$inscriptionDisponible)>
                    Confirmer l’inscription
                </button>
                <p class="field-help" style="margin-top:12px;text-align:center">
                    Une preuve chaînée sera produite avant l’écriture.
                </p>
            </div>
        </section>
    </aside>
</form>
@endsection
