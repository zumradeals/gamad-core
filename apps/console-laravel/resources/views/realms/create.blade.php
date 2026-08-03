@extends('layouts.console')

@section('title', 'Nouveau realm')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.realms.index') }}">Realms</a>
    <span aria-hidden="true">/</span>
    <span>Nouveau realm</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Inscription gouvernée</p>
        <h1 class="page-title">Nouveau realm</h1>
        <p class="page-subtitle">
            Le realm est créé en PREPARATION, lié à une identité canonique déjà inscrite de type
            « realm ». Aucune activation, aucun rattachement n’est automatique.
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

<form method="POST" action="{{ route('console.realms.store') }}" class="form-layout">
    @csrf

    <div class="card">
        <section class="form-section" aria-labelledby="realm-title">
            <div>
                <h2 class="form-section__title" id="realm-title">Fiche de realm</h2>
            </div>
            <div class="field">
                <label for="identite_reference">Identité canonique (CAP-CORE-001)</label>
                <input class="input" id="identite_reference" name="identite_reference"
                       value="{{ old('identite_reference') }}" maxlength="64" required autocomplete="off"
                       placeholder="Référence d’une identité déjà inscrite, de type « realm »">
                <p class="field-help">Le realm doit déjà exister comme identité de type « realm » au Registre des identités (préfixe IDN-RLM). Une référence n’est jamais réattribuée.</p>
            </div>
            <div class="field">
                <label for="code_canonique">Code canonique</label>
                <input class="input" id="code_canonique" name="code_canonique"
                       value="{{ old('code_canonique') }}" maxlength="128" required autocomplete="off"
                       placeholder="Ex. RLM-CI">
            </div>
            <div class="field">
                <label for="nom_affichage">Nom d’affichage</label>
                <input class="input" id="nom_affichage" name="nom_affichage"
                       value="{{ old('nom_affichage') }}" maxlength="500" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_realm_reference">Type</label>
                <select class="select" id="type_realm_reference" name="type_realm_reference" required>
                    @foreach($types as $valeur)
                        <option value="{{ $valeur }}" @selected(old('type_realm_reference') === $valeur)>{{ $valeur }}</option>
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
                <label for="description">Description (facultative)</label>
                <textarea class="input" id="description" name="description" maxlength="2000" rows="3">{{ old('description') }}</textarea>
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
