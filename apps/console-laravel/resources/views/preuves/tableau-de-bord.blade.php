@extends('layouts.console')

@section('title', 'Preuves d’intégrité')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Preuves d’intégrité</p>
        <h1 class="page-title">Preuves d’intégrité</h1>
        <p class="page-subtitle">
            Registre de gouvernance (CAP-CORE-015) : empreintes, signatures, manifestes, checkpoints et
            attestations émis sur le Core. Cet écran n’affiche jamais une clé privée.
        </p>
    </div>
</header>

@unless($autorise)
    <div class="form-error" role="alert">
        <span aria-hidden="true">!</span>
        <span>Cet écran est fermé pour cette session. {{ $motif }}</span>
    </div>
@else
    <section class="hero-status" aria-labelledby="etat-preuves">
        <div>
            <p class="eyebrow">État du registre</p>
            <h2 class="hero-status__title" id="etat-preuves">
                {{ ($diagnostic['sodium_disponible'] ?? false) ? 'Le fournisseur cryptographique est disponible.' : 'Aucune signature Ed25519 ne peut être émise ni vérifiée.' }}
            </h2>
            <p class="hero-status__copy">
                {{ $diagnostic['preuves'] ?? 0 }} preuve(s), {{ $diagnostic['actives'] ?? 0 }} active(s).
            </p>
        </div>
        <div class="health-orbit">
            <div class="health-orbit__ring {{ ($diagnostic['sodium_disponible'] ?? false) ? '' : 'health-orbit__ring--danger' }}">
                <span>
                    <span class="health-orbit__state">{{ ($diagnostic['sodium_disponible'] ?? false) ? 'Disponible' : 'Indisponible' }}</span>
                    <span class="health-orbit__label">{{ $diagnostic['compromises'] ?? 0 }} preuve(s) compromise(s)</span>
                </span>
            </div>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="card span-6">
            <div class="card__header"><h2 class="card__title">Cycle</h2></div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row"><dt>Préparées non émises</dt><dd>{{ $diagnostic['preparees_bloquees'] ?? 0 }}</dd></div>
                    <div class="summary-row"><dt>Actives</dt><dd>{{ $diagnostic['actives'] ?? 0 }}</dd></div>
                    <div class="summary-row"><dt>Compromises</dt><dd>{{ $diagnostic['compromises'] ?? 0 }}</dd></div>
                </dl>
                <p class="field-help" style="margin-top:12px">
                    Émission : <span class="technical-reference">core:preuves:empreinter</span>,
                    <span class="technical-reference">core:preuves:checkpoint-journal</span>,
                    <span class="technical-reference">core:preuves:manifeste-sauvegarde</span>,
                    <span class="technical-reference">core:preuves:attester-restauration</span>.
                </p>
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:20px">
        <div class="card__header"><h2 class="card__title">Preuves ({{ count($preuves) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead>
                    <tr><th>Référence</th><th>Type</th><th>Sujet</th><th>Realm</th><th>Classification</th><th>Créée le</th></tr>
                </thead>
                <tbody>
                    @forelse($preuves as $preuve)
                        <tr>
                            <td><a href="{{ route('console.preuves.show', $preuve['reference']) }}" class="technical-reference">{{ $preuve['reference'] }}</a></td>
                            <td>{{ $preuve['type_preuve'] }}</td>
                            <td>{{ $preuve['sujet_type'] }} — <span class="technical-reference">{{ $preuve['sujet_reference'] }}</span></td>
                            <td class="technical-reference">{{ $preuve['realm_reference'] }}</td>
                            <td>{{ $preuve['classification'] }}</td>
                            <td>{{ $preuve['cree_le'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucune preuve émise.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endunless
@endsection
