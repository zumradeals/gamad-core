@extends('layouts.console')

@section('title', $politique['libelle'])

@section('content')
@php
    $libelleMinuscule = mb_strtolower((string) $politique['libelle']);
    $resumeHumain = $politique['description'] ?: match (true) {
        str_contains($libelleMinuscule, 'accès') || str_contains($libelleMinuscule, 'connexion')
            => 'Cette règle définit les moyens autorisés pour se connecter à GAMAD et protège les comptes contre les accès non autorisés.',
        str_contains($libelleMinuscule, 'identité')
            => 'Cette règle encadre la création, la vérification et l’utilisation des identités reconnues par GAMAD.',
        str_contains($libelleMinuscule, 'produit')
            => 'Cette règle encadre la manière dont les produits et services GAMAD peuvent être inscrits, activés ou suspendus.',
        default
            => 'Cette règle définit une partie du fonctionnement de GAMAD. Elle est appliquée automatiquement lorsque les opérations concernées sont demandées.',
    };
    $formatDate = static function (?string $date): string {
        if ($date === null || trim($date) === '') {
            return 'Non renseignée';
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)
                ->locale('fr')
                ->isoFormat('D MMMM YYYY [à] HH[h]mm');
        } catch (\Throwable) {
            return $date;
        }
    };
    $libellesEtat = [
        'BROUILLON' => 'Modification en préparation',
        'EN_VALIDATION' => 'En cours de validation',
        'ACTIVE' => 'En vigueur',
        'SUSPENDUE' => 'Suspendue',
        'DEPRECIEE' => 'En cours de remplacement',
        'RETIREE' => 'Retirée',
    ];
@endphp

<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.politiques.index') }}">Règles et autorisations</a>
    <span aria-hidden="true">/</span>
    <span>{{ $politique['libelle'] }}</span>
</nav>

<section class="detail-hero">
    <span class="identity-avatar" aria-hidden="true">{{ mb_substr($politique['libelle'], 0, 2) }}</span>
    <div>
        <p class="eyebrow">Règle de fonctionnement</p>
        <h1 class="detail-hero__title">{{ $politique['libelle'] }}</h1>
        <p class="detail-hero__summary">{{ $resumeHumain }}</p>
        <div class="detail-hero__meta">
            @if($politique['version_active'])
                <span class="status status--success" data-technical-state="Active — {{ $politique['version_active'] }}">En vigueur — version {{ $politique['version_active'] }}</span>
            @else
                <span class="status status--warning" data-technical-state="Aucune version active">Aucune version en vigueur</span>
            @endif
        </div>
    </div>
</section>

@if($errors->any())
    <div class="form-error" role="alert" style="margin:18px 0">{{ $errors->first() }}</div>
@endif
@if(session('succes'))
    <div class="alert alert--success" style="margin:18px 0">
        <span class="alert__dot" aria-hidden="true"></span>
        <span>
            <span class="alert__title">{{ session('succes') }}</span>
            @if(session('preuve'))
                <span class="alert__detail">Une preuve technique a été conservée avec l’opération.</span>
            @endif
        </span>
    </div>
@endif

<section class="human-summary" style="margin-top:22px">
    <div>
        <h2 class="human-summary__title">À retenir</h2>
        <p class="human-summary__copy">
            @if($politique['version_active'])
                Cette règle est actuellement appliquée par GAMAD. Aucune action n’est nécessaire tant qu’aucune alerte ou modification n’est demandée.
            @else
                Cette règle n’a pas de version active. Les opérations qui en dépendent peuvent être refusées jusqu’à son activation.
            @endif
        </p>
    </div>
    <dl class="human-facts">
        <div class="human-fact">
            <dt>Situation actuelle</dt>
            <dd class="human-state {{ $politique['version_active'] ? '' : 'human-state--warning' }}">
                {{ $politique['version_active'] ? 'En vigueur' : 'À examiner' }}
            </dd>
        </div>
        <div class="human-fact">
            <dt>Dernière modification</dt>
            <dd>{{ $formatDate($politique['modifie_le'] ?? null) }}</dd>
        </div>
        <div class="human-fact">
            <dt>Action attendue</dt>
            <dd>{{ $politique['version_active'] ? 'Aucune pour le moment' : 'Préparer ou activer une version' }}</dd>
        </div>
    </dl>
</section>

@if($autorite)
<section class="card" style="margin-top:22px" data-technical-action="Créer en BROUILLON">
    <div class="card__header">
        <div>
            <h2 class="card__title">Préparer une modification</h2>
            <p class="card__description">Créer une nouvelle version sans modifier immédiatement la règle actuellement en vigueur.</p>
        </div>
    </div>
    <div class="card__body">
        <div class="action-explainer">
            <strong>Que se passe-t-il ensuite ?</strong>
            <p>La nouvelle version restera en préparation. Elle devra être complétée, vérifiée et validée avant de pouvoir remplacer la version active.</p>
        </div>
        <form method="POST" action="{{ route('console.politiques.versions.creer', $politique['reference']) }}" class="form-layout" style="max-width:620px">
            @csrf
            <div class="field">
                <label for="version">Numéro de la nouvelle version</label>
                <input class="input" id="version" name="version" maxlength="32" required autocomplete="off" placeholder="1.1.0">
                <p class="field-help">Format technique : trois nombres séparés par des points, par exemple 1.1.0.</p>
            </div>
            <div class="field">
                <label for="description">Pourquoi cette modification est-elle nécessaire ?</label>
                <textarea class="input" id="description" name="description" maxlength="2000" rows="3" placeholder="Expliquez le changement attendu en langage simple."></textarea>
            </div>
            <button class="button button--primary" type="submit" style="margin-top:8px">Commencer la modification</button>
        </form>
    </div>
</section>
@endif

<section class="card" style="margin-top:22px">
    <div class="card__header">
        <div>
            <h2 class="card__title">Versions de cette règle</h2>
            <p class="card__description">La version en vigueur et les modifications encore en préparation.</p>
        </div>
    </div>
    <div class="card__body">
        <div class="identity-list">
            @forelse($versions as $v)
                <a class="identity-row" href="{{ route('console.politiques.version', [$politique['reference'], $v['version']]) }}">
                    <span class="identity-main">
                        <span style="min-width:0">
                            <span class="identity-name">Version {{ $v['version'] }}</span>
                            <span class="card__description">Créée le {{ $formatDate($v['cree_le'] ?? null) }}</span>
                        </span>
                    </span>
                    <span>
                        <span class="status {{ $v['etat'] === 'ACTIVE' ? 'status--success' : ($v['etat'] === 'RETIREE' ? 'status--danger' : 'status--warning') }}">
                            {{ $libellesEtat[$v['etat']] ?? $v['etat'] }}
                        </span>
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            @empty
                <div class="empty-state">
                    <h2>Aucune version n’est encore enregistrée</h2>
                    <p>Une première version doit être préparée puis validée avant que cette règle puisse être appliquée.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

<section class="card" style="margin-top:22px">
    <div class="card__header">
        <div>
            <h2 class="card__title">Historique compréhensible</h2>
            <p class="card__description">Les grandes étapes traversées par cette règle, de la préparation à son état actuel.</p>
        </div>
    </div>
    <div class="card__body">
        <div class="identity-list">
            @forelse($historique as $evenement)
                <div class="identity-row" style="cursor:default">
                    <span class="identity-main">
                        <span style="min-width:0">
                            <span class="identity-name">{{ $libellesEtat[$evenement['etat']] ?? $evenement['etat'] }}</span>
                            <span class="card__description">{{ $formatDate($evenement['date_effet'] ?? null) }}</span>
                        </span>
                    </span>
                    <span>
                        <span class="identity-cell-label">Motif</span>
                        {{ $evenement['motif'] ?? 'Aucun motif complémentaire' }}
                    </span>
                    <span aria-hidden="true"></span>
                </div>
            @empty
                <p class="card__description">Aucun changement d’état n’est encore enregistré.</p>
            @endforelse
        </div>
    </div>
</section>

<section style="margin-top:22px">
    <details class="technical-panel">
        <summary>Afficher les détails techniques</summary>
        <div class="technical-panel__body">
            <dl class="detail-list">
                <div class="detail-row"><dt>Référence de la règle</dt><dd class="technical-reference">{{ $politique['reference'] }}</dd></div>
                <div class="detail-row"><dt>Responsable technique</dt><dd class="technical-reference">{{ $politique['proprietaire_reference'] }}</dd></div>
                <div class="detail-row"><dt>Source technique</dt><dd class="technical-reference">{{ $politique['source_reference'] }}</dd></div>
                <div class="detail-row"><dt>Domaine</dt><dd>{{ $politique['domaine'] ?? 'Non renseigné' }}</dd></div>
                @if(session('preuve'))
                    <div class="detail-row"><dt>Dernière preuve</dt><dd class="technical-reference">{{ session('preuve')['reference'] ?? 'Non renseignée' }}</dd></div>
                @endif
            </dl>
        </div>
    </details>
</section>

@if($autorite)
<section style="margin-top:16px">
    <details class="technical-panel danger-panel">
        <summary>Examiner les conséquences d’un retrait</summary>
        <div class="technical-panel__body">
            <div class="action-explainer">
                <strong>Action sensible</strong>
                <p>Retirer cette règle arrête immédiatement l’utilisation de sa version active. Les opérations qui en dépendent pourront être refusées. L’historique ne sera pas supprimé.</p>
            </div>
            <ul class="impact-list">
                <li>La règle ne sera plus considérée comme active.</li>
                <li>Les services dépendants devront disposer d’une règle de remplacement.</li>
                <li>L’opération sera journalisée et restera vérifiable.</li>
            </ul>
            <form method="POST" action="{{ route('console.politiques.retirer', $politique['reference']) }}"
                  onsubmit="return confirm('Confirmez-vous le retrait de cette règle ? Sa version active cessera immédiatement de s’appliquer.');">
                @csrf
                <button class="button button--secondary" type="submit" @disabled(!$politique['version_active'])>Retirer cette règle</button>
            </form>
        </div>
    </details>
</section>
@endif
@endsection
