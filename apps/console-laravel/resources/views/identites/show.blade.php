@extends('layouts.console')

@section('title', $identite['libelle'])

@section('content')
@php
    $typeLabels = [
        'personne' => 'Personne',
        'organisation' => 'Organisation',
        'produit' => 'Produit',
        'realm' => 'Realm',
        'agent' => 'Agent',
        'service' => 'Service',
        'INDETERMINE' => 'À déterminer',
    ];
    $etat = (string) ($identite['etat'] ?? 'INDETERMINE');
    $statutClasse = in_array($etat, ['ACTIVE', 'VERIFIEE'], true)
        ? 'status--success'
        : ($etat === 'PROVISOIRE' ? 'status--warning' : '');
@endphp

<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.identites.index') }}">Identités</a>
    <span aria-hidden="true">/</span>
    <span>{{ $identite['libelle'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($typeLabels[$identite['type']] ?? $identite['type'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">{{ $typeLabels[$identite['type']] ?? $identite['type'] }}</p>
        <h1 class="detail-hero__title">{{ $identite['libelle'] }}</h1>
        <div class="detail-hero__meta">
            <span class="status {{ $statutClasse }}">{{ ucfirst(mb_strtolower($etat)) }}</span>
            <span class="status">{{ $identite['regime'] === 'INSCRIT_AU_REGISTRE' ? 'Inscrite' : 'Issue du corpus' }}</span>
            @if($assurance)
                <span class="status status--success">Assurance {{ $assurance['niveau'] }}</span>
            @endif
        </div>
    </div>
    <span class="technical-reference">{{ $identite['reference'] }}</span>
</section>

<div class="detail-grid">
    <section class="card" aria-labelledby="overview-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="overview-title">Vue générale</h2>
                <p class="card__description">Les informations minimales conservées par le Core.</p>
            </div>
        </div>
        <div class="card__body">
            <dl class="detail-list">
                <div class="detail-row">
                    <dt>Référence</dt>
                    <dd class="technical-reference">{{ $identite['reference'] }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Type</dt>
                    <dd>{{ $typeLabels[$identite['type']] ?? $identite['type'] }}</dd>
                </div>
                <div class="detail-row">
                    <dt>État</dt>
                    <dd>{{ ucfirst(mb_strtolower($etat)) }}</dd>
                </div>
                <div class="detail-row">
                    <dt>Régime de vérité</dt>
                    <dd>{{ $identite['regime'] === 'INSCRIT_AU_REGISTRE' ? 'Inscrite au registre persistant' : 'Dérivée du corpus' }}</dd>
                </div>
                @if(isset($identite['classification']))
                    <div class="detail-row">
                        <dt>Classification</dt>
                        <dd>{{ ucfirst(mb_strtolower(str_replace('_', ' ', $identite['classification']))) }}</dd>
                    </div>
                @endif
                <div class="detail-row">
                    <dt>Date d’effet</dt>
                    <dd>{{ $identite['date_effet'] ?? 'Non établie' }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="card" aria-labelledby="source-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="source-title">Source et preuve</h2>
                <p class="card__description">Pourquoi le Core reconnaît cette identité.</p>
            </div>
        </div>
        <div class="card__body">
            <dl class="detail-list">
                <div class="detail-row">
                    <dt>Source</dt>
                    <dd>{{ $identite['source'] ?? 'Non établie' }}</dd>
                </div>
                @if(isset($identite['politique_inscription']))
                    <div class="detail-row">
                        <dt>Politique</dt>
                        <dd class="technical-reference">{{ $identite['politique_inscription'] }}</dd>
                    </div>
                @endif
                @if(isset($identite['producteur']))
                    <div class="detail-row">
                        <dt>Producteur</dt>
                        <dd class="technical-reference">{{ $identite['producteur'] }}</dd>
                    </div>
                @endif
                @if(isset($identite['preuve_reference']))
                    <div class="detail-row">
                        <dt>Preuve</dt>
                        <dd class="technical-reference">{{ $identite['preuve_reference'] }}</dd>
                    </div>
                @elseif(isset($identite['adoption_reference']))
                    <div class="detail-row">
                        <dt>Acte</dt>
                        <dd class="technical-reference">{{ $identite['adoption_reference'] }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>

    <section class="card span-12" aria-labelledby="assurance-title">
        <div class="card__header">
            <div>
                <h2 class="card__title" id="assurance-title">Assurance et chronologie</h2>
                <p class="card__description">Chaque évolution conserve sa date, sa source et sa preuve.</p>
            </div>
            @if($assurance)
                <span class="status status--success">Niveau {{ $assurance['niveau'] }}</span>
            @endif
        </div>
        <div class="card__body">
            @if($assurance && $assurance['evenements'] !== [])
                <ol class="timeline">
                    @foreach(array_reverse($assurance['evenements']) as $evenement)
                        <li class="timeline-item">
                            <span class="timeline-item__marker">{{ $evenement['niveau'] }}</span>
                            <span class="timeline-item__content">
                                <span class="timeline-item__title">Assurance portée au niveau {{ $evenement['niveau'] }}</span>
                                <span class="timeline-item__meta">
                                    {{ $evenement['date_effet'] }} · {{ $evenement['producteur'] }}
                                    · preuve <span class="technical-reference">{{ $evenement['preuve_reference'] }}</span>
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="empty-state" style="padding:28px 18px">
                    <h2>Aucun événement d’assurance persistant</h2>
                    <p>Cette identité est issue du corpus ou son historique d’assurance n’est pas exposé à cette session.</p>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
