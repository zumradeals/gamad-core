@extends('layouts.console')

@section('title', $preuve['reference'])

@section('content')
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    <a href="{{ route('console.preuves.index') }}">Preuves d’intégrité</a>
    <span aria-hidden="true">/</span>
    <span>{{ $preuve['reference'] }}</span>
</nav>

<header class="page-header">
    <div>
        <p class="eyebrow">{{ $preuve['type_preuve'] }}</p>
        <h1 class="page-title technical-reference">{{ $preuve['reference'] }}</h1>
        <p class="page-subtitle">{{ $preuve['sujet_type'] }} — {{ $preuve['sujet_reference'] }} · {{ $preuve['finalite_reference'] }}</p>
    </div>
    <span class="status status--success">{{ $preuve['etat'] ?? '—' }}</span>
</header>

<div class="detail-grid">
    <section class="card">
        <div class="card__header"><h2 class="card__title">Gouvernance</h2></div>
        <div class="card__body">
            <dl class="summary-list">
                <div class="summary-row"><dt>Producteur</dt><dd class="technical-reference">{{ $preuve['producteur_capacite_reference'] ?? $preuve['producteur_produit_reference'] ?? $preuve['producteur_identite_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Source</dt><dd class="technical-reference">{{ $preuve['source_reference'] }}</dd></div>
                <div class="summary-row"><dt>Realm</dt><dd class="technical-reference">{{ $preuve['realm_reference'] }}</dd></div>
                <div class="summary-row"><dt>Contrat</dt><dd class="technical-reference">{{ $preuve['contrat_reference'] ?? '—' }}</dd></div>
                <div class="summary-row"><dt>Classification</dt><dd>{{ $preuve['classification'] }}</dd></div>
                <div class="summary-row"><dt>Créée le</dt><dd>{{ $preuve['cree_le'] }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Empreintes ({{ count($empreintes) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Algorithme</th><th>Empreinte</th><th>Émise le</th></tr></thead>
                <tbody>
                    @forelse($empreintes as $empreinte)
                        <tr>
                            <td>{{ $empreinte['algorithme'] }}</td>
                            <td class="technical-reference">{{ $empreinte['empreinte_hex'] }}</td>
                            <td>{{ $empreinte['cree_le'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Aucune empreinte émise.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Signatures ({{ count($signatures) }})</h2></div>
        <div class="card__body">
            <p class="field-help">La clé privée n’a jamais quitté le fournisseur — seule la signature produite est conservée ici.</p>
            <table class="data-table">
                <thead><tr><th>Algorithme</th><th>Clé</th><th>Émise le</th></tr></thead>
                <tbody>
                    @forelse($signatures as $signature)
                        <tr>
                            <td>{{ $signature['algorithme_signature'] }}</td>
                            <td class="technical-reference">{{ $signature['cle_reference'] }}</td>
                            <td>{{ $signature['cree_le'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Preuve non signée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($manifeste)
        <section class="card span-12">
            <div class="card__header"><h2 class="card__title">Manifeste — {{ $manifeste['type_manifeste'] }}</h2></div>
            <div class="card__body">
                <dl class="summary-list">
                    <div class="summary-row"><dt>Racine</dt><dd class="technical-reference">{{ $manifeste['racine_empreinte'] }}</dd></div>
                    <div class="summary-row"><dt>Membres attendus</dt><dd>{{ $manifeste['membres_attendus'] }}</dd></div>
                </dl>
                <table class="data-table" style="margin-top:12px">
                    <thead><tr><th>Chemin logique</th><th>Taille</th><th>Empreinte</th></tr></thead>
                    <tbody>
                        @forelse($manifeste['membres'] as $membre)
                            <tr>
                                <td class="technical-reference">{{ $membre['chemin_logique'] }}</td>
                                <td>{{ $membre['taille_octets'] }} octet(s)</td>
                                <td class="technical-reference">{{ $membre['empreinte'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Aucun membre.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Vérifications ({{ count($verifications) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Résultat</th><th>Vérifiée le</th></tr></thead>
                <tbody>
                    @forelse($verifications as $verification)
                        <tr>
                            <td>{{ $verification['resultat'] }}</td>
                            <td>{{ $verification['cree_le'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">Aucune vérification enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <form method="POST" action="{{ route('console.preuves.verifier', $preuve['reference']) }}" style="margin-top:12px">
                @csrf
                <button class="button" type="submit">Vérifier maintenant</button>
            </form>
        </div>
    </section>

    <section class="card span-6">
        <div class="card__header"><h2 class="card__title">Liens ({{ count($liens) }})</h2></div>
        <div class="card__body">
            <table class="data-table">
                <thead><tr><th>Type</th><th>Cible</th></tr></thead>
                <tbody>
                    @forelse($liens as $lien)
                        <tr>
                            <td>{{ $lien['type_lien'] }}</td>
                            <td class="technical-reference">{{ $lien['preuve_cible_reference'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">Aucun lien déclaré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card span-12">
        <div class="card__header"><h2 class="card__title">Opérations sensibles</h2></div>
        <div class="card__body">
            <p class="field-help">Suspension, révocation et compromission exigent un motif et sont irréversibles pour la révocation et la compromission — aucun bouton d’effacement.</p>
            <div class="action-bar">
                <form method="POST" action="{{ route('console.preuves.suspendre', $preuve['reference']) }}" onsubmit="return confirm('Suspendre cette preuve ?');">
                    @csrf<input type="hidden" name="motif_code" value="SUSPENSION_CONSOLE">
                    <input type="hidden" name="motif_detail" value="suspension déclarée depuis la console">
                    <button class="button" type="submit">Suspendre</button>
                </form>
                <form method="POST" action="{{ route('console.preuves.revoquer', $preuve['reference']) }}" onsubmit="return confirm('Révoquer irréversiblement cette preuve ?');">
                    @csrf<input type="hidden" name="motif_code" value="REVOCATION_CONSOLE">
                    <input type="hidden" name="motif_detail" value="révocation déclarée depuis la console">
                    <button class="button button--danger" type="submit">Révoquer</button>
                </form>
                <form method="POST" action="{{ route('console.preuves.compromettre', $preuve['reference']) }}" onsubmit="return confirm('Déclarer cette preuve compromise ? Cette décision est terminale.');">
                    @csrf<input type="hidden" name="motif_code" value="COMPROMISSION_CONSOLE">
                    <input type="hidden" name="motif_detail" value="compromission déclarée depuis la console">
                    <button class="button button--danger" type="submit">Déclarer compromise</button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection
