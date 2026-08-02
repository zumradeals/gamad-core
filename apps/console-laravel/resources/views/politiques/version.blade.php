@extends('layouts.console')

@section('title', $politique['libelle'].' — '.$version['version'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.politiques.index') }}">Politiques</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('console.politiques.show', $politique['reference']) }}">{{ $politique['libelle'] }}</a>
    <span aria-hidden="true">/</span>
    <span>{{ $version['version'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($politique['libelle'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">{{ $politique['reference'] }}</p>
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
    <div class="card__header"><h2 class="card__title">Règles ({{ count($version['regles']) }})</h2></div>
    <div class="card__body">
        @if($version['regles'] === [])
            <div class="empty-state" style="padding:28px 18px">
                <div class="empty-state__symbol" aria-hidden="true">⌕</div>
                <h2>Aucune règle</h2>
                <p>Une version sans règle ne peut pas être soumise.</p>
            </div>
        @else
            <div class="identity-list">
                @foreach($version['regles'] as $regle)
                    <div class="identity-row" style="cursor:default">
                        <span class="identity-main">
                            <span style="min-width:0">
                                <span class="identity-name">{{ $regle['action_reference'] }}</span>
                                <span class="technical-reference">{{ $regle['sujet_reference'] ?? 'tout sujet' }}</span>
                            </span>
                        </span>
                        <span>
                            <span class="status {{ $regle['effet'] === 'PERMET' ? 'status--success' : 'status--danger' }}">
                                {{ $regle['effet'] }}
                            </span>
                        </span>
                        <span>{{ $regle['motif'] }}</span>
                        <span aria-hidden="true"></span>
                    </div>
                @endforeach
            </div>
        @endif

        @if($autorite && $version['etat'] === 'BROUILLON')
        <form method="POST" action="{{ route('console.politiques.versions.regles.ajouter', [$politique['reference'], $version['version']]) }}" class="form-layout" style="max-width:560px;margin-top:18px">
            @csrf
            <h3 class="form-section__title" style="font-size:15px">Ajouter une règle</h3>
            <div class="field">
                <label for="effet">Effet</label>
                <select class="select" id="effet" name="effet" required>
                    @foreach($effets as $e)
                        <option value="{{ $e }}">{{ $e }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="action_reference">Action</label>
                <input class="input" id="action_reference" name="action_reference" maxlength="256" required autocomplete="off">
            </div>
            <div class="field">
                <label for="sujet_reference">Sujet (facultatif — vide = tout sujet)</label>
                <input class="input" id="sujet_reference" name="sujet_reference" maxlength="64" autocomplete="off">
            </div>
            <div class="field">
                <label for="motif">Motif</label>
                <textarea class="input" id="motif" name="motif" maxlength="2000" rows="2" required></textarea>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Ajouter</button>
        </form>
        @endif
    </div>
</section>

@if($autorite)
<section class="card" style="margin-bottom:22px">
    <div class="card__header"><h2 class="card__title">Cycle</h2></div>
    <div class="card__body" style="display:flex;flex-direction:column;gap:16px">
        @if($version['etat'] === 'BROUILLON')
            <form method="POST" action="{{ route('console.politiques.versions.soumettre', [$politique['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Soumettre cette version ? Elle devient immuable : plus aucune règle ne pourra y être ajoutée ou modifiée.');">
                @csrf
                <button class="button button--primary" type="submit">Soumettre à validation</button>
            </form>
        @endif

        @if($version['etat'] === 'EN_VALIDATION')
            <form method="POST" action="{{ route('console.politiques.versions.simuler', [$politique['reference'], $version['version']]) }}" class="form-layout" style="max-width:640px">
                @csrf
                <h3 class="form-section__title" style="font-size:15px">Simuler (obligatoire avant activation)</h3>
                <input type="hidden" name="jeu_reference" value="CONSOLE-{{ now()->format('YmdHis') }}">
                @foreach($version['regles'] as $i => $regle)
                    <div class="field" style="display:flex;gap:8px;align-items:center">
                        <input type="hidden" name="sujet[]" value="{{ $regle['sujet_reference'] ?? 'AUT-GAMAD-001' }}">
                        <input type="hidden" name="action[]" value="{{ $regle['action_reference'] }}">
                        <input type="hidden" name="attendu[]" value="{{ $regle['effet'] === 'PERMET' ? 'PERMIS' : 'REFUSE' }}">
                        <span class="technical-reference">{{ $regle['action_reference'] }}</span>
                        <span>→ attendu {{ $regle['effet'] === 'PERMET' ? 'PERMIS' : 'REFUSE' }}</span>
                    </div>
                @endforeach
                <p class="field-help">Rejoue chaque règle de cette version comme son propre cas de non-régression.</p>
                <button class="button button--primary" type="submit" style="margin-top:8px" @disabled($version['regles'] === [])>Lancer la simulation</button>
            </form>

            <form method="POST" action="{{ route('console.politiques.versions.activer', [$politique['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Activer cette version ? La version active précédente, s’il en existe une, sera remplacée dans la même transaction.');">
                @csrf
                <button class="button button--secondary" type="submit">Activer (exige une simulation réussie)</button>
            </form>
        @endif

        @if($version['etat'] === 'ACTIVE')
            <form method="POST" action="{{ route('console.politiques.versions.suspendre', [$politique['reference'], $version['version']]) }}"
                  onsubmit="return confirm('Suspendre cette version ? Elle cesse immédiatement de permettre quoi que ce soit.');">
                @csrf
                <button class="button button--secondary" type="submit">Suspendre</button>
            </form>
        @endif
    </div>
</section>
@endif
@endsection
