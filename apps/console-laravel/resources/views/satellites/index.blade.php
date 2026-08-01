@extends('layouts.console')

@section('title', 'Satellites')

@section('content')
<header class="page-header">
    <div>
        <p class="eyebrow">Fédération de l’écosystème</p>
        <h1 class="page-title">Satellites</h1>
        <p class="page-subtitle">
            Les produits que le Core sait ouvrir à une personne. Le Core donne l’accès et la preuve ;
            le produit garde son compte, ses données et ses règles.
        </p>
    </div>
</header>

<div class="section-heading">
    <div>
        <h2>{{ count($satellites) }} produit{{ count($satellites) > 1 ? 's' : '' }} connu{{ count($satellites) > 1 ? 's' : '' }}</h2>
        <p>
            Un produit n’est ouvrable que si l’écosystème l’a reconnu. Les partenaires externes
            restent listés, et restent fermés.
        </p>
    </div>
</div>

<div class="identity-list">
    @foreach($satellites as $satellite)
        <a class="identity-row" href="{{ route('console.satellites.show', $satellite['reference']) }}">
            <span class="identity-main">
                <span class="identity-avatar" aria-hidden="true">{{ mb_substr($satellite['libelle'], 0, 2) }}</span>
                <span style="min-width:0">
                    <span class="identity-name">{{ $satellite['libelle'] }}</span>
                    <span class="technical-reference">{{ $satellite['reference'] }}</span>
                </span>
            </span>
            <span>
                <span class="identity-cell-label">Accès actifs</span>
                @if($satellite['lisible'])
                    {{ $satellite['acces_actifs'] }}
                @else
                    <span title="Lisible par ce satellite ou par l’autorité seulement">—</span>
                @endif
            </span>
            <span>
                <span class="status {{ $satellite['federable'] ? 'status--success' : 'status--warning' }}">
                    {{ $satellite['federable'] ? 'Ouvrable' : 'Non entériné' }}
                </span>
            </span>
            <span aria-hidden="true">→</span>
        </a>
    @endforeach
</div>

@unless($autorite)
    <div class="alert" style="margin-top:22px">
        <span class="alert__dot" aria-hidden="true"></span>
        <span>
            <span class="alert__title">Vue partielle</span>
            <span class="alert__detail">
                Vous êtes connecté en tant que <span class="technical-reference">{{ $acteur }}</span>.
                La liste des personnes ayant accès à un produit n’est lisible que par ce produit
                lui-même ou par l’autorité d’inscription.
            </span>
        </span>
    </div>
@endunless
@endsection
