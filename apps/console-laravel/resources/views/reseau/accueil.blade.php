<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0e2a3f">
    <title>GAMAD — Le réseau social d’action</title>
    <link rel="stylesheet" href="{{ asset('css/reseau-accueil.css') }}">
</head>
<body class="reseau-public gamad-home">
@include('reseau.navigation-publique')
<main>
    <section class="gamad-hero" aria-labelledby="accroche">
        <div class="conteneur gamad-hero-inner">
            <div class="gamad-hero-copy">
                <p class="etiquette">Le réseau social d’action</p>
                <h1 id="accroche">Des personnes, des compétences et des opportunités concrètes <span>pour agir ensemble.</span></h1>
                <p>GAMAD est le réseau d’action des personnes, des entreprises et des initiatives qui créent de la valeur en Afrique.</p>
                <div class="reseau-actions">
                    <a class="bouton accent" href="{{ route('inscription.formulaire') }}">Créer un compte gratuitement</a>
                    <a class="bouton" href="#comment">Découvrir GAMAD</a>
                </div>
                <p class="gamad-hero-values">✓ Échanger &nbsp; ✓ Collaborer &nbsp; ✓ Apprendre &nbsp; ✓ Réaliser</p>
            </div>
            <figure><img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="Des personnes collaborent autour d’un ordinateur" width="510" height="280" fetchpriority="high"></figure>
        </div>
    </section>
    <section class="gamad-pillars" aria-label="Découvrir les univers GAMAD">
        <div class="conteneur gamad-pillars-grid">
            <a href="{{ route('personnes.index') }}"><span class="gamad-pillar-icon">♙</span><strong>Personnes</strong><small>Valorisez votre savoir-faire et vos expériences.</small></a>
            <div><span class="gamad-pillar-icon">▦</span><strong>Entreprises</strong><small>Découvrez des talents, partenaires et solutions.</small></div>
            <div><span class="gamad-pillar-icon">◎</span><strong>Opportunités</strong><small>Des projets, missions et collaborations.</small></div>
            <div><span class="gamad-pillar-icon">✧</span><strong>ZUMRA</strong><small>Des initiatives à impact pour nos communautés.</small></div>
        </div>
    </section>
    <section class="conteneur gamad-how" id="comment" aria-labelledby="comment-titre">
        <div class="gamad-how-visual"><img src="{{ asset('images/gamad-accueil-hero.webp') }}" alt="GAMAD relie les personnes et les initiatives" loading="lazy"></div>
        <div>
            <h2 id="comment-titre">Comment ça marche ?</h2>
            <p>Un réseau simple, utile et ouvert à tous.</p>
            <ol class="gamad-steps">
                <li><span>1</span><div><strong>Créez votre compte</strong><p>Rejoignez la communauté GAMAD en quelques minutes.</p></div></li>
                <li><span>2</span><div><strong>Présentez vos compétences</strong><p>Dites ce que vous pouvez apporter et ce que vous souhaitez apprendre.</p></div></li>
                <li><span>3</span><div><strong>Échangez et agissez</strong><p>Entrez en relation, collaborez et donnez vie à vos projets.</p></div></li>
            </ol>
        </div>
    </section>
    @php
        $stats = collect(config('gamad_public.statistics', []))->filter(fn ($stat) => !empty($stat['visible']) && isset($stat['value']) && is_numeric($stat['value']));
    @endphp
    @if ($stats->isNotEmpty())
        <section class="conteneur gamad-statistics" aria-label="GAMAD en chiffres">
            @foreach ($stats as $stat)
                <div><strong>{{ number_format((float) $stat['value'], 0, ',', ' ') }}</strong><span>{{ $stat['label'] }}</span></div>
            @endforeach
        </section>
    @endif
    <section class="conteneur gamad-join">
        <div><h2>Rejoignez une communauté qui construit <span>des solutions africaines.</span></h2>
            <p>Partagez vos idées, vos compétences et vos initiatives avec des personnes qui avancent comme vous.</p></div>
        <div class="gamad-join-actions"><a class="bouton accent" href="{{ route('inscription.formulaire') }}">Créer mon compte maintenant</a>
            <a href="#comment">En savoir plus sur GAMAD</a></div>
    </section>
</main>
@include('reseau.pied-public')
</body>
</html>
