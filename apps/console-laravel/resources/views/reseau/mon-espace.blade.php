<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon Espace — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/mon-espace.css') }}">
</head>
<body>
    <header class="espace-bandeau">
        <div class="espace-largeur espace-barre">
            <a class="espace-marque" href="{{ route('reseau.espace') }}">
                <img src="{{ asset('images/logo-gamad.jpg') }}" width="38" height="38" alt="">
                <strong>GAMAD</strong> <span>Réseau</span>
            </a>
            <ul class="espace-navigation" aria-label="Rubriques du Réseau">
                @foreach (['Accueil', 'Personnes', 'Entreprises', 'Opportunités', 'ZUMRA'] as $rubrique)
                    <li>{{ $rubrique }}</li>
                @endforeach
            </ul>
            <a class="espace-badge" href="{{ route('reseau.espace') }}" aria-current="page">Mon Espace</a>
        </div>
    </header>

    <main class="espace-largeur espace-principal">
        <header class="espace-titre">
            <h1>Mon Espace</h1>
            <span class="espace-badge">Espace privé</span>
        </header>

        <div class="espace-grille">
            <nav class="espace-carte espace-menu" aria-label="Menu personnel">
                <a href="{{ route('reseau.espace') }}" aria-current="page">Vue d’ensemble</a>
                <a href="#profil">Mon profil</a>
                <a href="#capacites">Mes capacités</a>
                <a href="#realisations">Mes réalisations</a>
                <a href="#visibilite">Paramètres</a>
            </nav>

            <div class="espace-contenu">
                <section id="profil" class="espace-carte espace-profil" aria-labelledby="nom-profil">
                    <span class="espace-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($compte['nom'], 0, 1)) }}</span>
                    <div>
                        <h2 id="nom-profil">{{ $compte['nom'] }}</h2>
                        @if (isset($territoires[$fiche['territoire'] ?? '']))
                            <p>{{ $territoires[$fiche['territoire']] }}</p>
                        @endif
                        <p>{{ $fiche['introduction'] ?? $compte['bio'] ?? 'Présentez votre parcours en quelques mots.' }}</p>
                    </div>
                    <div class="espace-actions">
                        <a class="espace-bouton" href="{{ route('compte.profil') }}">Modifier mon profil</a>
                        @if (isset($fiche['reference_publique']) && ($fiche['visibilite'] ?? 'prive') !== 'prive')
                            <a href="{{ route('personnes.show', $fiche['reference_publique']) }}">Voir ma fiche</a>
                        @endif
                    </div>
                </section>

                <section id="capacites" class="espace-carte" aria-labelledby="titre-capacites">
                    <header class="espace-section-titre">
                        <div>
                            <h2 id="titre-capacites">Mes capacités</h2>
                            <p>Mettez en avant ce que vous pouvez apporter et ce que vous souhaitez apprendre.</p>
                        </div>
                        <a class="espace-bouton" href="{{ route('compte.profil') }}#apportees">Ajouter une capacité</a>
                    </header>
                    <div class="espace-capacites">
                        @foreach (['competences_apportees' => 'Ce que je peux apporter', 'competences_recherchees' => 'Ce que je souhaite apprendre'] as $cle => $titre)
                            <div class="espace-panneau">
                                <h3>{{ $titre }}</h3>
                                @if (filled($fiche[$cle] ?? null))
                                    <ul>
                                        @foreach (explode("\n", $fiche[$cle]) as $capacite)
                                            <li>{{ $capacite }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>Aucune capacité renseignée.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                <div class="espace-bas">
                    <section id="realisations" class="espace-carte" aria-labelledby="titre-realisations">
                        <h2 id="titre-realisations">Mes réalisations</h2>
                        @forelse ($realisations as $realisation)
                            <p class="espace-panneau">{{ \Illuminate\Support\Str::limit($realisation['contenu'], 180) }}</p>
                        @empty
                            <p>Vos réalisations apparaîtront ici.</p>
                        @endforelse
                    </section>

                    <section id="visibilite" class="espace-carte" aria-labelledby="titre-visibilite">
                        <h2 id="titre-visibilite">Visibilité du profil</h2>
                        <p>Choisissez qui peut voir votre profil.</p>
                        <a class="espace-panneau espace-visibilite" href="{{ route('compte.profil') }}#visibilite">
                            {{ ['prive' => 'Privé', 'members' => 'Membres vérifiés uniquement', 'public' => 'Public'][$fiche['visibilite'] ?? 'prive'] }}
                        </a>
                    </section>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
