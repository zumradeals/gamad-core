@extends('layouts.console')

@section('title', $realm['revision']['nom_affichage'] ?? $realm['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.realms.index') }}">Realms</a>
    <span aria-hidden="true">/</span>
    <span>{{ $realm['revision']['nom_affichage'] ?? $realm['reference'] }}</span>
</nav>

@if(session('succes'))
    <div class="alert alert--success" style="margin-bottom:18px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span><span class="alert__title">{{ session('succes') }}</span>
        @if(session('preuve'))
            <span class="alert__detail technical-reference">preuve {{ session('preuve')['reference'] ?? '' }}</span>
        @endif
        </span>
    </div>
@endif
@if($errors->any())
    <div class="form-error" role="alert" style="margin-bottom:18px">
        <span aria-hidden="true">!</span><span>{{ $errors->first() }}</span>
    </div>
@endif

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $realm['type_realm_reference'] }} — identité canonique {{ $realm['identite_reference'] }}</p>
        <h1 class="page-title">{{ $realm['revision']['nom_affichage'] ?? '(aucune révision)' }}</h1>
        <p class="page-subtitle technical-reference">{{ $realm['reference'] }} — {{ $realm['code_canonique'] }}</p>
    </div>
    <span class="status {{ $realm['etat'] === 'ACTIF' ? 'status--success' : (in_array($realm['etat'], ['FERME','RETIRE']) ? 'status--danger' : 'status--warning') }}">
        {{ $realm['etat'] }}
    </span>
</header>

@if($autorite)
<div class="action-bar">
    @if(in_array($realm['etat'], ['PREPARATION','SUSPENDU']))
        <form method="POST" action="{{ route('console.realms.activer', $realm['reference']) }}"
              onsubmit="return confirm('Activer ce realm ?');">
            @csrf<button class="button button--primary" type="submit">Activer</button>
        </form>
    @endif
    @if($realm['etat'] === 'ACTIF')
        <form method="POST" action="{{ route('console.realms.suspendre', $realm['reference']) }}"
              onsubmit="return confirm('Suspendre ce realm ?');">
            @csrf<button class="button" type="submit">Suspendre</button>
        </form>
    @endif
    @if(in_array($realm['etat'], ['ACTIF','SUSPENDU']))
        <form method="POST" action="{{ route('console.realms.fermer', $realm['reference']) }}"
              onsubmit="return confirm('Fermer ce realm ?');">
            @csrf<button class="button button--danger" type="submit">Fermer</button>
        </form>
    @endif
    @if($realm['etat'] !== 'RETIRE')
        <form method="POST" action="{{ route('console.realms.retirer', $realm['reference']) }}"
              onsubmit="return confirm('Retirer irréversiblement ce realm du registre ?');">
            @csrf
            <input type="hidden" name="motif_reference" value="RETRAIT_DEPUIS_CONSOLE">
            <button class="button button--danger" type="submit">Retirer</button>
        </form>
    @endif
</div>
@endif

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Fiche du realm</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Classification</dt><dd>{{ $realm['revision']['classification_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Organisation responsable</dt><dd class="technical-reference">{{ $realm['revision']['organisation_responsable_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Description</dt><dd>{{ $realm['revision']['description'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Source</dt><dd>{{ $realm['source_reference'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Hiérarchie</h2></div>
        <div class="card__body">
            <p><strong>Parents :</strong>
                @forelse($parents as $p) <span class="technical-reference">{{ $p['realm_source_reference'] }}</span> @empty — @endforelse
            </p>
            <p><strong>Enfants :</strong>
                @forelse($enfants as $e) <span class="technical-reference">{{ $e['realm_cible_reference'] }}</span> @empty — @endforelse
            </p>
            <p><strong>Autres relations :</strong>
                @php $autresRelations = array_filter($relations, fn($r) => $r['type_relation_reference'] !== 'PARENT_DE'); @endphp
                @forelse($autresRelations as $r)
                    <span class="technical-reference">{{ $r['type_relation_reference'] }} → {{ $r['realm_source_reference'] === $realm['reference'] ? $r['realm_cible_reference'] : $r['realm_source_reference'] }}</span>
                @empty
                    —
                @endforelse
            </p>
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.relations.declarer', $realm['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <select class="select" name="type_relation_reference" required>
                    @foreach($typesRelation as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <input class="input" name="realm_cible_reference" placeholder="realm cible" required>
                <button class="button" type="submit">Déclarer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Périmètres</h2></div>
        <div class="card__body">
            @forelse($perimetres as $perimetre)
                <div class="summary-row"><dt>{{ $perimetre['dimension_reference'] }}</dt><dd>{{ $perimetre['valeur_reference'] }}</dd></div>
            @empty
                <p>Aucun périmètre déclaré.</p>
            @endforelse
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.perimetres.declarer', $realm['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <select class="select" name="dimension_reference" required>
                    @foreach($dimensionsPerimetre as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <input class="input" name="valeur_reference" placeholder="valeur" required>
                <button class="button" type="submit">Déclarer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Identifiants externes</h2></div>
        <div class="card__body">
            @forelse($identifiants as $identifiant)
                <div class="summary-row"><dt>{{ $identifiant['systeme_reference'] }}</dt><dd>{{ $identifiant['valeur'] }}</dd></div>
            @empty
                <p>Aucun identifiant externe déclaré.</p>
            @endforelse
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.identifiants.declarer', $realm['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <input class="input" name="systeme_reference" placeholder="système (ex. ISO-3166-1)" required>
                <input class="input" name="valeur" placeholder="valeur" required>
                <button class="button" type="submit">Déclarer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Organisations rattachées</h2></div>
        <div class="card__body">
            @forelse($organisations as $organisation)
                <div class="summary-row"><dt class="technical-reference">{{ $organisation['organisation_reference'] }}</dt><dd>{{ $organisation['role_reference'] }}</dd></div>
            @empty
                <p>Aucune organisation rattachée.</p>
            @endforelse
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.organisations.rattacher', $realm['reference']) }}" class="inline-form" style="margin-top:12px"
                  onsubmit="return confirm('Confirmer le rattachement de cette organisation ?');">
                @csrf
                <input class="input" name="organisation_reference" placeholder="ORG-GAMAD-…" required>
                <select class="select" name="role_reference" required>
                    @foreach($rolesOrganisation as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <select class="select" name="classification_reference" required>
                    @foreach($classifications as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <button class="button" type="submit">Rattacher</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Produits rattachés</h2></div>
        <div class="card__body">
            @forelse($produits as $produit)
                <div class="summary-row"><dt class="technical-reference">{{ $produit['produit_reference'] }}</dt><dd>{{ $produit['role_reference'] }} {{ $produit['environnement_reference'] ? '('.$produit['environnement_reference'].')' : '' }}</dd></div>
            @empty
                <p>Aucun produit rattaché.</p>
            @endforelse
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.produits.rattacher', $realm['reference']) }}" class="inline-form" style="margin-top:12px"
                  onsubmit="return confirm('Confirmer le rattachement de ce produit ?');">
                @csrf
                <input class="input" name="produit_reference" placeholder="PRD-…" required>
                <select class="select" name="role_reference" required>
                    @foreach($rolesProduit as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <input class="input" name="environnement_reference" placeholder="environnement (facultatif)">
                <button class="button" type="submit">Rattacher</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Contrats rattachés</h2></div>
        <div class="card__body">
            @forelse($contrats as $contrat)
                <div class="summary-row"><dt class="technical-reference">{{ $contrat['contrat_reference'] }}</dt><dd>{{ $contrat['role_reference'] }}</dd></div>
            @empty
                <p>Aucun contrat rattaché.</p>
            @endforelse
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.contrats.rattacher', $realm['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <input class="input" name="contrat_reference" placeholder="CTR-…" required>
                <select class="select" name="role_reference" required>
                    @foreach($rolesContrat as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <button class="button" type="submit">Rattacher</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Franchissements</h2></div>
        <div class="card__body">
            @forelse($franchissements as $franchissement)
                <div class="summary-row">
                    <dt class="technical-reference">{{ $franchissement['realm_source_reference'] }} → {{ $franchissement['realm_cible_reference'] }}</dt>
                    <dd>{{ $franchissement['effet_reference'] }} — {{ $franchissement['objet_reference'] }} ({{ $franchissement['finalite_reference'] }})</dd>
                </div>
            @empty
                <p>Aucun franchissement déclaré. Refus par défaut.</p>
            @endforelse
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.franchissements.declarer', $realm['reference']) }}" class="inline-form" style="margin-top:12px"
                  onsubmit="return confirm('Confirmer la déclaration de ce franchissement ?');">
                @csrf
                <input class="input" name="realm_cible_reference" placeholder="realm cible" required>
                <input class="input" name="objet_reference" placeholder="objet (ex. operation.x)" required>
                <input class="input" name="type_objet_reference" placeholder="type d’objet" required>
                <select class="select" name="effet_reference" required>
                    @foreach($effetsFranchissement as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <input class="input" name="finalite_reference" placeholder="finalité" required>
                <button class="button" type="submit">Déclarer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Vérification courante</h2></div>
        <div class="card__body">
            @if($verification)
                <dl class="summary-list">
                    <div class="summary-row"><dt>Type</dt><dd>{{ $verification['type_verification_reference'] }}</dd></div>
                    <div class="summary-row"><dt>Résultat</dt><dd>{{ $verification['resultat_reference'] }}{{ $verification['expiree'] ? ' (expirée)' : '' }}</dd></div>
                    <div class="summary-row"><dt>Vérifié par</dt><dd class="technical-reference">{{ $verification['verifie_par_reference'] }}</dd></div>
                    <div class="summary-row"><dt>Expire le</dt><dd>{{ $verification['expire_le'] ?? '—' }}</dd></div>
                </dl>
            @else
                <p>Aucune vérification enregistrée.</p>
            @endif
            @if($autorite)
            <form method="POST" action="{{ route('console.realms.verifications.enregistrer', $realm['reference']) }}" class="inline-form" style="margin-top:12px">
                @csrf
                <select class="select" name="type_verification_reference" required>
                    @foreach($typesVerification as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <select class="select" name="resultat_reference" required>
                    @foreach($resultatsVerification as $valeur)<option value="{{ $valeur }}">{{ $valeur }}</option>@endforeach
                </select>
                <input class="input" name="verifie_par_reference" placeholder="vérificateur" required>
                <input class="input" type="date" name="expire_le">
                <button class="button" type="submit">Enregistrer</button>
            </form>
            @endif
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Vérifier une portée</h2></div>
        <div class="card__body">
            <form method="POST" action="{{ route('console.realms.portee.verifier', $realm['reference']) }}" class="inline-form">
                @csrf
                <input class="input" name="organisation" placeholder="organisation (facultatif)">
                <input class="input" name="produit" placeholder="produit (facultatif)">
                <input class="input" name="realm_cible" placeholder="realm cible (facultatif)">
                <input class="input" name="finalite" placeholder="finalité (facultatif)">
                <button class="button" type="submit">Vérifier</button>
            </form>
            <p class="field-help">Cette vérification est explicable, jamais une autorisation en elle-même.</p>
        </div>
    </section>

    <section class="card">
        <div class="card__header"><h2 class="card__title">Historique du cycle</h2></div>
        <div class="card__body">
            @forelse($historique as $evenement)
                <div class="summary-row"><dt>{{ $evenement['date_effet'] }}</dt><dd>{{ $evenement['etat_reference'] }}{{ $evenement['motif_reference'] ? ' — '.$evenement['motif_reference'] : '' }}</dd></div>
            @empty
                <p>Aucun événement.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
