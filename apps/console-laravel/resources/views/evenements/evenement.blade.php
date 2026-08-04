@extends('layouts.console')

@section('title', $evenement['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.evenements.index') }}">Événements</a>
    <span aria-hidden="true">/</span>
    <span>{{ $evenement['reference'] }}</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $evenement['type'] }} — {{ $evenement['contrat_reference'] }} {{ $evenement['contrat_version'] }}</p>
        <h1 class="page-title technical-reference">{{ $evenement['reference'] }}</h1>
        <p class="page-subtitle">Séquence {{ $evenement['sequence'] }} — survenu le {{ $evenement['survenu_le'] }}</p>
    </div>
    <span class="status status--success">{{ $evenement['classification'] }}</span>
</header>

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Enveloppe</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Producteur</dt><dd class="technical-reference">{{ $evenement['producteur_capacite_reference'] ?? $evenement['producteur_produit_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Source</dt><dd class="technical-reference">{{ $evenement['source_reference'] }}</dd></div>
                <div class="summary-row"><dt>Realm</dt><dd class="technical-reference">{{ $evenement['realm_reference'] }}</dd></div>
                <div class="summary-row"><dt>Finalité</dt><dd>{{ $evenement['finalite_reference'] }}</dd></div>
                <div class="summary-row"><dt>Sujet</dt><dd>{{ $evenement['sujet_type'] ?? '—' }} {{ $evenement['sujet_reference'] ?? '' }}</dd></div>
                <div class="summary-row"><dt>Corrélation</dt><dd class="technical-reference">{{ $evenement['correlation_id'] }}</dd></div>
                <div class="summary-row"><dt>Causalité</dt><dd class="technical-reference">{{ $evenement['causation_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Enregistré le</dt><dd>{{ $evenement['enregistre_le'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Intégrité</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Empreinte chaînée</dt><dd class="technical-reference">{{ $evenement['empreinte'] }}</dd></div>
                <div class="summary-row"><dt>Signée</dt><dd>{{ $evenement['signee'] ? 'oui' : 'non — capacité de signature non livrée' }}</dd></div>
                <div class="summary-row"><dt>Reconstruction</dt><dd>{{ $evenement['reconstruction'] ? 'oui' : 'non' }}</dd></div>
                <div class="summary-row"><dt>Expiration de charge</dt><dd>{{ $evenement['charge_expire_le'] ?? 'non fixée' }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card span-12">
        <div class="card__header"><h2 class="card__title">Charge</h2></div>
        <div class="card__body">
            @if($chargeRefusee)
                <p>La charge n’est pas visible pour cette session — réservée à l’autorité, au producteur ou à un consommateur destinataire.</p>
            @elseif($charge === null)
                <p>Aucune charge (résolution indisponible).</p>
            @elseif(($charge['etat'] ?? null) === 'CHARGE_EXPIREE')
                <p><span class="status status--warning">CHARGE_EXPIREE</span> La charge a été purgée ; l’enveloppe et l’empreinte restent vérifiables ci-dessus.</p>
            @else
                <pre style="white-space:pre-wrap;word-break:break-word">{{ json_encode($charge['charge'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
        </div>
    </section>
</div>
@endsection
