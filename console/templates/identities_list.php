<?php
/**
 * @var list<array<string, mixed>> $items
 * @var string|null $nextCursor
 * @var array{type: string, status: string, cursor: string} $filters
 */
?>
<h1>Identités</h1>
<p><a href="/identities/new" class="btn btn-primary">Enregistrer une identité</a></p>

<form method="get" action="/identities" class="filters">
    <div class="form-field">
        <label for="type">Type</label>
        <select id="type" name="type">
            <option value="">Tous</option>
            <?php foreach (['person', 'organization', 'application', 'service', 'agent', 'device', 'resource'] as $type): ?>
                <option value="<?= h($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= h($type) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-field">
        <label for="status">Statut</label>
        <select id="status" name="status">
            <option value="">Tous</option>
            <?php foreach (['draft', 'active', 'suspended', 'archived', 'revoked'] as $status): ?>
                <option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-neutral">Filtrer</button>
</form>

<table>
    <thead>
        <tr>
            <th>Identifiant</th>
            <th>Type</th>
            <th>Statut</th>
            <th>Enregistrée le</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($items === []): ?>
            <tr><td colspan="4" class="meta">Aucune identité pour ces filtres.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="/identities/<?= h((string) $item['identity_id']) ?>"><?= h((string) $item['identity_id']) ?></a></td>
                <td><?= h((string) $item['identity_type']) ?></td>
                <td><span class="badge badge-neutral"><?= h((string) $item['status']) ?></span></td>
                <td><?= h((string) $item['registered_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($nextCursor !== null): ?>
    <p>
        <a class="btn btn-neutral" href="/identities?<?= h(http_build_query(array_filter($filters + ['cursor' => $nextCursor]))) ?>">Page suivante</a>
    </p>
<?php endif; ?>
