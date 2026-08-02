@extends('layouts.console')

@section('title', $contrat['nom'].' — '.$version['version'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.contrats.index') }}">Contrats</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('console.contrats.show', $contrat['reference']) }}">{{ $contrat['nom'] }}</a>
    <span aria-hidden="true">/</span>
    <span>{{ $version['version'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($contrat['nom'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">{{ $contrat['reference'] }}</p>
        <h1 class="detail-hero__title">Version {{ $version['version'] }}</h1>
        <div class="detail-hero__meta">
            <span class="status {{ $version['etat'] === 'ACTIVE' ? 'status--success' : (in_array($version['etat'], ['RETIREE','SUSPENDUE'], true) ? 'status--danger' : 'status--warning') }}">
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
    <div class="card__header"><h2 class="card__title">Parties ({{ count($version['parties']) }})</h2></div>
    <div class="card__body">
        <div class="identity-list">
            @foreach($version['parties'] as $p)
                <div class="identity-row" style="cursor:default">
                    <span class="identity-main"><span class="technical-reference">{{ $p['partie_reference'] }}</span></span>
                    <span><span class="status status--info">{{ $p['role'] }}</span></span>
                    <span>{{ $p['partie_type'] }}</span>
                    <span aria-hidden="true"></span>
                </div>
            @endforeach
        </div>
        @if($autorite && $version['etat'] === 'BROUILLON')
        <form method="POST" action="{{ route('console.contrats.versions.parties.declarer', [$contrat['reference'], $version['version']]) }}" class="form-layout" style="max-width:560px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Déclarer une partie</h3>
            <div class="field">
                <label for="role">Rôle</label>
                <select class="select" id="role" name="role" required>
                    @foreach($rolesPartie as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="partie_type">Type</label>
                <select class="select" id="partie_type" name="partie_type" required>
                    @foreach($typesPartie as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="partie_reference">Référence</label>
                <input class="input" id="partie_reference" name="partie_reference" maxlength="64" required autocomplete="off">
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Déclarer</button>
        </form>
        @endif
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Opérations ({{ count($version['operations']) }})</h2></div>
    <div class="card__body">
        <div class="identity-list">
            @foreach($version['operations'] as $o)
                <div class="identity-row" style="cursor:default">
                    <span class="identity-main"><span class="technical-reference">{{ $o['reference_operation'] }}</span></span>
                    <span><span class="status status--info">{{ $o['type_operation'] }}</span></span>
                    <span>{{ $o['methode_http'] ? $o['methode_http'].' '.$o['chemin_http'] : '(interne)' }}</span>
                    <span aria-hidden="true"></span>
                </div>
            @endforeach
        </div>
        @if($autorite && $version['etat'] === 'BROUILLON')
        <form method="POST" action="{{ route('console.contrats.versions.operations.declarer', [$contrat['reference'], $version['version']]) }}" class="form-layout" style="max-width:560px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Déclarer une opération</h3>
            <div class="field">
                <label for="reference_operation">Référence</label>
                <input class="input" id="reference_operation" name="reference_operation" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="type_operation">Type</label>
                <select class="select" id="type_operation" name="type_operation" required>
                    @foreach($typesOperation as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="methode_http">Méthode HTTP (facultative)</label>
                <input class="input" id="methode_http" name="methode_http" maxlength="10" autocomplete="off" placeholder="GET, POST…">
            </div>
            <div class="field">
                <label for="chemin_http">Chemin HTTP (facultatif)</label>
                <input class="input" id="chemin_http" name="chemin_http" maxlength="256" autocomplete="off" placeholder="/exemple/{reference}">
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Déclarer</button>
        </form>
        @endif
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Schémas ({{ count($version['schemas']) }})</h2></div>
    <div class="card__body">
        <div class="identity-list">
            @foreach($version['schemas'] as $s)
                <div class="identity-row" style="cursor:default">
                    <span class="identity-main"><span class="technical-reference">{{ $s['operation_reference'] ?? '(contrat)' }}</span></span>
                    <span><span class="status status--info">{{ $s['sens'] }}</span></span>
                    <span>{{ $s['format'] }}</span>
                    <span aria-hidden="true"></span>
                </div>
            @endforeach
        </div>
        @if($autorite && $version['etat'] === 'BROUILLON')
        <form method="POST" action="{{ route('console.contrats.versions.schemas.declarer', [$contrat['reference'], $version['version']]) }}" class="form-layout" style="max-width:560px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Déclarer un schéma</h3>
            <div class="field">
                <label for="operation_reference">Opération (facultative)</label>
                <input class="input" id="operation_reference" name="operation_reference" maxlength="128" autocomplete="off">
            </div>
            <div class="field">
                <label for="sens">Sens</label>
                <select class="select" id="sens" name="sens" required>
                    <option value="ENTREE">ENTREE</option>
                    <option value="SORTIE">SORTIE</option>
                    <option value="EVENEMENT">EVENEMENT</option>
                    <option value="ERREUR">ERREUR</option>
                </select>
            </div>
            <div class="field">
                <label for="format">Format</label>
                <select class="select" id="format" name="format" required>
                    <option value="JSON_SCHEMA">JSON_SCHEMA</option>
                    <option value="OPENAPI_SCHEMA">OPENAPI_SCHEMA</option>
                    <option value="TEXTE_STRUCTURE">TEXTE_STRUCTURE</option>
                    <option value="AUCUN_CORPS">AUCUN_CORPS</option>
                </select>
            </div>
            <div class="field">
                <label for="contenu">Contenu (facultatif)</label>
                <textarea class="input" id="contenu" name="contenu" maxlength="20000" rows="3" placeholder='{"proprietes":{"champ":{"type":"string","requis":true}}}'></textarea>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Déclarer</button>
        </form>
        @endif
    </div>
</section>

<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Erreurs ({{ count($version['erreurs']) }})</h2></div>
    <div class="card__body">
        <div class="identity-list">
            @foreach($version['erreurs'] as $e)
                <div class="identity-row" style="cursor:default">
                    <span class="identity-main"><span class="technical-reference">{{ $e['code'] }}</span></span>
                    <span>{{ $e['statut_http'] ?? '—' }}</span>
                    <span>{{ $e['description'] }}</span>
                    <span aria-hidden="true"></span>
                </div>
            @endforeach
        </div>
        @if($autorite && $version['etat'] === 'BROUILLON')
        <form method="POST" action="{{ route('console.contrats.versions.erreurs.declarer', [$contrat['reference'], $version['version']]) }}" class="form-layout" style="max-width:560px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Déclarer une erreur</h3>
            <div class="field">
                <label for="code">Code</label>
                <input class="input" id="code" name="code" maxlength="128" required autocomplete="off">
            </div>
            <div class="field">
                <label for="statut_http">Statut HTTP (facultatif)</label>
                <input class="input" id="statut_http" name="statut_http" type="number" min="100" max="599" autocomplete="off">
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea class="input" id="description" name="description" maxlength="1000" rows="2" required></textarea>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Déclarer</button>
        </form>
        @endif
    </div>
</section>

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
                — {{ $c['artefact_reference'] }}, le {{ $c['cree_le'] }}
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
            <form method="POST" action="{{ route('console.contrats.versions.soumettre', [$contrat['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Soumettre cette version ? Elle devient immuable : plus aucune partie, opération, schéma ou erreur ne pourra y être ajoutée.');">
                @csrf
                <button class="button button--primary" type="submit">Soumettre à validation</button>
            </form>
        @endif

        @if($version['etat'] === 'EN_VALIDATION')
            <form method="POST" action="{{ route('console.contrats.versions.analyser', [$contrat['reference'], $version['version']]) }}">
                @csrf
                <button class="button button--secondary" type="submit">Analyser la compatibilité</button>
            </form>

            <form method="POST" action="{{ route('console.contrats.versions.conformite', [$contrat['reference'], $version['version']]) }}" class="form-layout" style="max-width:520px">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Enregistrer une conformité</h3>
                <div class="field">
                    <label for="resultat">Résultat</label>
                    <select class="select" id="resultat" name="resultat" required>
                        <option value="CONFORME">CONFORME</option>
                        <option value="NON_CONFORME">NON_CONFORME</option>
                        <option value="INCOMPLET">INCOMPLET</option>
                    </select>
                </div>
                <div class="field">
                    <label for="artefact_reference">Artefact (commit, rapport de test…)</label>
                    <input class="input" id="artefact_reference" name="artefact_reference" maxlength="256" required autocomplete="off">
                </div>
                <button class="button button--secondary" type="submit" style="margin-top:8px">Enregistrer</button>
            </form>

            <form method="POST" action="{{ route('console.contrats.versions.activer', [$contrat['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Activer cette version ? La version active précédente, s’il en existe une, sera remplacée dans la même transaction.');"
                  class="form-layout" style="max-width:520px">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Activer (exige analyse et conformité CONFORME)</h3>
                <div class="field">
                    <label for="plan_migration">Plan de migration (obligatoire si l’analyse a rendu RUPTURE)</label>
                    <textarea class="input" id="plan_migration" name="plan_migration" maxlength="2000" rows="2"></textarea>
                </div>
                <div class="field">
                    <label for="date_limite_migration">Date limite de migration</label>
                    <input class="input" id="date_limite_migration" name="date_limite_migration" type="date" autocomplete="off">
                </div>
                <button class="button button--primary" type="submit" style="margin-top:8px">Activer</button>
            </form>
        @endif

        @if($version['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.contrats.versions.deprecier', [$contrat['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Déprécier cette version ? Elle reste active mais annonce son remplacement à venir.');">
                @csrf
                <button class="button button--secondary" type="submit">Déprécier</button>
            </form>
        @endif

        @if(in_array($version['etat'], ['ACTIVE', 'DEPRECIEE'], true))
            <form method="POST" action="{{ route('console.contrats.versions.suspendre', [$contrat['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Suspendre cette version ? Elle cesse immédiatement de permettre quoi que ce soit.');">
                @csrf
                <button class="button button--secondary" type="submit">Suspendre</button>
            </form>
        @endif

        @if(in_array($version['etat'], ['ACTIVE', 'DEPRECIEE', 'SUSPENDUE'], true))
            <form method="POST" action="{{ route('console.contrats.versions.retirer', [$contrat['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Retirer cette version ? Rien n’est supprimé ; la référence ne sera jamais réutilisée.');">
                @csrf
                <button class="button button--secondary" type="submit">Retirer</button>
            </form>
        @endif
    </div>
</section>
@endif
@endsection
