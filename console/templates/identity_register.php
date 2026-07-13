<h1>Enregistrer une identité</h1>

<form method="post" action="/identities" style="max-width: 420px;">
    <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
    <div class="form-field">
        <label for="identity_type">Type d'identité</label>
        <select id="identity_type" name="identity_type" required>
            <?php foreach (['person', 'organization', 'application', 'service', 'agent', 'device', 'resource'] as $type): ?>
                <option value="<?= h($type) ?>"><?= h($type) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <p class="meta">La clé d'idempotence est générée automatiquement par la console.</p>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="/identities" class="btn btn-neutral">Annuler</a>
</form>
