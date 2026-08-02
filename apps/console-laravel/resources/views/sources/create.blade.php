@extends('layouts.console')

@section('title', 'Nouvelle source')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.sources.index') }}">Sources</a>
    <span aria-hidden="true">/</span>
    <span>Nouvelle source</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Inscription gouvernée</p>
        <h1 class="page-title">Nouvelle source</h1>
        <p class="page-subtitle">
            La source est créée en PREPARATION. Aucune activation n’est automatique ; il faudra
            l’activer explicitement une fois ses métadonnées vérifiées. Aucune finalité n’est jamais
            implicite.
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

<form method="POST" action="{{ route('console.sources.store') }}" class="form-layout">
    @csrf

    <div class="card">
        <section class="form-section" aria-labelledby="source-title">
            <div>
                <h2 class="form-section__title" id="source-title">Fiche de provenance</h2>
            </div>
            <div class="field">
                <label for="reference">Référence</label>
                <input class="input" id="reference" name="reference" value="{{ old('reference') }}"
                       maxlength="64" required autocomplete="off" placeholder="Ex. SRC-GAMAD-005">
                <p class="field-help">Immuable une fois inscrite : jamais réutilisée, jamais réattribuée.</p>
            </div>
            <div class="field">
                <label for="nom_canonique">Nom canonique</label>
                <input class="input" id="nom_canonique" name="nom_canonique" value="{{ old('nom_canonique') }}"
                       maxlength="255" required autocomplete="off">
                <p class="field-help">Immuable, comme le type.</p>
            </div>
            <div class="field">
                <label for="nom_affichage">Nom d’exploitation</label>
                <input class="input" id="nom_affichage" name="nom_affichage" value="{{ old('nom_affichage') }}"
                       maxlength="255" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_source">Type</label>
                <select class="select" id="type_source" name="type_source" required>
                    @foreach($types as $valeur)
                        <option value="{{ $valeur }}" @selected(old('type_source') === $valeur)>{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="proprietaire_reference">Propriétaire ou responsable (CAP-CORE-001)</label>
                <input class="input" id="proprietaire_reference" name="proprietaire_reference"
                       value="{{ old('proprietaire_reference') }}" maxlength="64" required autocomplete="off"
                       placeholder="Référence d’une identité déjà inscrite">
            </div>
            <div class="field">
                <label for="produit_producteur_reference">Produit producteur (facultatif, CAP-CORE-011)</label>
                <input class="input" id="produit_producteur_reference" name="produit_producteur_reference"
                       value="{{ old('produit_producteur_reference') }}" maxlength="64" autocomplete="off"
                       placeholder="Référence d’un produit ACTIF, s’il en existe un">
            </div>
            <div class="field">
                <label for="categorie">Catégorie (facultative)</label>
                <input class="input" id="categorie" name="categorie" value="{{ old('categorie') }}" maxlength="500" autocomplete="off">
            </div>
            <div class="field">
                <label for="description">Description (facultative)</label>
                <textarea class="input" id="description" name="description" maxlength="2000" rows="3">{{ old('description') }}</textarea>
            </div>
            <div class="field">
                <label for="reserve">Réserve (facultative)</label>
                <textarea class="input" id="reserve" name="reserve" maxlength="2000" rows="3">{{ old('reserve') }}</textarea>
                <p class="field-help">Une limite ou un doute connu, restitué tel quel plutôt que masqué.</p>
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
                        <dt>Finalités</dt>
                        <dd>Aucune (à déclarer ensuite)</dd>
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
