@extends('layouts.console')

@section('title', 'Vue d’ensemble')

@section('content')
@php
    $corePret = (bool) ($fondation['pret'] ?? false);
    $mandatActif = is_array($mandat) && str_starts_with((string) ($mandat['etat'] ?? ''), 'ACTIF');
    $peutInscrire = ($decisionInscription['decision'] ?? null) === 'PERMIS' && $mandatActif;
    $libellesActivite = [
        'AUTHENTIFICATION_CONSOLE' => 'Connexion à la console',
        'AUTHENTIFICATION_API' => 'Accès à l’API',
        'DECISION_INSCRIPTION_IDENTITE' => 'Décision d’inscription',
        'INSCRIPTION_IDENTITE_REFUSEE' => 'Inscription refusée',
        'ENROLEMENT_PASSKEY' => 'Passkey enregistrée',
        'AUTHENTIFICATION_PASSKEY' => 'Connexion avec une passkey',
    ];
@endphp

<header class="page-header">
    <div>
        <p class="eyebrow">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</p>
        <h1 class="page-title">Bonjour.</h1>
        <p class="page-subtitle">
            Voici l’état utile du Core et les actions qui demandent votre attention aujourd’hui.
        </p>
    </div>
    <div class="page-actions">
        <a class="button button--primary" href="{{ route('console.identites.create') }}">
            <span class="button__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            Inscrire une identité
        </a>
    </div>
</header>

<section class="hero-status" aria-labelledby="etat-core">
    <div>
        <p class="eyebrow">État général</p>
        <h2 class="hero-status__title" id="etat-core">
            {{ $corePret && $alertes === [] ? 'Le Core fonctionne normalement.' : 'Le Core reste disponible, avec des points à examiner.' }}
        </h2>
        <p class="hero-status__copy">
            @if($corePret && $alertes === [])
                Les quatre registres répondent, les contrôles sont cohérents et votre mandat autorise les opérations prévues.
            @else
                Les services disponibles sont affichés ci-dessous. Toute action non autorisée restera fermée par défaut.
            @endif
        </p>
        <div class="hero-status__actions">
            <a class="button button--primary" href="{{ route('console.identites.index') }}">Gérer les identités</a>
            <a class="button button--secondary" href="#activite">Voir l’activité récente</a>
        </div>
    </div>
    <div class="health-orbit">
        <div class="health-orbit__ring {{ $corePret ? '' : 'health-orbit__ring--danger' }}">
            <span>
                <span class="health-orbit__state">{{ $corePret ? 'Prêt' : 'À vérifier' }}</span>
                <span class="health-orbit__label">4 registres souverains</span>
            </span>
        </div>
    </div>
</section>

<div class="dashboard-grid">
    <section class="card span-7" aria-labelledby="attention-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="attention-title">Votre attention</h2>
                <p class="card__description">Les points importants, classés avant les statistiques.</p>
            </div>
            <span class="status {{ $alertes === [] ? 'status--success' : 'status--warning' }}">
                {{ $alertes === [] ? 'Aucune alerte' : count($alertes).' à examiner' }}
            </span>
        </div>
        <div class="card__body">
            <ul class="alert-list">
                @forelse($alertes as $alerte)
                    <li class="alert {{ $alerte['niveau'] === 'danger' ? 'alert--danger' : '' }}">
                        <span class="alert__dot" aria-hidden="true"></span>
                        <span>
                            <span class="alert__title">{{ $alerte['titre'] }}</span>
                            <span class="alert__detail">{{ $alerte['detail'] }}</span>
                        </span>
                    </li>
                @empty
                    <li class="alert alert--success">
                        <span class="alert__dot" aria-hidden="true"></span>
                        <span>
                            <span class="alert__title">Rien d’urgent pour le moment</span>
                            <span class="alert__detail">Les fondations répondent et aucune divergence d’intégrité n’est signalée.</span>
                        </span>
                    </li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="card span-5" aria-labelledby="actions-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="actions-title">Actions rapides</h2>
                <p class="card__description">Commencer par ce qui compte.</p>
            </div>
        </div>
        <div class="card__body">
            <div class="quick-actions">
                <a class="quick-action" href="{{ route('console.identites.create') }}">
                    <span class="quick-action__icon" aria-hidden="true">+</span>
                    <span>
                        <span class="quick-action__title">Nouvelle identité</span>
                        <span class="quick-action__detail">{{ $peutInscrire ? 'Autorisation et mandat vérifiés' : 'Vérification requise' }}</span>
                    </span>
                </a>
                <a class="quick-action" href="{{ route('console.identites.index') }}">
                    <span class="quick-action__icon" aria-hidden="true">⌕</span>
                    <span>
                        <span class="quick-action__title">Rechercher une identité</span>
                        <span class="quick-action__detail">{{ count($identites) }} identités connues</span>
                    </span>
                </a>
            </div>
        </div>
    </section>

    <section class="card span-4" aria-labelledby="identity-summary-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="identity-summary-title">Identités</h2>
                <p class="card__description">Le registre commun, sans profil universel.</p>
            </div>
            <a href="{{ route('console.identites.index') }}">Ouvrir</a>
        </div>
        <div class="card__body">
            <div class="metric-row">
                <div class="metric">
                    <span class="metric__value">{{ count($identites) }}</span>
                    <span class="metric__label">Total</span>
                </div>
                <div class="metric">
                    <span class="metric__value">{{ $parType['personne'] ?? 0 }}</span>
                    <span class="metric__label">Personnes</span>
                </div>
                <div class="metric">
                    <span class="metric__value">{{ ($parType['organisation'] ?? 0) + ($parType['produit'] ?? 0) }}</span>
                    <span class="metric__label">Structures</span>
                </div>
            </div>
        </div>
    </section>

    <section class="card span-4" aria-labelledby="mandate-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="mandate-title">Votre mandat</h2>
                <p class="card__description">Le pouvoir vient d’une source explicite.</p>
            </div>
            <span class="status {{ $mandatActif ? 'status--success' : 'status--danger' }}">
                {{ $mandatActif ? 'Actif' : 'Non actif' }}
            </span>
        </div>
        <div class="card__body">
            @if(is_array($mandat))
                <strong>{{ $mandat['fonction_libelle'] }}</strong>
                <p class="technical-reference">{{ $mandat['mandat'] }}</p>
                <p class="card__description">Preuve {{ $mandat['niveau_preuve'] }} · depuis {{ $mandat['debut'] }}</p>
            @else
                <p class="card__description">Aucun mandat n’a été résolu pour cette session.</p>
            @endif
        </div>
    </section>

    <section class="card span-4" aria-labelledby="foundation-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="foundation-title">Fondations</h2>
                <p class="card__description">État des magasins de production.</p>
            </div>
            <span class="status {{ $corePret ? 'status--success' : 'status--danger' }}">
                {{ $corePret ? 'Prêtes' : 'À vérifier' }}
            </span>
        </div>
        <div class="card__body">
            <ul class="detail-list">
                @foreach($fondation['cibles'] as $nom => $cible)
                    <li style="display:flex;justify-content:space-between;gap:12px">
                        <span>{{ ucfirst($nom) }}</span>
                        <span class="status {{ $cible['prete'] ? 'status--success' : 'status--danger' }}">
                            {{ $cible['prete'] ? 'Disponible' : 'Indisponible' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="card span-7" id="activite" aria-labelledby="activity-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="activity-title">Activité récente</h2>
                <p class="card__description">Les dernières traces du journal opérationnel.</p>
            </div>
        </div>
        <div class="card__body">
            <ol class="activity-list">
                @forelse($activite as $evenement)
                    <li class="activity-item">
                        <span class="activity-item__dot" aria-hidden="true"></span>
                        <span>
                            <span class="activity-item__title">
                                {{ $libellesActivite[$evenement['type_evenement']] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $evenement['type_evenement']))) }}
                            </span>
                            <span class="activity-item__meta">
                                {{ $evenement['acteur'] ?: 'Système' }} · {{ $evenement['decision'] ?: $evenement['categorie'] }}
                            </span>
                        </span>
                        <time class="activity-item__time" datetime="{{ $evenement['cree_le'] }}">
                            {{ substr($evenement['cree_le'], 11, 5) }}
                        </time>
                    </li>
                @empty
                    <li class="card__description">Aucune activité n’est encore enregistrée.</li>
                @endforelse
            </ol>
        </div>
    </section>

    <section class="card span-5" aria-labelledby="integrity-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="integrity-title">Cohérence et preuves</h2>
                <p class="card__description">Les indicateurs techniques restent disponibles, sans dominer l’accueil.</p>
            </div>
        </div>
        <div class="card__body">
            <dl class="detail-list">
                <div class="detail-row">
                    <dt>Actes d’adoption</dt>
                    <dd>{{ count($adoptions) }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Fichiers intègres</dt>
                    <dd>{{ count($concordants) }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Divergences</dt>
                    <dd>{{ count($divergents) + count($index['divergences']) }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Preuve temporelle</dt>
                    <dd>{{ $p3Ok ? 'P3 établie' : 'À vérifier' }}</dd>
                </div>
            </dl>
        </div>
    </section>
</div>
@endsection
