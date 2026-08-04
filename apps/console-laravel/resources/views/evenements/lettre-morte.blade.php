@extends('layouts.console')

@section('title', $lettreMorte['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.evenements.index') }}">Événements</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('console.lettres-mortes.index') }}">Lettres mortes</a>
    <span aria-hidden="true">/</span>
    <span>{{ $lettreMorte['reference'] }}</span>
</nav>

@if(session('succes'))
    <div class="alert alert--success" style="margin-bottom:18px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span><span class="alert__title">{{ session('succes') }}</span></span>
    </div>
@endif
@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span><span>{{ $errors->first() }}</span>
    </div>
@endif

<header class="page-header">
    <div>
        <p class="eyebrow">Livraison {{ $lettreMorte['livraison_reference'] }}</p>
        <h1 class="page-title technical-reference">{{ $lettreMorte['reference'] }}</h1>
        <p class="page-subtitle">Code : {{ $lettreMorte['raison_code'] }} — {{ $lettreMorte['tentatives_total'] }} tentative(s)</p>
    </div>
    <span class="status {{ ($lettreMorte['cloturee'] ?? false) ? 'status--danger' : 'status--warning' }}">
        {{ ($lettreMorte['cloturee'] ?? false) ? 'CLÔTURÉE' : 'OUVERTE' }}
    </span>
</header>

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Historique</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Première erreur</dt><dd>{{ $lettreMorte['premiere_erreur_le'] }}</dd></div>
                <div class="summary-row"><dt>Dernière erreur</dt><dd>{{ $lettreMorte['derniere_erreur_le'] }}</dd></div>
                <div class="summary-row"><dt>Créée le</dt><dd>{{ $lettreMorte['cree_le'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Actions</h2></div>
        <div class="card__body">
            <form method="POST" action="{{ route('console.lettres-mortes.relancer', $lettreMorte['reference']) }}" class="form-layout" style="gap:12px">
                @csrf
                <div class="field">
                    <label for="motif-relance">Motif de relance</label>
                    <input class="input" id="motif-relance" name="motif" maxlength="500" required placeholder="Cause corrigée">
                </div>
                <button class="button button--primary" type="submit">Relancer</button>
            </form>
            <hr style="margin:18px 0">
            <form method="POST" action="{{ route('console.lettres-mortes.cloturer', $lettreMorte['reference']) }}" class="form-layout" style="gap:12px">
                @csrf
                <div class="field">
                    <label for="motif-cloture">Motif de clôture</label>
                    <input class="input" id="motif-cloture" name="motif" maxlength="500" required placeholder="Abandon définitif, doublon…">
                </div>
                <button class="button button--danger" type="submit">Clôturer</button>
            </form>
        </div>
    </section>
</div>
@endsection
