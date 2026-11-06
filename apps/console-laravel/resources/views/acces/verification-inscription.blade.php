<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérifier mon adresse — GAMAD</title>
    <link rel="stylesheet" href="{{ asset('css/gamad-core.css') }}">
</head>
<body class="auth-page">
<main class="auth-shell auth-shell--verification">
    <section class="auth-form-panel">
        <div class="auth-form">
            <h1>Vérifier mon adresse e-mail</h1>
            @if (session('inscription_statut'))<p role="status">{{ session('inscription_statut') }}</p>@endif
            @if ($errors->any())<p class="form-error" role="alert">{{ $errors->first() }}</p>@endif
            <form action="{{ route('inscription.verifier') }}" method="post">
                @csrf
                <div class="field">
                    <label for="code">Code à six chiffres</label>
                    <input class="input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" required maxlength="6">
                </div>
                <button class="button button--primary" type="submit">Vérifier mon adresse</button>
            </form>
            <form class="verification-resend" action="{{ route('inscription.renvoyer') }}" method="post">
                @csrf
                <div class="field">
                    <label for="destination">Adresse e-mail utilisée à l’inscription</label>
                    <input class="input" id="destination" name="destination" type="email" autocomplete="email" required maxlength="256">
                </div>
                <button class="button button--secondary" type="submit">Renvoyer le code</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
