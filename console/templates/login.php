<div class="login-shell login-admin">
    <h1>Connexion admin</h1>
    <p class="meta">
        Collez un token Bearer déjà émis pour ce Core (même mécanisme que pour <code>curl</code>).
        Ce n'est pas un compte utilisateur : la console n'émet ni ne gère aucun token (ADR-0011, ADR-0016).
        Ceci ouvre uniquement le contexte admin/runtime — jamais une session Persons (ADR-0019).
    </p>
    <form method="post" action="/login">
        <div class="form-field">
            <label for="token">Token Bearer</label>
            <input type="password" id="token" name="token" autocomplete="off" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>
    <p class="meta"><a href="/login-operateur">Connexion opérateur</a></p>
</div>
