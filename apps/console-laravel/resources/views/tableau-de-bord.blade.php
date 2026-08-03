@extends('layouts.console')

@section('title', 'Vue d’ensemble')

@section('content')
@php
    $corePret = (bool) ($fondation['pret'] ?? false);
    $mandatActif = is_array($mandat) && str_starts_with((string) ($mandat['etat'] ?? ''), 'ACTIF');
    $peutInscrire = ($decisionInscription['decision'] ?? null) === 'PERMIS' && $mandatActif;
    $ciblesFondation = $fondation['cibles'] ?? [];
    $servicesDisponibles = count(array_filter(
        $ciblesFondation,
        static fn (array $cible): bool => (bool) ($cible['prete'] ?? false),
    ));
    $servicesTotal = count($ciblesFondation);
    $libellesActivite = [
        'AUTHENTIFICATION_CONSOLE' => 'Connexion à la console',
        'AUTHENTIFICATION_API' => 'Accès technique à GAMAD',
        'DECISION_INSCRIPTION_IDENTITE' => 'Décision concernant une identité',
        'INSCRIPTION_IDENTITE_REFUSEE' => 'Inscription d’identité refusée',
        'ENROLEMENT_PASSKEY' => 'Moyen de connexion sécurisé enregistré',
        'AUTHENTIFICATION_PASSKEY' => 'Connexion sécurisée réussie',
    ];
    $libellesServices = [
        'index' => 'Cohérence générale',
        'acces' => 'Accès et connexions',
        'identites' => 'Personnes et identités',
        'produits' => 'Produits et services',
        'sources' => 'Sources reconnues',
        'politiques' => 'Règles et autorisations',
        'contrats' => 'Accords et contrats',
        'vocabulaire' => 'Mots officiels',
        'journal' => 'Historique des opérations',
    ];
    $libellesCapacites = [
        'CAP-CORE-001' => 'Personnes et identités',
        'CAP-CORE-002' => 'Organisations',
        'CAP-CORE-003' => 'Responsables et mandats',
        'CAP-CORE-004' => 'Autorisations',
        'CAP-CORE-005' => 'Accès et connexions',
        'CAP-CORE-006' => 'Sources reconnues',
        'CAP-CORE-007' => 'Règles et autorisations',
        'CAP-CORE-008' => 'Décisions importantes',
        'CAP-CORE-009' => 'Accords et contrats',
        'CAP-CORE-010' => 'Mots officiels',
        'CAP-CORE-011' => 'Produits et services',
        'CAP-CORE-012' => 'Pays et périmètres',
        'CAP-CORE-013' => 'Audit commun',
        'CAP-CORE-014' => 'Échanges entre services',
        'CAP-CORE-015' => 'Preuves d’intégrité',
        'CAP-CORE-016' => 'Secrets et clés',
        'CAP-CORE-017' => 'Risques et exceptions',
        'CAP-CORE-018' => 'Incidents',
        'CAP-CORE-019' => 'Sauvegardes et restauration',
        'CAP-CORE-020' => 'Directory et Atlas',
        'CAP-CORE-021' => 'Matching',
        'CAP-CORE-022' => 'Connexion des satellites',
    ];
    $libellesEtatCapacite = [
        'CONÇUE' => 'Disponible',
        'CONCUE' => 'Disponible',
        'GO' => 'Disponible',
        'NO GO' => 'En construction',
        'EN_COURS' => 'En construction',
    ];
    $formatDate = static function (?string $date): string {
        if ($date === null || trim($date) === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)
                ->locale('fr')
                ->isoFormat('D MMMM YYYY');
        } catch (\Throwable) {
            return $date;
        }
    };
@endphp

<header class="page-header">
    <div>
        <p class="eyebrow">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</p>
        <h1 class="page-title">Bonjour.</h1>
        <p class="page-subtitle">
            Voici ce qui fonctionne, ce qui demande votre attention et les décisions utiles pour aujourd’hui.
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
                Les services fondamentaux répondent correctement. Aucune décision urgente n’est attendue de votre part.
            @else
                Les services disponibles sont affichés ci-dessous. Toute opération incertaine ou non autorisée reste bloquée automatiquement.
            @endif
        </p>
        <div class="hero-status__actions">
            <a class="button button--primary" href="#attention-title">Voir ce qui demande mon attention</a>
            <a class="button button--secondary" href="#activite">Voir l’activité récente</a>
        </div>
    </div>
    <div class="health-orbit">
        <div class="health-orbit__ring {{ $corePret ? '' : 'health-orbit__ring--danger' }}">
            <span>
                <span class="health-orbit__state">{{ $corePret ? 'Prêt' : 'À vérifier' }}</span>
                <span class="health-orbit__label">{{ $servicesDisponibles }} service{{ $servicesDisponibles > 1 ? 's' : '' }} disponible{{ $servicesDisponibles > 1 ? 's' : '' }} sur {{ $servicesTotal }}</span>
            </span>
        </div>
    </div>
</section>

<div class="dashboard-grid">
    <section class="card span-7" aria-labelledby="attention-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="attention-title">Votre attention</h2>
                <p class="card__description">Les problèmes et décisions sont affichés avant les statistiques.</p>
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
                            <span class="alert__detail">Les fonctions essentielles répondent et aucun problème d’intégrité n’est signalé.</span>
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
                <p class="card__description">Les opérations courantes disponibles pour votre session.</p>
            </div>
        </div>
        <div class="card__body">
            <div class="quick-actions">
                <a class="quick-action" href="{{ route('console.identites.create') }}">
                    <span class="quick-action__icon" aria-hidden="true">+</span>
                    <span>
                        <span class="quick-action__title">Nouvelle identité</span>
                        <span class="quick-action__detail">{{ $peutInscrire ? 'Vous êtes autorisé à effectuer cette opération' : 'Une vérification supplémentaire est nécessaire' }}</span>
                    </span>
                </a>
                <a class="quick-action" href="{{ route('console.identites.index') }}">
                    <span class="quick-action__icon" aria-hidden="true">⌕</span>
                    <span>
                        <span class="quick-action__title">Rechercher une personne ou une structure</span>
                        <span class="quick-action__detail">{{ count($identites) }} identité{{ count($identites) > 1 ? 's' : '' }} connue{{ count($identites) > 1 ? 's' : '' }}</span>
                    </span>
                </a>
            </div>
        </div>
    </section>

    <section class="card span-4" aria-labelledby="identity-summary-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="identity-summary-title">Personnes et identités</h2>
                <p class="card__description">Les personnes, organisations et produits reconnus par GAMAD.</p>
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
                    <span class="metric__label">Structures et produits</span>
                </div>
            </div>
        </div>
    </section>

    <section class="card span-4" aria-labelledby="mandate-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="mandate-title">Votre pouvoir d’action</h2>
                <p class="card__description">Ce que votre fonction vous permet de faire dans GAMAD.</p>
            </div>
            <span class="status {{ $mandatActif ? 'status--success' : 'status--danger' }}">
                {{ $mandatActif ? 'Actif' : 'Non actif' }}
            </span>
        </div>
        <div class="card__body">
            @if(is_array($mandat))
                <strong>{{ $mandat['fonction_libelle'] }}</strong>
                <p class="card__description">Ce pouvoir est reconnu depuis le {{ $formatDate($mandat['debut'] ?? null) }}.</p>
                <details class="technical-panel" style="margin-top:14px">
                    <summary>Afficher la référence du mandat</summary>
                    <div class="technical-panel__body technical-reference">{{ $mandat['mandat'] }} · preuve {{ $mandat['niveau_preuve'] }}</div>
                </details>
            @else
                <p class="card__description">Aucun mandat n’a été reconnu pour cette session. Les actions sensibles resteront bloquées.</p>
            @endif
        </div>
    </section>

    <section class="card span-4" aria-labelledby="foundation-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="foundation-title">Services fondamentaux</h2>
                <p class="card__description">Les fonctions indispensables au fonctionnement de GAMAD.</p>
            </div>
            <span class="status {{ $corePret ? 'status--success' : 'status--danger' }}">
                {{ $corePret ? 'Disponibles' : 'À vérifier' }}
            </span>
        </div>
        <div class="card__body">
            <ul class="detail-list">
                @foreach($ciblesFondation as $nom => $cible)
                    <li style="display:flex;justify-content:space-between;gap:12px">
                        <span>{{ $libellesServices[$nom] ?? ucfirst($nom) }}</span>
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
                <p class="card__description">Les dernières opérations importantes enregistrées par GAMAD.</p>
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
                <h2 class="card__title" id="integrity-title">Intégrité du système</h2>
                <p class="card__description">Vérifie que les fondations n’ont pas été altérées ou rendues incohérentes.</p>
            </div>
            <span class="status {{ $diagnostic['coherent'] ? 'status--success' : 'status--danger' }}">
                {{ $diagnostic['coherent'] ? 'Aucun problème' : 'À vérifier' }}
            </span>
        </div>
        <div class="card__body">
            <div class="help-callout">
                <strong>{{ $diagnostic['coherent'] ? 'Les contrôles sont satisfaisants.' : 'Une vérification technique est nécessaire.' }}</strong>
                <p>{{ $diagnostic['coherent'] ? 'Aucune intervention n’est attendue de votre part.' : 'Aucune correction automatique ne sera effectuée sans validation.' }}</p>
            </div>
            <details class="technical-panel" style="margin-top:14px">
                <summary>Afficher les contrôles techniques</summary>
                <div class="technical-panel__body">
                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>Empreinte de référence</dt>
                            <dd>{{ $diagnostic['baseline']['concorde'] ? 'Vérifiée' : 'Non concordante' }}</dd>
                        </div>
                        <div class="detail-row">
                            <dt>Index lisible</dt>
                            <dd>{{ $diagnostic['index']['lisible'] ? 'Oui' : 'Non' }}</dd>
                        </div>
                        <div class="detail-row">
                            <dt>Divergences</dt>
                            <dd>{{ count($diagnostic['divergences']) }}</dd>
                        </div>
                        <div class="detail-row">
                            <dt>Historique des opérations</dt>
                            <dd>{{ $journalDisponible ? 'Disponible' : 'Illisible' }}</dd>
                        </div>
                    </dl>
                    @if(! $diagnostic['coherent'])
                        <ul class="detail-list">
                            @foreach($diagnostic['divergences'] as $divergence)
                                <li class="technical-reference">{{ $divergence }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </details>
        </div>
    </section>

    <section class="card span-12" aria-labelledby="capacites-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="capacites-title">Fonctions du Core</h2>
                <p class="card__description">Ce qui est déjà disponible et ce qui reste encore en construction.</p>
            </div>
        </div>
        <div class="card__body">
            <dl class="detail-list">
                @foreach($capacites as $capacite)
                    @php
                        $etatBrut = strtoupper(trim((string) $capacite['valeur']));
                        $etatHumain = $libellesEtatCapacite[$etatBrut] ?? ucfirst(mb_strtolower((string) $capacite['valeur']));
                        $disponible = in_array($etatBrut, ['CONÇUE', 'CONCUE', 'GO'], true);
                    @endphp
                    <div class="detail-row">
                        <dt>
                            <span class="capacity-name">{{ $libellesCapacites[$capacite['reference']] ?? 'Fonction GAMAD' }}</span>
                            <span class="technical-reference capacity-reference">{{ $capacite['reference'] }}</span>
                        </dt>
                        <dd>
                            <span class="human-state {{ $disponible ? '' : 'human-state--warning' }}">{{ $etatHumain }}</span>
                            @if($capacite['date_effet'])
                                <span class="card__description">depuis le {{ $formatDate($capacite['date_effet']) }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
</div>
@endsection
