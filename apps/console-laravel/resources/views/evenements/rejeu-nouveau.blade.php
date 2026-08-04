@extends('layouts.console')

@section('title', 'Nouveau rejeu')

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.evenements.index') }}">Événements</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('console.rejeux.index') }}">Rejeux</a>
    <span aria-hidden="true">/</span>
    <span>Nouveau</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">Rejeu borné</p>
        <h1 class="page-title">Demander un rejeu</h1>
        <p class="page-subtitle">
            Des bornes explicites sont obligatoires — aucune demande « depuis toujours » implicite. Le volume
            estimé est affiché sur la fiche du rejeu une fois créé, avant toute validation.
        </p>
    </div>
</header>

@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span><span>{{ $errors->first() }}</span>
    </div>
@endif

<form method="POST" action="{{ route('console.rejeux.store') }}" class="form-layout">
    @csrf
    <div class="card">
        <section class="form-section">
            <div class="field">
                <label for="abonnement_reference">Abonnement</label>
                <input class="input" id="abonnement_reference" name="abonnement_reference"
                       value="{{ old('abonnement_reference', $abonnementPrerempli) }}" maxlength="64" required autocomplete="off">
            </div>
            <div class="field">
                <label for="motif">Motif</label>
                <input class="input" id="motif" name="motif" value="{{ old('motif') }}" maxlength="500" required>
            </div>
            <div class="field">
                <label for="sequence_debut">Séquence de début</label>
                <input class="input" type="number" id="sequence_debut" name="sequence_debut" min="0" value="{{ old('sequence_debut') }}">
            </div>
            <div class="field">
                <label for="sequence_fin">Séquence de fin</label>
                <input class="input" type="number" id="sequence_fin" name="sequence_fin" min="0" value="{{ old('sequence_fin') }}">
            </div>
            <div class="field">
                <label for="date_debut">Date de début (alternative aux séquences)</label>
                <input class="input" type="date" id="date_debut" name="date_debut" value="{{ old('date_debut') }}">
            </div>
            <div class="field">
                <label for="date_fin">Date de fin</label>
                <input class="input" type="date" id="date_fin" name="date_fin" value="{{ old('date_fin') }}">
            </div>
        </section>
    </div>
    <aside class="form-aside">
        <section class="card card--raised">
            <div class="card__body">
                <button class="button button--primary button--full" type="submit">Demander le rejeu</button>
            </div>
        </section>
    </aside>
</form>
@endsection
