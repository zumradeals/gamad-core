@extends('layouts.console')

@section('title', 'Nouvelle politique')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.politiques.index') }}">Politiques</a>
    <span aria-hidden="true">/</span>
    <span>Nouvelle politique</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Inscription gouvernée</p>
        <h1 class="page-title">Nouvelle politique</h1>
        <p class="page-subtitle">
            La politique est créée sans version. Il faudra ensuite créer une version en
            BROUILLON, y ajouter des règles, la soumettre, la simuler avec succès, puis
            l’activer.
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

<form method="POST" action="{{ route('console.politiques.store') }}" class="form-layout">
    @csrf

    <div class="card">
        <section class="form-section" aria-labelledby="politique-title">
            <div>
                <h2 class="form-section__title" id="politique-title">Fiche</h2>
            </div>
            <div class="field">
                <label for="reference">Référence</label>
                <input class="input" id="reference" name="reference" value="{{ old('reference') }}"
                       maxlength="64" required autocomplete="off" placeholder="Ex. POL-EXEMPLE-V1">
                <p class="field-help">Immuable une fois inscrite : jamais réutilisée, jamais réattribuée.</p>
            </div>
            <div class="field">
                <label for="libelle">Libellé</label>
                <input class="input" id="libelle" name="libelle" value="{{ old('libelle') }}" maxlength="255" required autocomplete="off">
            </div>
            <div class="field">
                <label for="domaine">Domaine (facultatif)</label>
                <input class="input" id="domaine" name="domaine" value="{{ old('domaine') }}" maxlength="128" autocomplete="off">
            </div>
            <div class="field">
                <label for="proprietaire_reference">Propriétaire ou responsable (CAP-CORE-001)</label>
                <input class="input" id="proprietaire_reference" name="proprietaire_reference"
                       value="{{ old('proprietaire_reference') }}" maxlength="64" required autocomplete="off"
                       placeholder="Référence d’une identité déjà inscrite">
            </div>
            <div class="field">
                <label for="source_reference">Source</label>
                <input class="input" id="source_reference" name="source_reference" value="{{ old('source_reference') }}"
                       maxlength="500" required autocomplete="off" placeholder="Provenance : module, article, décision">
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
                        <dt>Version active</dt>
                        <dd>Aucune (à créer ensuite)</dd>
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
