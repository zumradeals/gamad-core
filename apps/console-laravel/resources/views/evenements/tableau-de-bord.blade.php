@extends('layouts.console')

@section('title', 'Événements')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Journal des événements</p>
        <h1 class="page-title">Événements</h1>
        <p class="page-subtitle">
            Le journal commun (CAP-CORE-014) — audit sans payload, diffusion gouvernée par abonnement, par
            realm et par contrat. Ce tableau de bord n’affiche que des agrégats de santé, jamais une charge.
        </p>
    </div>
</header>

<div class="action-bar" style="margin-bottom:18px">
    <form method="GET" action="" class="inline-form" onsubmit="event.preventDefault(); if(this.reference.value) window.location = '{{ url('/evenements') }}/' + encodeURIComponent(this.reference.value);">
        <input class="input" name="reference" placeholder="Référence d’un événement (EVT-GAMAD-…)" autocomplete="off">
        <button class="button" type="submit">Ouvrir la fiche</button>
    </form>
    <form method="GET" action="" class="inline-form" onsubmit="event.preventDefault(); if(this.reference.value) window.location = '{{ url('/abonnements') }}/' + encodeURIComponent(this.reference.value);">
        <input class="input" name="reference" placeholder="Référence d’un abonnement (ABN-GAMAD-…)" autocomplete="off">
        <button class="button" type="submit">Ouvrir l’abonnement</button>
    </form>
    <a class="button" href="{{ route('console.lettres-mortes.index') }}">Lettres mortes</a>
    <a class="button" href="{{ route('console.rejeux.index') }}">Rejeux</a>
</div>

@unless($autorise)
    <div class="form-error" role="alert">
        <span aria-hidden="true">!</span>
        <span>Le tableau de bord est fermé pour cette session. {{ $motif }}</span>
    </div>
@else
    @php
        $diag = $diagnostic['diagnostic'];
        $coherent = (bool) ($diag['coherent'] ?? false);
        $chaineValide = (bool) ($diag['chaine']['valide'] ?? false);
    @endphp

    <section class="hero-status" aria-labelledby="etat-journal">
        <div>
            <p class="eyebrow">État du journal</p>
            <h2 class="hero-status__title" id="etat-journal">
                {{ $coherent ? 'Le journal des événements est cohérent.' : 'Des écarts sont à examiner.' }}
            </h2>
            <p class="hero-status__copy">
                Chaîne d’empreintes : {{ $chaineValide ? 'valide' : 'INVALIDE' }} sur {{ $diag['chaine']['evenements'] ?? 0 }} événement(s).
                Tête : <span class="technical-reference">{{ $diag['chaine']['tete'] ?? '—' }}</span>
            </p>
        </div>
        <div class="health-orbit">
            <div class="health-orbit__ring {{ $coherent ? '' : 'health-orbit__ring--danger' }}">
                <span>
                    <span class="health-orbit__state">{{ $coherent ? 'Cohérent' : 'À vérifier' }}</span>
                    <span class="health-orbit__label">{{ $diagnostic['rejeux_actifs'] }} rejeu(x) actif(s)</span>
                </span>
            </div>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="card span-7">
            <div class="card__header"><h2 class="card__title">Publications</h2></div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row"><dt>Dernière heure</dt><dd>{{ $diagnostic['publies_1h'] }}</dd></div>
                    <div class="summary-row"><dt>24 heures</dt><dd>{{ $diagnostic['publies_24h'] }}</dd></div>
                    <div class="summary-row"><dt>7 jours</dt><dd>{{ $diagnostic['publies_7j'] }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="card span-5">
            <div class="card__header"><h2 class="card__title">Abonnements</h2></div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row"><dt>Total</dt><dd>{{ $diagnostic['abonnements_total'] }}</dd></div>
                    @foreach(['ACTIF' => 'Actifs', 'SUSPENDU' => 'Suspendus', 'PREPARATION' => 'En préparation', 'RETIRE' => 'Retirés'] as $etat => $libelle)
                        <div class="summary-row"><dt>{{ $libelle }}</dt><dd>{{ $diagnostic['abonnements_par_etat'][$etat] ?? 0 }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section class="card span-6">
            <div class="card__header"><h2 class="card__title">Livraison</h2></div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row"><dt>Baux expirés non libérés</dt><dd>{{ $diag['baux_expires_non_liberes'] ?? 0 }}</dd></div>
                    <div class="summary-row"><dt>Lettres mortes</dt><dd>{{ $diag['lettres_mortes'] ?? 0 }}</dd></div>
                    <div class="summary-row"><dt>Rejeux actifs</dt><dd>{{ $diagnostic['rejeux_actifs'] }}</dd></div>
                </dl>
                <p class="field-help" style="margin-top:12px">
                    Des baux expirés se libèrent avec <span class="technical-reference">core:evenements:liberer-baux</span>.
                </p>
            </div>
        </section>

        <section class="card span-6">
            <div class="card__header"><h2 class="card__title">Intégrité</h2></div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row"><dt>Événements sans charge inattendus</dt><dd>{{ $diag['evenements_sans_charge_inattendus'] ?? 0 }}</dd></div>
                    <div class="summary-row"><dt>Abonnements actifs sans type</dt><dd>{{ $diag['abonnements_actifs_sans_type'] ?? 0 }}</dd></div>
                </dl>
                <p class="field-help" style="margin-top:12px">
                    Rapprochement complet avec les magasins producteurs :
                    <span class="technical-reference">core:evenements:rapprocher</span>.
                </p>
            </div>
        </section>
    </div>
@endunless
@endsection
