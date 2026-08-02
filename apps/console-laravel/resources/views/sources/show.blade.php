@extends('layouts.console')

@section('title', $source['nom_affichage'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.sources.index') }}">Sources</a>
    <span aria-hidden="true">/</span>
    <span>{{ $source['nom_affichage'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($source['nom_affichage'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">{{ $source['type_source'] }}</p>
        <h1 class="detail-hero__title">{{ $source['nom_affichage'] }}</h1>
        <div class="detail-hero__meta">
            <span class="status {{ $source['etat'] === 'ACTIVE' ? 'status--success' : ($source['etat'] === 'RETIREE' ? 'status--danger' : 'status--warning') }}">
                {{ $source['etat'] }}
            </span>
        </div>
    </div>
    <span class="technical-reference">{{ $source['reference'] }}</span>
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
            <div class="detail-row"><dt>Nom canonique</dt><dd>{{ $source['nom_canonique'] }}</dd></div>
            <div class="detail-row"><dt>Propriétaire ou responsable</dt><dd class="technical-reference">{{ $source['proprietaire_reference'] }}</dd></div>
            <div class="detail-row"><dt>Produit producteur</dt><dd class="technical-reference">{{ $source['produit_producteur_reference'] ?? '—' }}</dd></div>
            <div class="detail-row"><dt>Catégorie</dt><dd>{{ $source['categorie'] ?? '—' }}</dd></div>
            <div class="detail-row"><dt>Description</dt><dd>{{ $source['description'] ?? '—' }}</dd></div>
            <div class="detail-row"><dt>Réserve</dt><dd>{{ $source['reserve'] ?? '—' }}</dd></div>
            <div class="detail-row"><dt>Authenticité historique</dt><dd>{{ $source['authenticite_legacy'] ?? '—' }}</dd></div>
            <div class="detail-row"><dt>Depuis</dt><dd>{{ $source['depuis'] }}</dd></div>
            <div class="detail-row"><dt>Révision courante</dt><dd>{{ $source['numero_revision'] }}</dd></div>
            <div class="detail-row"><dt>Modifié le</dt><dd>{{ $source['modifie_le'] }}</dd></div>
        </dl>
    </div>
</section>

@if($autorite)
<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Cycle de vie</h2></div>
    <div class="card__body" style="display:flex;gap:12px;flex-wrap:wrap">
        @if(in_array($source['etat'], ['PREPARATION', 'SUSPENDUE'], true))
            <form method="POST" action="{{ route('console.sources.activer', $source['reference']) }}">
                @csrf
                <button class="button button--primary" type="submit">Activer</button>
            </form>
        @endif
        @if($source['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.sources.suspendre', $source['reference']) }}"
                  onsubmit="return confirm('Suspendre {{ $source['nom_affichage'] }} ? Tout nouvel usage se ferme immédiatement.');">
                @csrf
                <button class="button button--secondary" type="submit">Suspendre</button>
            </form>
        @endif
        @if($source['etat'] !== 'RETIREE')
            <form method="POST" action="{{ route('console.sources.retirer', $source['reference']) }}"
                  onsubmit="return confirm('Retirer {{ $source['nom_affichage'] }} ? C’est irréversible ; son historique reste consultable.');">
                @csrf
                <button class="button button--secondary" type="submit">Retirer</button>
            </form>
        @endif
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Métadonnées</h2></div>
    <div class="card__body">
        <form method="POST" action="{{ route('console.sources.modifier', $source['reference']) }}" class="form-layout" style="max-width:520px">
            @csrf
            <div class="field">
                <label for="nom_affichage">Nom d’exploitation</label>
                <input class="input" id="nom_affichage" name="nom_affichage" value="{{ $source['nom_affichage'] }}" maxlength="255">
            </div>
            <div class="field">
                <label for="categorie">Catégorie</label>
                <input class="input" id="categorie" name="categorie" value="{{ $source['categorie'] }}" maxlength="500">
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea class="input" id="description" name="description" maxlength="2000" rows="3">{{ $source['description'] }}</textarea>
            </div>
            <div class="field">
                <label for="proprietaire_reference">Propriétaire ou responsable</label>
                <input class="input" id="proprietaire_reference" name="proprietaire_reference" value="{{ $source['proprietaire_reference'] }}" maxlength="64">
            </div>
            <div class="field">
                <label for="produit_producteur_reference">Produit producteur</label>
                <input class="input" id="produit_producteur_reference" name="produit_producteur_reference" value="{{ $source['produit_producteur_reference'] }}" maxlength="64">
            </div>
            <div class="field">
                <label for="reserve">Réserve</label>
                <textarea class="input" id="reserve" name="reserve" maxlength="2000" rows="3">{{ $source['reserve'] }}</textarea>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Enregistrer (nouvelle révision)</button>
        </form>
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Finalités</h2></div>
    <div class="card__body">
        @if($finalites === [])
            <div class="empty-state" style="padding:28px 18px">
                <div class="empty-state__symbol" aria-hidden="true">⌕</div>
                <h2>Aucune finalité déclarée</h2>
                <p>Sans finalité déclarée, cette source reste inutilisable pour un nouveau traitement.</p>
            </div>
        @else
            <div class="identity-list">
                @foreach($finalites as $finalite)
                    <div class="identity-row" style="cursor:default">
                        <span class="identity-main">
                            <span style="min-width:0">
                                <span class="identity-name">{{ $finalite['finalite_reference'] }}</span>
                                <span class="technical-reference">{{ $finalite['produit_consommateur_reference'] ?? 'Aucun consommateur précis' }}</span>
                            </span>
                        </span>
                        <span>
                            <span class="identity-cell-label">Période</span>
                            {{ $finalite['date_debut'] }} @if($finalite['date_fin']) → {{ $finalite['date_fin'] }} @endif
                        </span>
                        <span>
                            <span class="status {{ $finalite['actif'] ? 'status--success' : 'status--warning' }}">
                                {{ $finalite['actif'] ? 'Active' : 'Fermée' }}
                            </span>
                        </span>
                        @if($finalite['actif'])
                            <form method="POST" action="{{ route('console.sources.finalites.fermer', [$source['reference'], $finalite['id']]) }}"
                                  onsubmit="return confirm('Fermer cette finalité ?');">
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

        <form method="POST" action="{{ route('console.sources.finalites.declarer', $source['reference']) }}" class="form-layout" style="max-width:520px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Déclarer une finalité</h3>
            <div class="field">
                <label for="finalite_reference">Finalité</label>
                <input class="input" id="finalite_reference" name="finalite_reference" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="produit_consommateur_reference">Produit consommateur (facultatif)</label>
                <input class="input" id="produit_consommateur_reference" name="produit_consommateur_reference" maxlength="64" autocomplete="off">
                <p class="field-help">Une référence précise est préférable à une portée universelle.</p>
            </div>
            <div class="field">
                <label for="date_debut">Date de début</label>
                <input class="input" id="date_debut" type="date" name="date_debut" autocomplete="off">
            </div>
            <div class="field">
                <label for="date_fin">Date de fin (facultative)</label>
                <input class="input" id="date_fin" type="date" name="date_fin" autocomplete="off">
            </div>
            <div class="field">
                <label for="restriction">Restriction (facultative)</label>
                <input class="input" id="restriction" name="restriction" maxlength="1000" autocomplete="off">
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Déclarer</button>
        </form>
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Vérifications</h2></div>
    <div class="card__body">
        @if($verifications === [])
            <div class="empty-state" style="padding:28px 18px">
                <div class="empty-state__symbol" aria-hidden="true">⌕</div>
                <h2>Aucune vérification enregistrée</h2>
                <p>Le niveau courant reste NON_VERIFIEE tant qu’aucune vérification n’est enregistrée.</p>
            </div>
        @else
            <div class="identity-list">
                @foreach($verifications as $verification)
                    <div class="identity-row" style="cursor:default">
                        <span class="identity-main">
                            <span style="min-width:0">
                                <span class="identity-name">{{ $verification['niveau'] }}</span>
                                <span class="technical-reference">{{ $verification['verifie_par_reference'] }}</span>
                            </span>
                        </span>
                        <span>
                            <span class="identity-cell-label">Résultat</span>
                            {{ $verification['resultat'] }}
                        </span>
                        <span>
                            <span class="identity-cell-label">Le</span>
                            {{ $verification['verifie_le'] }}@if($verification['expire_le']) &middot; expire {{ $verification['expire_le'] }} @endif
                        </span>
                        <span aria-hidden="true"></span>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('console.sources.verifications.enregistrer', $source['reference']) }}" class="form-layout" style="max-width:520px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Enregistrer une vérification</h3>
            <div class="field">
                <label for="niveau">Niveau</label>
                <select class="select" id="niveau" name="niveau" required>
                    @foreach($niveaux as $valeur)
                        <option value="{{ $valeur }}">{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="resultat">Résultat</label>
                <select class="select" id="resultat" name="resultat" required>
                    @foreach($resultats as $valeur)
                        <option value="{{ $valeur }}">{{ $valeur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="verifie_par_reference">Vérifié par</label>
                <input class="input" id="verifie_par_reference" name="verifie_par_reference" maxlength="64" required autocomplete="off"
                       placeholder="Distinct du producteur pour ATTESTEE">
            </div>
            <div class="field">
                <label for="expire_le">Expire le (facultatif)</label>
                <input class="input" id="expire_le" type="date" name="expire_le" autocomplete="off">
            </div>
            <div class="field">
                <label for="motif">Motif (facultatif)</label>
                <input class="input" id="motif" name="motif" maxlength="1000" autocomplete="off">
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Enregistrer</button>
        </form>
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Lignée</h2></div>
    <div class="card__body">
        <h3 class="form-section__title" style="font-size:14px">Amont (dont elle dérive)</h3>
        @if(($lignee['amont'] ?? []) === [])
            <p class="field-help">Aucune parente déclarée.</p>
        @else
            <div class="identity-list">
                @foreach($lignee['amont'] as $relation)
                    <div class="identity-row" style="cursor:default">
                        <span class="identity-main"><span class="technical-reference">{{ $relation['reference'] }}</span></span>
                        <span>{{ $relation['type_relation'] }}</span>
                        <span>{{ $relation['date_effet'] }}</span>
                        <span aria-hidden="true"></span>
                    </div>
                @endforeach
            </div>
        @endif

        <h3 class="form-section__title" style="font-size:14px;margin-top:18px">Aval (ce qui en dérive)</h3>
        @if(($lignee['aval'] ?? []) === [])
            <p class="field-help">Aucune dérivée déclarée.</p>
        @else
            <div class="identity-list">
                @foreach($lignee['aval'] as $relation)
                    <div class="identity-row" style="cursor:default">
                        <span class="identity-main"><span class="technical-reference">{{ $relation['reference'] }}</span></span>
                        <span>{{ $relation['type_relation'] }}</span>
                        <span>{{ $relation['date_effet'] }}</span>
                        <span aria-hidden="true"></span>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('console.sources.lignee.declarer', $source['reference']) }}" class="form-layout" style="max-width:520px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Déclarer une relation de lignée</h3>
            <div class="field">
                <label for="source_parente_reference">Source parente</label>
                <input class="input" id="source_parente_reference" name="source_parente_reference" maxlength="64" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_relation">Type de relation</label>
                <select class="select" id="type_relation" name="type_relation" required>
                    @foreach($typesLignee as $valeur)
                        <option value="{{ $valeur }}">{{ $valeur }}</option>
                    @endforeach
                </select>
                <p class="field-help">Tout cycle de lignée est refusé avant écriture.</p>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Déclarer</button>
        </form>
    </div>
</section>
@endif

<section class="card">
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
