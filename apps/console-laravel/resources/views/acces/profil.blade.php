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
            <button class="button button--primary" type="submit">Enregistrer</button>
            <a href="{{ route('console.acces.index') }}">Mes moyens d’accès</a>
        </form>
    </section>
</main>
</body>
</html>
