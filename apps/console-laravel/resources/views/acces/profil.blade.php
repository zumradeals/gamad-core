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
                @error('bio')
                    <p class="form-error" role="alert">{{ $message }}</p>
                @enderror
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
            @error('competences')<p class="form-error" role="alert">{{ $message }}</p>@enderror
            <button class="button button--primary" type="submit">Enregistrer</button>
            <a href="{{ route('console.acces.index') }}">Mes moyens d’accès</a>
            <a href="{{ route('fil') }}">Ouvrir le fil GAMAD</a>
        </form>
    </section>
</main>
</body>
</html>
