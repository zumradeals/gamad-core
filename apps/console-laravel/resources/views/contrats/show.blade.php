@extends('layouts.console')

@section('title', $contrat['nom'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.contrats.index') }}">Contrats</a>
    <span aria-hidden="true">/</span>
    <span>{{ $contrat['nom'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($contrat['nom'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">{{ $contrat['type_contrat'] }}</p>
        <h1 class="detail-hero__title">{{ $contrat['nom'] }}</h1>
        <div class="detail-hero__meta">
            @if($contrat['version_active'])
                <span class="status status--success">Active — {{ $contrat['version_active'] }}</span>
            @else
                <span class="status status--warning">Aucune version active</span>
            @endif
        </div>
    </div>
    <span class="technical-reference">{{ $contrat['reference'] }}</span>
</section>

@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">{{ $errors->first() }}</div>
@endif
@if(session('succes'))
    <div class="alert alert--success" style="margin-bottom:18px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span>
            <span class="alert__title">{{ session('succes') }}</span>
            @if(session('preuve'))
                <span class="alert__detail">Preuve : <span class="technical-reference">{{ session('preuve')['reference'] ?? '' }}</span></span>
            @endif
        </span>
    </div>
@endif

<section class="card" style="margin-bottom:22px">
    <div class="card__header">
        <h2 class="card__title">Fiche</h2>
    </div>
    <div class="card__body">
        <dl class="detail-list">
            <div class="detail-row"><dt>Finalité</dt><dd>{{ $contrat['finalite_reference'] }}</dd></div>
            <div class="detail-row"><dt>Producteur</dt><dd class="technical-reference">{{ $contrat['producteur_capacite_reference'] ?? $contrat['producteur_produit_reference'] }}</dd></div>
            <div class="detail-row"><dt>Propriétaire ou responsable</dt><dd class="technical-reference">{{ $contrat['proprietaire_reference'] }}</dd></div>
            <div class="detail-row"><dt>Source</dt><dd>{{ $contrat['source_reference'] }}</dd></div>
            <div class="detail-row"><dt>Description</dt><dd>{{ $contrat['description'] ?? '—' }}</dd></div>
            <div class="detail-row"><dt>Modifié le</dt><dd>{{ $contrat['modifie_le'] }}</dd></div>
        </dl>
    </div>
</section>

@if($autorite)
<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Créer une version</h2></div>
    <div class="card__body">
        <form method="POST" action="{{ route('console.contrats.versions.creer', $contrat['reference']) }}" class="form-layout" style="max-width:520px">
            @csrf
            <div class="field">
                <label for="version">Version (X.Y.Z)</label>
                <input class="input" id="version" name="version" maxlength="32" required autocomplete="off" placeholder="1.0.0">
            </div>
            <div class="field">
                <label for="compatibilite_annoncee">Compatibilité annoncée</label>
                <select class="select" id="compatibilite_annoncee" name="compatibilite_annoncee">
                    <option value="COMPATIBLE">COMPATIBLE</option>
                    <option value="COMPATIBLE_AVEC_ADAPTATION">COMPATIBLE_AVEC_ADAPTATION</option>
                    <option value="RUPTURE">RUPTURE</option>
                </select>
            </div>
            <div class="field">
                <label for="description">Description (facultative)</label>
                <textarea class="input" id="description" name="description" maxlength="2000" rows="2"></textarea>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Créer en BROUILLON</button>
        </form>
    </div>
</section>
@endif

<section class="card">
    <div class="card__header"><h2 class="card__title">Versions</h2></div>
    <div class="card__body">
        <div class="identity-list">
            @foreach($versions as $v)
                <a class="identity-row" href="{{ route('console.contrats.version', [$contrat['reference'], $v['version']]) }}">
                    <span class="identity-main">
                        <span style="min-width:0">
                            <span class="identity-name">{{ $v['version'] }}</span>
                            <span class="technical-reference">{{ $v['cree_le'] }}</span>
                        </span>
                    </span>
                    <span>
                        <span class="status {{ $v['etat'] === 'ACTIVE' ? 'status--success' : (in_array($v['etat'], ['RETIREE','SUSPENDUE'], true) ? 'status--danger' : 'status--warning') }}">
                            {{ $v['etat'] }}
                        </span>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="card" style="margin-top:22px">
    <div class="card__header"><h2 class="card__title">Historique du cycle</h2></div>
    <div class="card__body">
        <div class="identity-list">
            @foreach($historique as $evenement)
                <div class="identity-row" style="cursor:default">
                    <span class="identity-main">
                        <span style="min-width:0">
                            <span class="identity-name">{{ $evenement['etat'] }}</span>
                            <span class="technical-reference">{{ $evenement['date_effet'] }}</span>
                        </span>
                    </span>
                    <span>
                        <span class="identity-cell-label">Acteur</span>
                        <span class="technical-reference">{{ $evenement['acteur_reference'] }}</span>
                    </span>
                    <span>
                        <span class="identity-cell-label">Motif</span>
                        {{ $evenement['motif'] ?? '—' }}
                    </span>
                    <span aria-hidden="true"></span>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
