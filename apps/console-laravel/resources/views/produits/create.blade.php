@extends('layouts.console')

@section('title', 'Nouveau produit')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.produits.index') }}">Produits</a>
    <span aria-hidden="true">/</span>
    <span>Nouveau produit</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Inscription gouvernée</p>
        <h1 class="page-title">Nouveau produit</h1>
        <p class="page-subtitle">
            Le produit est créé en PREPARATION. Aucune activation n’est automatique ; il faudra
            l’activer explicitement une fois ses métadonnées vérifiées.
        </p>
    </div>
</header>

@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

@unless($inscriptionDisponible)
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span>
        <span>
            L’inscription est actuellement fermée pour cette session.
            {{ $decision['motif'] ?? 'Aucune politique ne permet cette action.' }}
        </span>
    </div>
@endunless

<form method="POST" action="{{ route('console.produits.store') }}" class="form-layout">
    @csrf

    <div class="card">
        <section class="form-section" aria-labelledby="produit-title">
            <div>
                <h2 class="form-section__title" id="produit-title">Fiche opérationnelle</h2>
            </div>
            <div class="field">
                <label for="reference">Référence</label>
                <input class="input" id="reference" name="reference" value="{{ old('reference') }}"
                       maxlength="64" required autocomplete="off" placeholder="Ex. PRD-GAMAD-005">
                <p class="field-help">Immuable une fois inscrite : jamais réutilisée, jamais réattribuée.</p>
            </div>
            <div class="field">
                <label for="identite_reference">Identité canonique (CAP-CORE-001)</label>
                <input class="input" id="identite_reference" name="identite_reference"
                       value="{{ old('identite_reference') }}" maxlength="64" required autocomplete="off"
                       placeholder="Référence d’une identité déjà inscrite, de type « produit »">
                <p class="field-help">Le produit doit déjà exister comme identité de type « produit » au Registre des identités.</p>
            </div>
            <div class="field">
                <label for="nom_canonique">Nom canonique</label>
                <input class="input" id="nom_canonique" name="nom_canonique" value="{{ old('nom_canonique') }}"
                       maxlength="255" required autocomplete="off">
            </div>
            <div class="field">
                <label for="nom_affichage">Nom d’exploitation</label>
                <input class="input" id="nom_affichage" name="nom_affichage" value="{{ old('nom_affichage') }}"
                       maxlength="255" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_produit">Type</label>
                <select class="select" id="type_produit" name="type_produit" required>
                    @foreach($types as $valeur)
                        <option value="{{ $valeur }}" @selected(old('type_produit') === $valeur)>{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="proprietaire_reference">Propriétaire ou responsable</label>
                <input class="input" id="proprietaire_reference" name="proprietaire_reference"
                       value="{{ old('proprietaire_reference') }}" maxlength="64" required autocomplete="off"
                       placeholder="Référence d’une identité">
            </div>
        </section>
    </div>

    <aside class="form-aside">
        <section class="card card--raised">
            <div class="card__header">
                <h2 class="card__title">Avant confirmation</h2>
            </div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row">
                        <dt>État initial</dt>
                        <dd>PREPARATION</dd>
                    </div>
                    <div class="summary-row">
                        <dt>Fédération</dt>
                        <dd>Non autorisée</dd>
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
                        <span class="alert__detail">Une preuve chaînée sera produite avant l’écriture.</span>
                    </span>
                </div>
                <button class="button button--primary button--full" type="submit" style="margin-top:16px"
                        @disabled(!$inscriptionDisponible)>
                    Confirmer l’inscription
                </button>
            </div>
        </section>
    </aside>
</form>
@endsection
