@extends('layouts.console')

@section('title', $produit['nom_affichage'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.produits.index') }}">Produits</a>
    <span aria-hidden="true">/</span>
    <span>{{ $produit['nom_affichage'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($produit['nom_affichage'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">{{ $produit['type_produit'] }}</p>
        <h1 class="detail-hero__title">{{ $produit['nom_affichage'] }}</h1>
        <div class="detail-hero__meta">
            <span class="status {{ $produit['etat'] === 'ACTIF' ? 'status--success' : ($produit['etat'] === 'RETIRE' ? 'status--danger' : 'status--warning') }}">
                {{ $produit['etat'] }}
            </span>
            <span class="status {{ $produit['federation_autorisee'] ? 'status--success' : 'status--warning' }}">
                {{ $produit['federation_autorisee'] ? 'Fédérable' : 'Non fédérable' }}
            </span>
        </div>
    </div>
    <span class="technical-reference">{{ $produit['reference'] }}</span>
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
            <div class="detail-row"><dt>Identité canonique</dt><dd class="technical-reference">{{ $produit['identite_reference'] }}</dd></div>
            <div class="detail-row"><dt>Nom canonique</dt><dd>{{ $produit['nom_canonique'] }}</dd></div>
            <div class="detail-row"><dt>Propriétaire ou responsable</dt><dd class="technical-reference">{{ $produit['proprietaire_reference'] }}</dd></div>
            <div class="detail-row"><dt>Source</dt><dd>{{ $produit['source_reference'] }}</dd></div>
            <div class="detail-row"><dt>Depuis</dt><dd>{{ $produit['depuis'] }}</dd></div>
            <div class="detail-row"><dt>Modifié le</dt><dd>{{ $produit['modifie_le'] }}</dd></div>
        </dl>
    </div>
</section>

@if($autorite)
<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Cycle de vie</h2></div>
    <div class="card__body" style="display:flex;gap:12px;flex-wrap:wrap">
        @if(in_array($produit['etat'], ['PREPARATION', 'SUSPENDU'], true))
            <form method="POST" action="{{ route('console.produits.activer', $produit['reference']) }}">
                @csrf
                <button class="button button--primary" type="submit">Activer</button>
            </form>
        @endif
        @if($produit['etat'] === 'ACTIF')
            <form method="POST" action="{{ route('console.produits.suspendre', $produit['reference']) }}"
                  onsubmit="return confirm('Suspendre {{ $produit['nom_affichage'] }} ? Sa fédération se ferme immédiatement, jetons ouverts compris.');">
                @csrf
                <button class="button button--secondary" type="submit">Suspendre</button>
            </form>
        @endif
        @if($produit['etat'] !== 'RETIRE')
            <form method="POST" action="{{ route('console.produits.retirer', $produit['reference']) }}"
                  onsubmit="return confirm('Retirer {{ $produit['nom_affichage'] }} ? C’est irréversible ; son historique reste consultable.');">
                @csrf
                <button class="button button--secondary" type="submit">Retirer</button>
            </form>
        @endif
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Métadonnées</h2></div>
    <div class="card__body">
        <form method="POST" action="{{ route('console.produits.modifier', $produit['reference']) }}" class="form-layout" style="max-width:520px">
            @csrf
            <div class="field">
                <label for="nom_affichage">Nom d’exploitation</label>
                <input class="input" id="nom_affichage" name="nom_affichage" value="{{ $produit['nom_affichage'] }}" maxlength="255">
            </div>
            <div class="field">
                <label for="type_produit">Type</label>
                <select class="select" id="type_produit" name="type_produit">
                    @foreach($types as $valeur)
                        <option value="{{ $valeur }}" @selected($produit['type_produit'] === $valeur)>{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="proprietaire_reference">Propriétaire ou responsable</label>
                <input class="input" id="proprietaire_reference" name="proprietaire_reference" value="{{ $produit['proprietaire_reference'] }}" maxlength="64">
            </div>
            <label class="check-row">
                <input name="federation_autorisee" type="checkbox" value="1" @checked($produit['federation_autorisee'])>
                <span><strong>Fédération autorisée</strong></span>
            </label>
            <button class="button button--primary" type="submit" style="margin-top:8px">Enregistrer</button>
        </form>
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Environnements</h2></div>
    <div class="card__body">
        @if($environnements === [])
            <div class="empty-state" style="padding:28px 18px">
                <div class="empty-state__symbol" aria-hidden="true">⌕</div>
                <h2>Aucun environnement déclaré</h2>
                <p>Déclarez une URL d’API pour que ce produit devienne exploitable.</p>
            </div>
        @else
            <div class="identity-list">
                @foreach($environnements as $env)
                    <div class="identity-row" style="cursor:default">
                        <span class="identity-main">
                            <span style="min-width:0">
                                <span class="identity-name">{{ $env['environnement'] }}</span>
                                <span class="technical-reference">{{ $env['api_base_url'] }}</span>
                            </span>
                        </span>
                        <span>
                            <span class="identity-cell-label">Audience</span>
                            <span class="technical-reference">{{ $env['audience_federation'] }}</span>
                        </span>
                        <span>
                            <span class="status {{ $env['actif'] ? 'status--success' : 'status--warning' }}">
                                {{ $env['actif'] ? 'Actif' : 'Fermé le '.$env['date_fin'] }}
                            </span>
                        </span>
                        @if($env['actif'])
                            <form method="POST" action="{{ route('console.produits.environnements.fermer', [$produit['reference'], $env['id']]) }}"
                                  onsubmit="return confirm('Fermer cet environnement ?');">
                                @csrf
                                <button class="button button--secondary" type="submit">Fermer</button>
                            </form>
                        @else
                            <span aria-hidden="true"></span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('console.produits.environnements.declarer', $produit['reference']) }}" class="form-layout" style="max-width:520px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Déclarer un environnement</h3>
            <div class="field">
                <label for="environnement">Environnement</label>
                <select class="select" id="environnement" name="environnement" required>
                    @foreach($environnementsListe as $valeur)
                        <option value="{{ $valeur }}">{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="api_base_url">URL de l’API</label>
                <input class="input" id="api_base_url" name="api_base_url" maxlength="2048" required
                       placeholder="https://…" autocomplete="off">
                <p class="field-help">HTTPS obligatoire pour l’environnement PRODUCTION.</p>
            </div>
            <div class="field">
                <label for="health_url">URL de santé (facultative)</label>
                <input class="input" id="health_url" name="health_url" maxlength="2048" autocomplete="off">
            </div>
            <div class="field">
                <label for="audience_federation">Audience de fédération</label>
                <input class="input" id="audience_federation" name="audience_federation" maxlength="64" required
                       value="{{ $produit['reference'] }}" autocomplete="off">
                <p class="field-help">Ne peut pas être partagée avec un autre produit actif.</p>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Déclarer</button>
        </form>
    </div>
</section>
@endif

<section class="card">
    <div class="card__header"><h2 class="card__title">Historique</h2></div>
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
