<?php
/**
 * @var array<string, mixed>|null $identity
 * @var string $identityId
 */
?>
<p><a href="/identities">&larr; Retour à la liste</a></p>

<?php if ($identity === null): ?>
    <h1><?= h($identityId) ?></h1>
    <p class="meta">Identité introuvable.</p>
<?php else: ?>
    <h1><?= h((string) $identity['identity_id']) ?></h1>

    <table>
        <tbody>
            <tr><th>Type</th><td><?= h((string) $identity['identity_type']) ?></td></tr>
            <tr><th>Statut</th><td><span class="badge badge-neutral"><?= h((string) $identity['status']) ?></span></td></tr>
            <tr><th>Enregistrée le</th><td><?= h((string) $identity['registered_at']) ?></td></tr>
        </tbody>
    </table>

    <div class="section-title">Transitions</div>
    <p class="meta">Chaque transition demande une confirmation avant tout envoi au Core.</p>
    <p>
        <?php foreach (['activate' => 'Activer', 'suspend' => 'Suspendre', 'archive' => 'Archiver', 'revoke' => 'Révoquer'] as $transition => $label): ?>
            <a class="btn <?= $transition === 'revoke' ? 'btn-danger' : 'btn-neutral' ?>"
               href="/identities/<?= h((string) $identity['identity_id']) ?>/transition/<?= h($transition) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>
