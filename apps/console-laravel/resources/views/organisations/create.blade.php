@extends('layouts.console')

@section('title', 'Nouvelle organisation')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.organisations.index') }}">Organisations</a>
    <span aria-hidden="true">/</span>
    <span>Nouvelle organisation</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Inscription gouvernée</p>
        <h1 class="page-title">Nouvelle organisation</h1>
        <p class="page-subtitle">
            L’organisation est créée en PREPARATION, liée à une identité canonique déjà inscrite de
            type « organisation ». Aucune activation n’est automatique.
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

<form method="POST" action="{{ route('console.organisations.store') }}" class="form-layout">
    @csrf

    <div class="card">
        <section class="form-section" aria-labelledby="organisation-title">
            <div>
                <h2 class="form-section__title" id="organisation-title">Fiche organisationnelle</h2>
            </div>
            <div class="field">
                <label for="identite_reference">Identité canonique (CAP-CORE-001)</label>
                <input class="input" id="identite_reference" name="identite_reference"
                       value="{{ old('identite_reference') }}" maxlength="64" required autocomplete="off"
                       placeholder="Référence d’une identité déjà inscrite, de type « organisation »">
                <p class="field-help">L’organisation doit déjà exister comme identité de type « organisation » au Registre des identités. Une référence n’est jamais réattribuée.</p>
            </div>
            <div class="field">
                <label for="denomination_officielle">Dénomination officielle</label>
                <input class="input" id="denomination_officielle" name="denomination_officielle"
                       value="{{ old('denomination_officielle') }}" maxlength="500" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_organisation_reference">Type</label>
                <select class="select" id="type_organisation_reference" name="type_organisation_reference" required>
                    @foreach($types as $valeur)
                        <option value="{{ $valeur }}" @selected(old('type_organisation_reference') === $valeur)>{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="classification_reference">Classification</label>
                <select class="select" id="classification_reference" name="classification_reference" required>
                    @foreach($classifications as $valeur)
                        <option value="{{ $valeur }}" @selected(old('classification_reference') === $valeur)>{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="proprietaire_reference">Propriétaire ou responsable</label>
                <input class="input" id="proprietaire_reference" name="proprietaire_reference"
                       value="{{ old('proprietaire_reference') }}" maxlength="64" required autocomplete="off"
                       placeholder="Référence d’une identité">
            </div>
            <div class="field field--checkbox">
                <label>
                    <input type="checkbox" name="personnalite_juridique" value="1" @checked(old('personnalite_juridique'))>
                    Personnalité juridique reconnue
                </label>
                <p class="field-help">Le registre ne suppose jamais qu’une organisation possède une personnalité juridique par défaut.</p>
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
