<?php
/**
 * @var array<string, mixed>|null $organization
 * @var bool $organizationNotFound
 * @var string $orgId
 * @var list<array<string, mixed>> $children
 */
?>
<p><a href="/organisations">&larr; Retour aux organisations</a></p>

<?php if ($organizationNotFound || $organization === null): ?>
    <h1><?= h($orgId) ?></h1>
    <p class="meta">Organisation introuvable.</p>
<?php else: ?>
    <h1><?= h((string) $organization['organization_id']) ?></h1>

    <table>
        <tbody>
            <tr><th>Nom</th><td><?= h((string) $organization['name']) ?></td></tr>
            <tr><th>Statut</th><td><span class="badge badge-neutral"><?= h((string) $organization['status']) ?></span></td></tr>
            <tr><th>Organisation parente</th><td><?= $organization['parent_id'] !== null ? h((string) $organization['parent_id']) : '—' ?></td></tr>
            <tr><th>Fondée le</th><td><?= h((string) $organization['founded_at']) ?></td></tr>
        </tbody>
    </table>

    <p><a class="btn btn-neutral" href="/organisations/<?= h((string) $organization['organization_id']) ?>/membres">Voir les membres</a></p>

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

    <div class="section-title">Créer une organisation fille</div>
    <form method="post" action="/organisations" style="max-width: 420px;">
        <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
        <input type="hidden" name="parent_id" value="<?= h((string) $organization['organization_id']) ?>">
        <div class="form-field">
            <label for="identity_id">Identifiant d'identité (type <code>organization</code>, déjà active dans le Registry)</label>
            <input type="text" id="identity_id" name="identity_id" required>
        </div>
        <div class="form-field">
            <label for="name">Nom</label>
            <input type="text" id="name" name="name" required>
        </div>
        <button type="submit" class="btn btn-primary">Créer</button>
    </form>

    <div class="section-title">Créer un département</div>
    <form method="post" action="/organisations/<?= h((string) $organization['organization_id']) ?>/departments" style="max-width: 420px;">
        <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
        <div class="form-field">
            <label for="department_name">Nom du département</label>
            <input type="text" id="department_name" name="name" required placeholder="Direction Générale">
        </div>
        <button type="submit" class="btn btn-neutral">Créer</button>
    </form>
<?php endif; ?>
