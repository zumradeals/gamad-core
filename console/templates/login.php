<div class="login-shell">
    <h1>GAMAD Core — Console d'Exploitation</h1>
    <p class="meta">
        Collez un token Bearer déjà émis pour ce Core (même mécanisme que pour <code>curl</code>).
        Ce n'est pas un compte utilisateur : la console n'émet ni ne gère aucun token (ADR-0011, ADR-0016).
    </p>
    <form method="post" action="/login">
        <div class="form-field">
            <label for="token">Token Bearer</label>
            <input type="password" id="token" name="token" autocomplete="off" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>
</div>
