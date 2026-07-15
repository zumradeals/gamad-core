<div class="login-shell login-operateur">
    <h1>Connexion opérateur</h1>
    <p class="meta">
        Identifiant de la personne (émis par l'Identity Registry, format <code>GAM-GAT-PER-xxxxxx</code>)
        et mot de passe du compte. Ceci ouvre une session Persons (ADR-0018), distincte du token admin
        (ADR-0011) — se connecter ici ne connecte jamais automatiquement le contexte admin (ADR-0019).
    </p>
    <form method="post" action="/login-operateur">
        <div class="form-field">
            <label for="person_id">Identifiant personne</label>
            <input type="text" id="person_id" name="person_id" autocomplete="username" required autofocus placeholder="GAM-GAT-PER-000001">
        </div>
        <div class="form-field">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>
    <p class="meta"><a href="/login">Connexion admin</a></p>
</div>
