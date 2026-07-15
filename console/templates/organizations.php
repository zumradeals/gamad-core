<?php
/**
 * @var array<string, mixed>|null $root
 * @var bool $rootNotFound
 * @var list<array<string, mixed>> $children
 */
?>
<h1>Organisations</h1>
<p class="meta">
    Miroir direct de <code>openapi/organizations-and-memberships-v1.yaml</code> — il n'existe pas de liste globale
    des organisations dans ce contrat, donc cet écran part toujours de GAMAD SAS, la racine unique du realm
    (GENESIS-011 §2.1), et ses filles directes (DIRECTIVE-006).
</p>

<?php if ($rootNotFound): ?>
    <p class="meta">GAMAD SAS n'existe pas encore dans ce Core — exécutez <code>bin/bootstrap-organizations</code>.</p>
<?php elseif ($root !== null): ?>
    <div class="section-title">GAMAD SAS (racine)</div>
    <table>
        <tbody>
            <tr><th>Identifiant</th><td><a href="/organisations/<?= h((string) $root['organization_id']) ?>"><?= h((string) $root['organization_id']) ?></a></td></tr>
            <tr><th>Nom</th><td><?= h((string) $root['name']) ?></td></tr>
            <tr><th>Statut</th><td><span class="badge badge-neutral"><?= h((string) $root['status']) ?></span></td></tr>
            <tr><th>Fondée le</th><td><?= h((string) $root['founded_at']) ?></td></tr>
        </tbody>
    </table>

    <div class="section-title">Organisations filles directes</div>
    <table>
        <thead>
            <tr><th>Identifiant</th><th>Nom</th><th>Statut</th></tr>
        </thead>
        <tbody>
            <?php if ($children === []): ?>
                <tr><td colspan="3" class="meta">Aucune organisation fille.</td></tr>
            <?php endif; ?>
            <?php foreach ($children as $child): ?>
                <tr>
                    <td><a href="/organisations/<?= h((string) $child['organization_id']) ?>"><?= h((string) $child['organization_id']) ?></a></td>
                    <td><?= h((string) $child['name']) ?></td>
                    <td><span class="badge badge-neutral"><?= h((string) $child['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="section-title">Créer une organisation</div>
<form method="post" action="/organisations" style="max-width: 420px;">
    <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
    <div class="form-field">
        <label for="identity_id">Identifiant d'identité (type <code>organization</code>, déjà active dans le Registry)</label>
        <input type="text" id="identity_id" name="identity_id" required placeholder="GAM-GAT-ORG-000003">
    </div>
    <div class="form-field">
        <label for="name">Nom</label>
        <input type="text" id="name" name="name" required>
    </div>
    <div class="form-field">
        <label for="parent_id">Organisation parente (vide seulement pour GAMAD SAS elle-même)</label>
        <input type="text" id="parent_id" name="parent_id" placeholder="GAM-GAT-ORG-000001">
    </div>
    <button type="submit" class="btn btn-primary">Créer</button>
</form>
