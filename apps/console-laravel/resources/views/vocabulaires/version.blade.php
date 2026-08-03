@extends('layouts.console')

@section('title', $vocabulaire['nom'].' — '.$version['version'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.vocabulaires.index') }}">Vocabulaire</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('console.vocabulaires.show', $vocabulaire['reference']) }}">{{ $vocabulaire['nom'] }}</a>
    <span aria-hidden="true">/</span>
    <span>{{ $version['version'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($vocabulaire['nom'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">{{ $vocabulaire['reference'] }}</p>
        <h1 class="detail-hero__title">Version {{ $version['version'] }}</h1>
        <div class="detail-hero__meta">
            <span class="status {{ $version['etat'] === 'ACTIVE' ? 'status--success' : ($version['etat'] === 'RETIREE' ? 'status--danger' : 'status--warning') }}">
                {{ $version['etat'] }}
            </span>
        </div>
    </div>
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
    <div class="card__header"><h2 class="card__title">Termes ({{ count($version['termes']) }})</h2></div>
    <div class="card__body">
        <div class="identity-list">
            @foreach($version['termes'] as $t)
                <div class="identity-row" style="cursor:default;flex-wrap:wrap">
                    <span class="identity-main"><span class="technical-reference">{{ $t['code'] }}</span></span>
                    <span><span class="status status--info">{{ $t['type_semantique'] }}</span></span>
                    <span>{{ $t['definition'] }}</span>
                    <span>
                        @if($t['date_fin'])
                            <span class="status status--danger">fin {{ $t['date_fin'] }}</span>
                        @else
                            <span class="technical-reference">{{ $t['reference'] }}</span>
                        @endif
                    </span>
                    <span aria-hidden="true"></span>
                </div>
                @if($t['libelles'] || $t['alias'] || $t['relations'] || $t['mappings'] || $t['usages'])
                <div class="identity-row" style="cursor:default;padding-top:0;padding-bottom:14px;font-size:13px;color:var(--muted, #6b7280)">
                    <span style="grid-column:1/-1">
                        @foreach($t['libelles'] as $l)<span class="status status--info" style="margin-right:6px">{{ $l['locale'] }}: {{ $l['libelle'] }}</span>@endforeach
                        @foreach($t['alias'] as $a)<span class="status" style="margin-right:6px">alias {{ $a['alias'] }}</span>@endforeach
                        @foreach($t['relations'] as $r)<span class="status" style="margin-right:6px">{{ $r['type_relation'] }} → {{ $r['terme_cible_reference'] === $t['reference'] ? $r['terme_source_reference'] : $r['terme_cible_reference'] }}</span>@endforeach
                        @foreach($t['mappings'] as $m)<span class="status" style="margin-right:6px">↔ {{ $m['systeme_reference'] }}:{{ $m['code_externe'] }}</span>@endforeach
                        @foreach($t['usages'] as $u)<span class="status" style="margin-right:6px">usage {{ $u['usage_type'] }}</span>@endforeach
                    </span>
                </div>
                @endif
            @endforeach
        </div>

        @if($autorite && $version['etat'] === 'BROUILLON')
        <form method="POST" action="{{ route('console.vocabulaires.versions.termes.ajouter', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:560px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Ajouter un terme</h3>
            <div class="field">
                <label for="terme_reference">Référence</label>
                <input class="input" id="terme_reference" name="reference" maxlength="128" required autocomplete="off" placeholder="Ex. TERM-GAMAD-EXEMPLE-ETAT-ACTIF">
            </div>
            <div class="field">
                <label for="code">Code (MAJUSCULES_SOULIGNEES)</label>
                <input class="input" id="code" name="code" maxlength="64" required autocomplete="off" placeholder="ACTIF">
            </div>
            <div class="field">
                <label for="type_semantique">Type sémantique</label>
                <select class="select" id="type_semantique" name="type_semantique" required>
                    @foreach($typesSemantiques as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="definition">Définition</label>
                <textarea class="input" id="definition" name="definition" maxlength="2000" rows="2" required></textarea>
            </div>
            <div class="field">
                <label for="ordre_affichage">Ordre d’affichage (facultatif)</label>
                <input class="input" id="ordre_affichage" name="ordre_affichage" type="number" autocomplete="off">
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Ajouter</button>
        </form>
        @endif

        @if($autorite && $version['etat'] === 'BROUILLON')
        <form method="POST" action="{{ route('console.vocabulaires.versions.termes.evoluer', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:560px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Faire évoluer un terme d’une version précédente</h3>
            <p class="field-help">Reconduit un terme existant, avec ou sans changement de code/type/définition, en conservant la lignée pour l’analyse de compatibilité.</p>
            <div class="field">
                <label for="ancienne_reference">Référence du terme d’origine</label>
                <input class="input" id="ancienne_reference" name="ancienne_reference" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="nouvelle_reference">Nouvelle référence</label>
                <input class="input" id="nouvelle_reference" name="reference" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="code_evolution">Nouveau code (facultatif — sinon inchangé)</label>
                <input class="input" id="code_evolution" name="code" maxlength="64" autocomplete="off">
            </div>
            <div class="field">
                <label for="definition_evolution">Nouvelle définition (facultative — sinon inchangée)</label>
                <textarea class="input" id="definition_evolution" name="definition" maxlength="2000" rows="2"></textarea>
            </div>
            <button class="button button--secondary" type="submit" style="margin-top:8px">Faire évoluer</button>
        </form>
        @endif
    </div>
</section>

@if($autorite && $version['etat'] === 'BROUILLON')
<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Libellés et alias d’un terme</h2></div>
    <div class="card__body" style="display:flex;gap:24px;flex-wrap:wrap">
        <form method="POST" action="{{ route('console.vocabulaires.versions.termes.libelles.ajouter', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:360px;flex:1">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Ajouter un libellé</h3>
            <div class="field">
                <label for="libelle_terme_reference">Référence du terme</label>
                <input class="input" id="libelle_terme_reference" name="terme_reference" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="locale">Locale</label>
                <select class="select" id="locale" name="locale" required>
                    @foreach($locales as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="libelle">Libellé</label>
                <input class="input" id="libelle" name="libelle" maxlength="255" required autocomplete="off">
            </div>
            <button class="button button--secondary" type="submit" style="margin-top:8px">Ajouter</button>
        </form>

        <form method="POST" action="{{ route('console.vocabulaires.versions.termes.alias.ajouter', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:360px;flex:1">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Ajouter un alias</h3>
            <div class="field">
                <label for="alias_terme_reference">Référence du terme</label>
                <input class="input" id="alias_terme_reference" name="terme_reference" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="alias">Alias</label>
                <input class="input" id="alias" name="alias" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_alias">Type</label>
                <select class="select" id="type_alias" name="type_alias" required>
                    @foreach($typesAlias as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="alias_source_reference">Source</label>
                <input class="input" id="alias_source_reference" name="source_reference" maxlength="256" required autocomplete="off">
            </div>
            <button class="button button--secondary" type="submit" style="margin-top:8px">Ajouter</button>
        </form>
    </div>
</section>
@endif

@if($compatibilites !== [] || $conformites !== [])
<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Compatibilité et conformité</h2></div>
    <div class="card__body">
        @foreach($compatibilites as $a)
            <p>
                <span class="status {{ $a['resultat'] === 'RUPTURE' ? 'status--danger' : ($a['resultat'] === 'COMPATIBLE' ? 'status--success' : 'status--warning') }}">{{ $a['resultat'] }}</span>
                — {{ count($a['divergences']) }} divergence(s), le {{ $a['cree_le'] }}
            </p>
        @endforeach
        @foreach($conformites as $c)
            <p>
                <span class="status {{ $c['resultat'] === 'CONFORME' ? 'status--success' : 'status--danger' }}">{{ $c['resultat'] }}</span>
                — {{ $c['consommateur_reference'] }}, le {{ $c['execute_le'] }}
            </p>
        @endforeach
    </div>
</section>
@endif

@if($autorite)
<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Cycle</h2></div>
    <div class="card__body" style="display:flex;flex-direction:column;gap:20px">
        @if($version['etat'] === 'BROUILLON')
            <form method="POST" action="{{ route('console.vocabulaires.versions.soumettre', [$vocabulaire['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Soumettre cette version ? Elle devient immuable : plus aucun terme, libellé, alias ou évolution ne pourra y être ajouté.');">
                @csrf
                <button class="button button--primary" type="submit">Soumettre à validation</button>
            </form>
        @endif

        @if($version['etat'] === 'EN_VALIDATION')
            <form method="POST" action="{{ route('console.vocabulaires.versions.analyser', [$vocabulaire['reference'], $version['version']]) }}">
                @csrf
                <button class="button button--secondary" type="submit">Analyser la compatibilité</button>
            </form>

            <form method="POST" action="{{ route('console.vocabulaires.versions.projections.generer', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:520px">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Générer une projection</h3>
                <div class="field">
                    <label for="type_projection">Type</label>
                    <select class="select" id="type_projection" name="type_projection" required>
                        @foreach($typesProjection as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                    </select>
                </div>
                <button class="button button--secondary" type="submit" style="margin-top:8px">Générer</button>
            </form>

            <form method="POST" action="{{ route('console.vocabulaires.versions.conformite', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:520px">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Enregistrer une conformité</h3>
                <div class="field">
                    <label for="resultat">Résultat</label>
                    <select class="select" id="resultat" name="resultat" required>
                        @foreach($resultatsConformite as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="consommateur_reference">Consommateur (capacité ou produit)</label>
                    <input class="input" id="consommateur_reference" name="consommateur_reference" maxlength="64" required autocomplete="off" placeholder="Ex. CAP-CORE-010">
                </div>
                <div class="field">
                    <label for="type_consommateur">Type de consommateur</label>
                    <select class="select" id="type_consommateur" name="type_consommateur" required>
                        @foreach($typesConsommateur as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="commit_reference">Commit (facultatif)</label>
                    <input class="input" id="commit_reference" name="commit_reference" maxlength="128" autocomplete="off">
                </div>
                <button class="button button--secondary" type="submit" style="margin-top:8px">Enregistrer</button>
            </form>

            <form method="POST" action="{{ route('console.vocabulaires.versions.activer', [$vocabulaire['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Activer cette version ? La version active précédente, s’il en existe une, sera remplacée dans la même transaction.');"
                  class="form-layout" style="max-width:520px">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Activer (exige analyse, projection et conformité CONFORME)</h3>
                <div class="field">
                    <label for="motif">Motif (facultatif)</label>
                    <textarea class="input" id="motif" name="motif" maxlength="500" rows="2"></textarea>
                </div>
                <button class="button button--primary" type="submit" style="margin-top:8px">Activer</button>
            </form>
        @endif

        @if($version['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.vocabulaires.versions.deprecier', [$vocabulaire['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Déprécier cette version ? Elle reste active mais annonce son remplacement à venir.');">
                @csrf
                <button class="button button--secondary" type="submit">Déprécier</button>
            </form>
        @endif

        @if(in_array($version['etat'], ['ACTIVE', 'DEPRECIEE'], true))
            <form method="POST" action="{{ route('console.vocabulaires.versions.retirer', [$vocabulaire['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Retirer cette version ? Rien n’est supprimé ; la référence ne sera jamais réutilisée.');">
                @csrf
                <button class="button button--secondary" type="submit">Retirer</button>
            </form>
        @endif

        @if(in_array($version['etat'], ['ACTIVE', 'DEPRECIEE'], true))
        <div style="display:flex;gap:24px;flex-wrap:wrap;border-top:1px solid var(--border, #e5e7eb);padding-top:18px">
            <form method="POST" action="{{ route('console.vocabulaires.versions.termes.deprecier', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:360px;flex:1">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Déprécier un terme</h3>
                <div class="field">
                    <label for="deprecier_terme_reference">Référence du terme</label>
                    <input class="input" id="deprecier_terme_reference" name="terme_reference" maxlength="128" required autocomplete="off">
                </div>
                <div class="field">
                    <label for="remplace_par_reference">Remplacé par (facultatif)</label>
                    <input class="input" id="remplace_par_reference" name="remplace_par_reference" maxlength="128" autocomplete="off">
                </div>
                <button class="button button--secondary" type="submit" style="margin-top:8px">Déprécier</button>
            </form>

            <form method="POST" action="{{ route('console.vocabulaires.versions.termes.retirer', [$vocabulaire['reference'], $version['version']]) }}" class="form-layout" style="max-width:360px;flex:1"
                  onsubmit="return confirm('Retirer ce terme ? Refusé si un usage obligatoire actif en dépend encore.');">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Retirer un terme</h3>
                <div class="field">
                    <label for="retirer_terme_reference">Référence du terme</label>
                    <input class="input" id="retirer_terme_reference" name="terme_reference" maxlength="128" required autocomplete="off">
                </div>
                <button class="button button--secondary" type="submit" style="margin-top:8px">Retirer</button>
            </form>
        </div>
        @endif
    </div>
</section>
@endif
@endsection
