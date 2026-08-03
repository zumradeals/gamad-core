@extends('layouts.console')

@section('title', $organisation['revision']['denomination_officielle'] ?? $organisation['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.organisations.index') }}">Organisations</a>
    <span aria-hidden="true">/</span>
    <span>{{ $organisation['revision']['denomination_officielle'] ?? $organisation['reference'] }}</span>
</nav>

@if(session('succes'))
    <div class="alert alert--success" style="margin-bottom:18px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span><span class="alert__title">{{ session('succes') }}</span>
        @if(session('preuve'))
            <span class="alert__detail technical-reference">preuve {{ session('preuve')['reference'] ?? '' }}</span>
        @endif
        </span>
    </div>
@endif
@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span><span>{{ $errors->first() }}</span>
    </div>
@endif

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $organisation['type_organisation_reference'] }} — identité canonique {{ $organisation['identite_reference'] }}</p>
        <h1 class="page-title">{{ $organisation['revision']['denomination_officielle'] ?? '(aucune révision)' }}</h1>
        <p class="page-subtitle technical-reference">{{ $organisation['reference'] }}</p>
    </div>
    <span class="status {{ $organisation['etat'] === 'ACTIVE' ? 'status--success' : (in_array($organisation['etat'], ['DISSOUTE','RETIREE']) ? 'status--danger' : 'status--warning') }}">
        {{ $organisation['etat'] }}
    </span>
</header>

@if($autorite)
<div class="action-bar">
    @if(in_array($organisation['etat'], ['PREPARATION','SUSPENDUE']))
        <form method="POST" action="{{ route('console.organisations.activer', $organisation['reference']) }}">
            @csrf<button class="button button--primary" type="submit">Activer</button>
        </form>
    @endif
    @if($organisation['etat'] === 'ACTIVE')
        <form method="POST" action="{{ route('console.organisations.suspendre', $organisation['reference']) }}">
            @csrf<button class="button" type="submit">Suspendre</button>
        </form>
    @endif
    @if(in_array($organisation['etat'], ['ACTIVE','SUSPENDUE']))
        <form method="POST" action="{{ route('console.organisations.dissoudre', $organisation['reference']) }}"
              onsubmit="return confirm('Dissoudre définitivement cette organisation ?');">
            @csrf<button class="button button--danger" type="submit">Dissoudre</button>
        </form>
    @endif
    @if($organisation['etat'] !== 'RETIREE')
        <form method="POST" action="{{ route('console.organisations.retirer', $organisation['reference']) }}"
              onsubmit="return confirm('Retirer cette organisation du registre ?');">
            @csrf
            <input type="hidden" name="motif" value="retrait décidé depuis la console">
            <button class="button button--danger" type="submit">Retirer</button>
        </form>
    @endif
</div>
@endif

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Fiche organisationnelle</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Nom court</dt><dd>{{ $organisation['revision']['nom_court'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Nom commercial</dt><dd>{{ $organisation['revision']['nom_commercial'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Forme</dt><dd>{{ $organisation['revision']['forme_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Personnalité juridique</dt><dd>{{ $organisation['personnalite_juridique'] ? 'oui' : 'non vérifiée' }}</dd></div>
                <div class="summary-row"><dt>Classification</dt><dd>{{ $organisation['revision']['classification_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Propriétaire</dt><dd class="technical-reference">{{ $organisation['proprietaire_reference'] }}</dd></div>
                <div class="summary-row"><dt>Source</dt><dd>{{ $organisation['source_reference'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Identifiants externes</h2></div>
        <div class="card__body">
            @forelse($identifiants as $identifiant)
                <div class="summary-row">
                    <dt>{{ $identifiant['systeme_reference'] }} — {{ $identifiant['type_identifiant_reference'] }}</dt>
                    <dd>{{ $identifiant['valeur_normalisee'] }} — {{ $identifiant['verifie'] ? 'vérifié' : 'non vérifié' }}</dd>
                </div>
            @empty
                <p class="muted">Aucun identifiant externe déclaré.</p>
            @endforelse
            @if($autorite)
            <form method="POST" action="{{ route('console.organisations.identifiants.declarer', $organisation['reference']) }}" class="inline-form">
                @csrf
                <input class="input" name="systeme_reference" placeholder="Système" required>
                <select class="select" name="type_identifiant_reference" required>
                    @foreach($typesIdentifiant as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
                <input class="input" name="valeur_normalisee" placeholder="Valeur" required>
                <button class="button" type="submit">Déclarer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Structure et unités</h2></div>
        <div class="card__body">
            @forelse($structure as $unite)
                <div class="summary-row">
                    <dt>{{ $unite['nom'] }} <span class="technical-reference">{{ $unite['reference'] }}</span></dt>
                    <dd>{{ $unite['type_unite_reference'] }} — {{ $unite['etat'] }}
                        @if($unite['unite_parente_reference']) (sous {{ $unite['unite_parente_reference'] }}) @endif
                    </dd>
                </div>
            @empty
                <p class="muted">Aucune unité déclarée.</p>
            @endforelse
            @if($autorite && $organisation['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.organisations.unites.creer', $organisation['reference']) }}" class="inline-form">
                @csrf
                <input class="input" name="nom" placeholder="Nom de l’unité" required>
                <select class="select" name="type_unite_reference" required>
                    @foreach($typesUnite as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
                <select class="select" name="classification_reference" required>
                    @foreach($classifications as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
                <input class="input" name="unite_parente_reference" placeholder="Unité parente (facultatif)">
                <button class="button" type="submit">Créer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Relations interorganisationnelles</h2></div>
        <div class="card__body">
            @forelse($relations as $relation)
                <div class="summary-row">
                    <dt>{{ $relation['type_relation_reference'] }}</dt>
                    <dd class="technical-reference">{{ $relation['organisation_source_reference'] }} → {{ $relation['organisation_cible_reference'] }}</dd>
                </div>
            @empty
                <p class="muted">Aucune relation déclarée.</p>
            @endforelse
            @if($autorite && $organisation['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.organisations.relations.declarer', $organisation['reference']) }}" class="inline-form">
                @csrf
                <input class="input" name="organisation_cible_reference" placeholder="Organisation cible (ORG-GAMAD-…)" required>
                <select class="select" name="type_relation_reference" required>
                    @foreach($typesRelation as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
                <select class="select" name="classification_reference" required>
                    @foreach($classifications as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
                <button class="button" type="submit">Déclarer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Affiliations</h2></div>
        <div class="card__body">
            @if(!$autorite && $organisation['proprietaire_reference'] !== ($organisation['proprietaire_reference'] ?? null))
                <p class="muted">La liste complète des affiliations n’est visible que par l’autorité ou le propriétaire.</p>
            @endif
            @forelse($affiliations as $affiliation)
                <div class="summary-row">
                    <dt>{{ $affiliation['type_affiliation_reference'] }} <span class="technical-reference">{{ $affiliation['reference'] }}</span></dt>
                    <dd>
                        {{ $affiliation['identite_reference'] }} — {{ $affiliation['etat'] }}
                        @if($autorite)
                        <span class="inline-actions">
                            @if($affiliation['etat'] === 'PROPOSEE' || $affiliation['etat'] === 'SUSPENDUE')
                            <form method="POST" action="{{ route('console.organisations.affiliations.activer', [$organisation['reference'], $affiliation['reference']]) }}">
                                @csrf<button class="button button--small" type="submit">Activer</button>
                            </form>
                            @endif
                            @if($affiliation['etat'] === 'ACTIVE')
                            <form method="POST" action="{{ route('console.organisations.affiliations.suspendre', [$organisation['reference'], $affiliation['reference']]) }}">
                                @csrf<button class="button button--small" type="submit">Suspendre</button>
                            </form>
                            @endif
                            @if(in_array($affiliation['etat'], ['PROPOSEE','ACTIVE','SUSPENDUE']))
                            <form method="POST" action="{{ route('console.organisations.affiliations.fermer', [$organisation['reference'], $affiliation['reference']]) }}">
                                @csrf<button class="button button--small" type="submit">Fermer</button>
                            </form>
                            @endif
                        </span>
                        @endif
                    </dd>
                </div>
            @empty
                <p class="muted">Aucune affiliation visible.</p>
            @endforelse
            @if($autorite && $organisation['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.organisations.affiliations.proposer', $organisation['reference']) }}" class="inline-form">
                @csrf
                <input class="input" name="identite_reference" placeholder="Identité (IDN-…)" required>
                <select class="select" name="type_affiliation_reference" required>
                    @foreach($typesAffiliation as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
                <select class="select" name="niveau_assurance_reference" required>
                    @foreach($niveauxAssurance as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
                <select class="select" name="classification_reference" required>
                    @foreach($classifications as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
                <button class="button" type="submit">Proposer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Fonctions internes et mandats vérifiés</h2></div>
        <div class="card__body">
            <p class="muted">
                Une fonction est descriptive seule. Une représentation ne devient opposable que si
                CAP-CORE-003 confirme un mandat actif pour la fonction reliée — jamais par la seule
                affiliation « dirigeant » ou « représentant ».
            </p>
            @forelse($fonctions as $fonction)
                <div class="summary-row">
                    <dt>{{ $fonction['libelle'] }} <span class="technical-reference">{{ $fonction['reference'] }}</span></dt>
                    <dd>{{ $fonction['type_fonction_reference'] }}
                        @if($fonction['mandat_fonction_reference']) — lié à {{ $fonction['mandat_fonction_reference'] }} (CAP-CORE-003) @else — sans lien de mandat @endif
                    </dd>
                </div>
            @empty
                <p class="muted">Aucune fonction interne déclarée.</p>
            @endforelse
            @if($autorite && $organisation['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.organisations.fonctions.creer', $organisation['reference']) }}" class="inline-form">
                @csrf
                <input class="input" name="libelle" placeholder="Libellé de la fonction" required>
                <select class="select" name="type_fonction_reference" required>
                    @foreach($typesFonction as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
                <input class="input" name="mandat_fonction_reference" placeholder="Référence de fonction CAP-CORE-003 (facultatif)">
                <button class="button" type="submit">Créer</button>
            </form>
            @endif
            @if($autorite)
            <form method="POST" action="{{ route('console.organisations.representation.verifier', $organisation['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <input class="input" name="identite_reference" placeholder="Vérifier la représentation de… (IDN-…)" required>
                <button class="button" type="submit">Vérifier via CAP-CORE-003</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Historique et audit</h2></div>
        <div class="card__body">
            @foreach($historique as $evenement)
                <div class="summary-row">
                    <dt>{{ $evenement['etat_reference'] }}</dt>
                    <dd>{{ $evenement['date_effet'] }} — {{ $evenement['acteur_reference'] }}
                        @if($evenement['motif']) ({{ $evenement['motif'] }}) @endif
                    </dd>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
