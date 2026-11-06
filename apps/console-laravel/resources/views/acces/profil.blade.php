<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon profil — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/gamad-core.css') }}">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-form-panel">
        <form class="auth-form" method="post" action="{{ route('compte.profil.modifier') }}">
            @csrf
            @method('PATCH')
            <h1>Mon profil GAMAD</h1>
            <p>{{ $compte['nom'] }}</p>
            @if (session('profil_enregistre'))
                <p role="status">Présentation enregistrée.</p>
            @endif
            <div class="field">
                <label for="bio">Ma présentation</label>
                <textarea class="input" id="bio" name="bio" maxlength="{{ $bioMax }}" rows="5">{{ old('bio', $compte['bio']) }}</textarea>
            </div>
            <div class="field">
                <label for="visibilite">Visibilité du profil</label>
                <select class="input" id="visibilite" name="visibilite">
                    @foreach (['prive' => 'Privé', 'members' => 'Membres avec e-mail vérifié', 'public' => 'Public'] as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(old('visibilite', $fiche['visibilite'] ?? 'prive') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="apportees">Ce que je peux apporter (une compétence par ligne)</label>
                <textarea class="input" id="apportees" name="apportees" rows="4">{{ old('apportees', $fiche['competences_apportees'] ?? '') }}</textarea>
            </div>
            <div class="field">
                <label for="recherchees">Ce que je veux apprendre (une compétence par ligne)</label>
                <textarea class="input" id="recherchees" name="recherchees" rows="4">{{ old('recherchees', $fiche['competences_recherchees'] ?? '') }}</textarea>
            </div>
            <div class="field">
                <label for="titre">Titre de profil</label>
                <input class="input" id="titre" name="titre" maxlength="100" value="{{ old('titre', $fiche['titre'] ?? '') }}">
            </div>
            <div class="field">
                <label for="introduction">Introduction</label>
                <textarea class="input" id="introduction" name="introduction" maxlength="1200" rows="4">{{ old('introduction', $fiche['introduction'] ?? '') }}</textarea>
            </div>
            <div class="field">
                <label for="territoire">Localité</label>
                <select class="input" id="territoire" name="territoire">
                    <option value="">Non renseignée</option>
                    @foreach ($territoires as $code => $libelle)
                        <option value="{{ $code }}" @selected(old('territoire', $fiche['territoire'] ?? null) === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <fieldset>
                <legend>Domaines d’action (trois maximum)</legend>
                <input type="hidden" name="domaines_present" value="1">
                @foreach ($domaines as $code => $libelle)
                    <label><input type="checkbox" name="domaines[]" value="{{ $code }}" @checked(in_array($code, (array) old('domaines', old('domaines_present') ? [] : ($fiche['domaines'] ?? [])), true))> {{ $libelle }}</label>
                @endforeach
            </fieldset>
            @foreach ($errors->all() as $erreur)
                <p class="form-error" role="alert">{{ $erreur }}</p>
            @endforeach
            <button class="button button--primary" type="submit">Enregistrer</button>
            <a href="{{ route('console.acces.index') }}">Mes moyens d’accès</a>
            <a href="{{ route('fil') }}">Ouvrir le fil GAMAD</a>
            <a href="{{ route('blocages.index') }}">Personnes bloquées</a>
            <a href="{{ route('relations.index') }}">Mes relations</a>
        </form>
        @if ($fiche !== [])
            <section aria-labelledby="experiences">
                <h2 id="experiences">Mes expériences déclarées</h2>
                @foreach ($fiche['experiences'] as $experience)
                    <details @if (old('experience_formulaire') === $experience['reference']) open @endif>
                        <summary>{{ $experience['titre'] }}</summary>
                        @include('acces.experience-profil', ['experience' => $experience])
                        <form method="post" action="{{ route('compte.profil.experiences.retirer', $experience['reference']) }}">
                            @csrf
                            @method('DELETE')
                            <button class="button" type="submit">Retirer l’expérience</button>
                        </form>
                    </details>
                @endforeach
                @if (count($fiche['experiences']) < 10)
                    <details @if (old('experience_formulaire') === 'nouvelle') open @endif>
                        <summary>Ajouter une expérience</summary>
                        @include('acces.experience-profil', ['experience' => null])
                    </details>
                @endif
            </section>
        @endif
    </section>
</main>
</body>
</html>
