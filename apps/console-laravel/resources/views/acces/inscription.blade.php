<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un compte — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/gamad-core.css') }}">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-form-panel">
        <form class="auth-form" action="{{ route('inscription.creer') }}" method="post">
            @csrf
            <h1>Créer un compte GAMAD</h1>
            @if ($errors->any())<p class="form-error" role="alert">{{ $errors->first() }}</p>@endif
            <div class="field">
                <label for="nom">Nom affiché</label>
                <input class="input" id="nom" name="nom" value="{{ old('nom') }}" autocomplete="name" required maxlength="256">
            </div>
            <div class="field">
                <label for="identifiant">Adresse e-mail</label>
                <input class="input" id="identifiant" name="identifiant" type="email" value="{{ old('identifiant') }}" autocomplete="email" required maxlength="256">
            </div>
            <div class="field">
                <label for="mot_de_passe">Mot de passe (8 caractères minimum)</label>
                <input class="input" id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="new-password" required minlength="8">
            </div>
            <div class="field">
                <label for="mot_de_passe_confirmation">Confirmer le mot de passe</label>
                <input class="input" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" type="password" autocomplete="new-password" required minlength="8">
            </div>
            <button class="button button--primary" type="submit">Créer mon compte</button>
            <a href="{{ route('acces.formulaire') }}">J’ai déjà un compte</a>
        </form>
    </section>
</main>
</body>
</html>
