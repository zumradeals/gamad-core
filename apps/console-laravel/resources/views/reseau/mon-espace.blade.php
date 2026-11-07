<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon Espace — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/gamad-core.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mon-espace.css') }}">
    <script src="{{ asset('js/mon-espace.js') }}" defer></script>
</head>
<body class="mon-espace">
<a class="skip-link" href="#profil">Aller à mon espace</a>
<header class="espace-bandeau">
    <div class="espace-largeur espace-barre">
        <a class="espace-marque" href="{{ route('reseau.espace') }}"><img src="{{ asset('images/logo-gamad.jpg') }}" width="38" height="38" alt=""><strong>GAMAD</strong><span>Réseau</span></a>
        <span class="espace-repere">Les personnes. Leurs capacités. Leurs actions.</span>
        <span class="espace-badge">Mon Espace · Privé</span>
    </div>
</header>
<div id="espace-status" class="espace-largeur" role="status" aria-live="polite"></div>
<main id="espace-racine" class="espace-largeur espace-principal">
    <div class="espace-grille">
        <aside class="espace-gauche">
            <nav class="espace-carte espace-menu" aria-label="Navigation personnelle">
                <h1>Mon Espace</h1>
                <a href="#profil" aria-current="page">Vue d’ensemble</a>
                <button type="button" data-open="edition-profil" data-focus="titre">Mon profil</button>
                <a href="#capacites">Mes capacités</a>
                <a href="#realisations">Mes réalisations</a>
                <a href="#publications">Mes publications</a>
                <a href="#relations">Mes relations</a>
                <button type="button" data-open="edition-profil" data-focus="visibilite">Paramètres</button>
                <hr>
                <span class="espace-legende">Gestion personnelle</span>
                <a href="#experiences">Mes expériences</a>
                <a href="{{ route('console.acces.index') }}">Mes moyens d’accès</a>
                <a href="{{ route('blocages.index') }}">Personnes bloquées</a>
            </nav>
            <div class="espace-carte espace-conseil">
                <span class="espace-symbole" aria-hidden="true">◎</span>
                <h2>Votre parcours compte</h2>
                <p>Présentez ce que vous savez faire et ce que vous souhaitez apprendre.</p>
                <button class="button button--secondary" type="button" data-open="edition-profil" data-focus="bio">Enrichir mon profil</button>
            </div>
        </aside>
        <div class="espace-contenu">
            <section id="profil" class="espace-carte espace-profil" aria-labelledby="nom-profil" tabindex="-1">
                <div class="espace-couverture" aria-hidden="true"><span>GAMAD</span></div>
                <div class="espace-identite">
                    <span class="espace-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($compte['nom'], 0, 1)) }}</span>
                    <div class="espace-identite-texte">
                        <h2 id="nom-profil">{{ $compte['nom'] }}</h2>
                        @if (filled($fiche['titre'] ?? null))<p class="espace-sous-titre">{{ $fiche['titre'] }}</p>@endif
                        <p>{{ $territoires[$fiche['territoire'] ?? ''] ?? 'Localité à renseigner' }}</p>
                        <p>{{ $compte['bio'] ?: 'Faites découvrir votre parcours, à votre rythme.' }}</p>
                        <div class="espace-etiquettes">@foreach (array_slice($capacites, 0, 3) as $capacite)<span>{{ $capacite }}</span>@endforeach</div>
                    </div>
                    <button class="button button--primary" type="button" data-open="edition-profil" data-focus="titre">Modifier mon profil</button>
                </div>
            </section>
            <nav class="espace-indicateurs" aria-label="Activité personnelle">
                @foreach ([['capacites', count($capacites), 'Capacités apportées'], ['realisations', count($realisations), 'Réalisations'], ['publications', $activite['total'], 'Publications'], ['relations', count(array_filter($relations, fn ($r) => $r['etat'] === 'accepted')), 'Relations acceptées']] as [$ancre, $nombre, $libelle])
                    <a class="espace-carte" href="#{{ $ancre }}"><strong>{{ $nombre }}</strong><span>{{ $libelle }}</span><span aria-hidden="true">↗</span></a>
                @endforeach
            </nav>
            <div class="espace-travail">
                <div class="espace-colonne">
                    <section class="espace-carte" aria-labelledby="presentation-titre">
                        <header class="espace-section-titre"><h2 id="presentation-titre">Ma présentation</h2><button class="espace-lien" type="button" data-open="edition-profil" data-focus="introduction">Modifier</button></header>
                        <p class="espace-texte">{{ ($fiche['introduction'] ?? '') ?: ($compte['bio'] ?: 'Quelques mots sur votre parcours et vos envies pour commencer.') }}</p>
                    </section>
                    <section class="espace-carte" aria-labelledby="informations-titre">
                        <header class="espace-section-titre"><h2 id="informations-titre">Informations clés</h2><button class="espace-lien" type="button" data-open="edition-profil" data-focus="territoire">Modifier</button></header>
                        <dl><dt>Localité</dt><dd>{{ $territoires[$fiche['territoire'] ?? ''] ?? 'Non renseignée' }}</dd><dt>Domaines d’action</dt><dd>@forelse (($fiche['domaines'] ?? []) as $domaine)<span class="espace-domaine">{{ $domaines[$domaine] ?? $domaine }}</span>@empty Non renseignés @endforelse</dd></dl>
                    </section>
                    <section id="experiences" class="espace-carte" aria-labelledby="experiences-titre">
                        <header class="espace-section-titre"><h2 id="experiences-titre">Mes expériences</h2><button class="espace-lien" type="button" data-open="edition-experiences">Gérer</button></header>
                        @forelse (($fiche['experiences'] ?? []) as $experience)
                            <article class="espace-element"><h3>{{ $experience['titre'] }}</h3><p>{{ $typesExperience[$experience['type']] }} · {{ $experience['periode'] ?: 'Période non précisée' }}</p></article>
                        @empty <p>Un emploi, un apprentissage, une action bénévole : chaque expérience compte.</p> @endforelse
                    </section>
                </div>
                <div class="espace-colonne">
                    <section id="capacites" class="espace-carte" aria-labelledby="capacites-titre">
                        <header class="espace-section-titre"><h2 id="capacites-titre">Mes capacités</h2><button class="espace-lien" type="button" data-open="edition-profil" data-focus="apportees">Gérer</button></header>
                        @foreach (['competences_apportees' => 'Ce que je peux apporter', 'competences_recherchees' => 'Ce que je souhaite apprendre'] as $cle => $libelle)
                            <h3>{{ $libelle }}</h3><div class="espace-etiquettes">
                            @forelse (array_filter(explode("\n", $fiche[$cle] ?? '')) as $capacite)<span>{{ $capacite }}</span>@empty <p>Aucune capacité renseignée.</p>@endforelse
                            </div>
                        @endforeach
                        <button class="button button--secondary" type="button" data-open="edition-profil" data-focus="apportees">+ Ajouter une capacité</button>
                    </section>
                    <section id="realisations" class="espace-carte" aria-labelledby="realisations-titre">
                        <header class="espace-section-titre"><h2 id="realisations-titre">Mes réalisations ({{ count($realisations) }})</h2><button class="espace-lien" type="button" data-open="edition-publication" data-type="personal_work">Ajouter</button></header>
                        @forelse (array_slice($realisations, 0, 6) as $realisation)<article class="espace-element"><p class="espace-texte">{{ $realisation['contenu'] }}</p></article>
                        @empty <div class="espace-vide"><span aria-hidden="true">◇</span><h3>Votre savoir-faire en action</h3><p>Racontez un travail accompli ou un projet auquel vous avez contribué.</p></div>@endforelse
                        @if (count($realisations) > 6)<p>Les six réalisations les plus récentes sont affichées.</p>@endif
                    </section>
                    <section id="publications" class="espace-carte" aria-labelledby="publications-titre">
                        <header class="espace-section-titre"><h2 id="publications-titre">Mes publications</h2><button class="espace-lien" type="button" data-open="edition-publication" data-type="share">Créer</button></header>
                        @forelse ($activite['publications'] as $publication)<article class="espace-element"><span class="espace-legende">{{ $typesPublication[$publication['type']] ?? $publication['type'] }} · {{ $publication['visibilite'] === 'public' ? 'Public' : 'Membres vérifiés' }}</span><p class="espace-texte">{{ $publication['contenu'] }}</p></article>
                        @empty <p>Partagez un savoir, une expérience ou votre envie d’apprendre.</p>@endforelse
                        @if ($activite['total'] > 20)<p>Les vingt publications les plus récentes sont affichées.</p>@endif
                    </section>
                    <section id="relations" class="espace-carte" aria-labelledby="relations-titre"><header class="espace-section-titre"><h2 id="relations-titre">Mes relations</h2><a href="{{ route('relations.index') }}">Gérer</a></header>
                        @forelse ($relations as $relation)<p>{{ $relation['nom'] }} · {{ $relation['etat'] === 'accepted' ? 'Relation acceptée' : 'Demande en attente' }}</p>@empty <p>Vos relations acceptées et vos demandes en attente apparaîtront ici.</p>@endforelse
                    </section>
                </div>
            </div>
        </div>
        <aside class="espace-droite" aria-label="Assistance personnelle">
            <section class="espace-carte"><header class="espace-section-titre"><h2>Complétude du profil</h2><strong>{{ $completude }} %</strong></header><progress max="100" value="{{ $completude }}" aria-label="Complétude du profil">{{ $completude }} %</progress>
                <ul class="espace-criteres">@foreach ($criteres as $libelle => $rempli)<li><span class="{{ $rempli ? 'espace-valide' : '' }}" aria-hidden="true">{{ $rempli ? '✓' : '○' }}</span>{{ $libelle }}<span class="espace-sr"> : {{ $rempli ? 'renseigné' : 'à renseigner' }}</span></li>@endforeach</ul>
                <p class="espace-note">Chaque critère renseigné vaut 20 %. Cet indicateur n’est pas une certification.</p><button class="button button--secondary" type="button" data-open="edition-profil" data-focus="bio">Compléter mon profil</button>
            </section>
            <section class="espace-carte espace-rapides"><h2>Actions rapides</h2><button type="button" data-open="edition-publication" data-type="personal_work">Ajouter une réalisation <span aria-hidden="true">↗</span></button><button type="button" data-open="edition-publication" data-type="share">Créer une publication <span aria-hidden="true">↗</span></button><button type="button" data-open="edition-profil" data-focus="apportees">Modifier mes capacités <span aria-hidden="true">↗</span></button></section>
            <section id="visibilite" class="espace-carte"><h2>Visibilité de mon profil</h2><p class="espace-badge">{{ ['prive' => 'Privé', 'members' => 'Membres vérifiés', 'public' => 'Public'][$fiche['visibilite'] ?? 'prive'] }}</p><p>Cette préférence concerne votre fiche. Chaque publication conserve sa propre audience. Vos moyens de connexion restent privés.</p><button class="espace-lien" type="button" data-open="edition-profil" data-focus="visibilite">Modifier la visibilité</button></section>
            <section class="espace-carte espace-conseil"><h2>Conseils et ressources</h2><p>Une présentation précise et une réalisation concrète aident les autres à comprendre ce que vous pouvez apporter.</p><button class="espace-lien" type="button" data-open="edition-profil" data-focus="introduction">Enrichir ma présentation →</button></section>
        </aside>
    </div>
    @include('reseau.espace-editeurs')
    <noscript><p class="espace-carte">Pour modifier vos informations sans JavaScript, utilisez <a href="{{ route('compte.profil') }}">le formulaire de profil</a>.</p></noscript>
</main>
</body>
</html>
