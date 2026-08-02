<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f8d40a">
    <title>@yield('title', 'Console') — GAMAD Core</title>
    <link rel="stylesheet" href="{{ asset('css/gamad-core.css') }}">
</head>
<body>
@php
    $acteurConnecte = (string) request()->attributes->get('gamad_entite', 'Session GAMAD');
@endphp
<a class="skip-link" href="#contenu">Aller au contenu</a>

<div class="app-shell">
    <aside class="sidebar" id="navigation-principale" aria-label="Navigation principale">
        <a class="brand" href="{{ route('console.accueil') }}">
            <img class="brand__mark"
                 src="{{ asset('images/logo-gamad.jpg') }}"
                 alt="Logo GAMAD"
                 width="56"
                 height="56">
            <span>
                <span class="brand__name">GAMAD Core</span>
                <span class="brand__tagline">Formation · Travail · Adoration</span>
            </span>
        </a>
        <div class="brand-rule" aria-hidden="true"></div>

        <nav class="nav">
            <span class="nav__label">Aujourd’hui</span>
            <a class="nav__link"
               href="{{ route('console.accueil') }}"
               @if(request()->routeIs('console.accueil')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/><path d="M9.5 19.5v-6h5v6"/></svg>
                </span>
                Vue d’ensemble
            </a>
            <a class="nav__link"
               href="{{ route('console.identites.index') }}"
               @if(request()->routeIs('console.identites.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.4-4 2.2-6 5.5-6s5.1 2 5.5 6"/><path d="M16 8.5h4.5M18.25 6.25v4.5"/></svg>
                </span>
                Identités
            </a>

            <span class="nav__label">Gouverner</span>
            <span class="nav__link nav__link--muted" aria-disabled="true">
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M5 4.5h14v15H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>
                </span>
                Décisions et règles
                <span class="nav__soon">Bientôt</span>
            </span>
            <span class="nav__link nav__link--muted" aria-disabled="true">
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="7" r="3"/><path d="M5 20c.5-4.5 2.8-7 7-7s6.5 2.5 7 7"/><path d="M18.5 4.5 20 6l-3 3"/></svg>
                </span>
                Autorités et accès
                <span class="nav__soon">Bientôt</span>
            </span>
            <a class="nav__link"
               href="{{ route('console.satellites.index') }}"
               @if(request()->routeIs('console.satellites.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/></svg>
                </span>
                Satellites
            </a>
            <a class="nav__link"
               href="{{ route('console.produits.index') }}"
               @if(request()->routeIs('console.produits.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="4" y="4" width="7" height="7"/><rect x="13" y="4" width="7" height="7"/><rect x="4" y="13" width="7" height="7"/><rect x="13" y="13" width="7" height="7"/></svg>
                </span>
                Produits
            </a>
            <a class="nav__link"
               href="{{ route('console.sources.index') }}"
               @if(request()->routeIs('console.sources.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 4v8l6 3"/></svg>
                </span>
                Sources
            </a>
            <a class="nav__link"
               href="{{ route('console.politiques.index') }}"
               @if(request()->routeIs('console.politiques.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 4h9v16H9"/><path d="M9 4H5v16h4"/><path d="M13 9h3M13 13h3"/></svg>
                </span>
                Politiques
            </a>
            <a class="nav__link"
               href="{{ route('console.contrats.index') }}"
               @if(request()->routeIs('console.contrats.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 4h8l4 4v12H4V4z"/><path d="M8 4v4H4"/><path d="M9 13h6M9 17h6"/></svg>
                </span>
                Contrats
            </a>

            <span class="nav__label">Protéger</span>
            <a class="nav__link"
               href="{{ route('console.continuite.index') }}"
               @if(request()->routeIs('console.continuite.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 7c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3z"/><path d="M4 7v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>
                </span>
                Continuité
            </a>
            <a class="nav__link"
               href="{{ route('console.acces.index') }}"
               @if(request()->routeIs('console.acces.*')) aria-current="page" @endif>
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.5c0 4.6 2.8 7.8 7.5 9.5 4.7-1.7 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2 2 4-5"/></svg>
                </span>
                Mon accès
            </a>
            <span class="nav__link nav__link--muted" aria-disabled="true">
                <span class="nav__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 18h16M6 18V9h4v9M14 18V5h4v13"/></svg>
                </span>
                Audit et preuves
                <span class="nav__soon">Bientôt</span>
            </span>
        </nav>

        <div class="sidebar-user">
            <div class="sidebar-user__identity">
                <span class="avatar" aria-hidden="true">{{ mb_substr($acteurConnecte, 0, 1) }}</span>
                <span style="min-width:0">
                    <span class="sidebar-user__label">Session active</span>
                    <span class="sidebar-user__reference">{{ $acteurConnecte }}</span>
                </span>
            </div>
            <form method="POST" action="{{ route('acces.deconnecter') }}">
                @csrf
                <button class="logout-button" type="submit">Fermer la session</button>
            </form>
        </div>
    </aside>

    <div class="scrim" data-menu-close aria-hidden="true"></div>

    <div class="workspace">
        <header class="mobile-header">
            <a class="mobile-brand" href="{{ route('console.accueil') }}">
                <img src="{{ asset('images/logo-gamad.jpg') }}" alt="" width="42" height="42">
                GAMAD Core
            </a>
            <button class="icon-button"
                    type="button"
                    data-menu-toggle
                    aria-expanded="false"
                    aria-controls="navigation-principale"
                    aria-label="Ouvrir le menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </header>

        <main class="content" id="contenu">
            @if(session('succes'))
                <div class="flash" data-flash role="status">
                    <span aria-hidden="true">✓</span>
                    <span>{{ session('succes') }}</span>
                    <button class="flash__close" type="button" data-flash-close aria-label="Fermer">×</button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('js/gamad-core.js') }}" defer></script>
@stack('scripts')
</body>
</html>
