@extends('layouts.console')

@section('title', 'Nouveau contrat')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.contrats.index') }}">Contrats</a>
    <span aria-hidden="true">/</span>
    <span>Nouveau contrat</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Inscription gouvernée</p>
        <h1 class="page-title">Nouveau contrat</h1>
        <p class="page-subtitle">
            Le contrat est créé sans version. Il faudra ensuite créer une version en BROUILLON,
            y déclarer parties, opérations, schémas et erreurs, la soumettre, l’analyser, la
            faire déclarer conforme, puis l’activer.
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

<form method="POST" action="{{ route('console.contrats.store') }}" class="form-layout">
    @csrf

    <div class="card">
        <section class="form-section" aria-labelledby="contrat-title">
            <div>
                <h2 class="form-section__title" id="contrat-title">Fiche</h2>
            </div>
            <div class="field">
                <label for="reference">Référence</label>
                <input class="input" id="reference" name="reference" value="{{ old('reference') }}"
                       maxlength="64" required autocomplete="off" placeholder="Ex. CTR-GAMAD-EXEMPLE">
                <p class="field-help">Immuable une fois inscrite : jamais réutilisée, jamais réattribuée.</p>
            </div>
            <div class="field">
                <label for="nom">Nom</label>
                <input class="input" id="nom" name="nom" value="{{ old('nom') }}" maxlength="255" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_contrat">Type</label>
                <select class="select" id="type_contrat" name="type_contrat" required>
                    @foreach($types as $t)
                        <option value="{{ $t }}" @selected(old('type_contrat') === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="finalite_reference">Finalité</label>
                <input class="input" id="finalite_reference" name="finalite_reference" value="{{ old('finalite_reference') }}"
                       maxlength="500" required autocomplete="off" placeholder="Ce que sert cet échange">
            </div>
            <div class="field">
                <label for="producteur_capacite_reference">Producteur — capacité (ou produit ci-dessous, pas les deux)</label>
                <input class="input" id="producteur_capacite_reference" name="producteur_capacite_reference"
                       value="{{ old('producteur_capacite_reference') }}" maxlength="64" autocomplete="off" placeholder="Ex. CAP-CORE-001">
            </div>
            <div class="field">
                <label for="producteur_produit_reference">Producteur — produit</label>
                <input class="input" id="producteur_produit_reference" name="producteur_produit_reference"
                       value="{{ old('producteur_produit_reference') }}" maxlength="64" autocomplete="off">
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
                       maxlength="500" required autocomplete="off" placeholder="Provenance : module, route, classe">
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
