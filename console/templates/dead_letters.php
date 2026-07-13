<?php
/**
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>|null $detail
 * @var bool $detailNotFound
 */
$detailNotFound ??= false;
?>
<h1>Outbox — Dead letters</h1>

<?php if ($detail !== null): ?>
    <div class="section-title">Détail du message</div>
    <table>
        <tbody>
            <tr><th>ID</th><td><?= h((string) $detail['id']) ?></td></tr>
            <tr><th>Agrégat</th><td><?= h((string) $detail['aggregate_id']) ?></td></tr>
            <tr><th>Événement</th><td><?= h((string) $detail['event_name']) ?></td></tr>
            <tr><th>Tentatives</th><td><?= h((string) $detail['attempts']) ?></td></tr>
            <tr><th>Dernière erreur</th><td><?= h((string) $detail['last_error']) ?></td></tr>
            <tr><th>Échec le</th><td><?= h((string) $detail['failed_at']) ?></td></tr>
            <tr><th>Payload</th><td><pre class="payload"><?= h(json_encode($detail['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '') ?></pre></td></tr>
        </tbody>
    </table>
    <p>
        <a class="btn btn-primary" href="/dead-letters/<?= h((string) $detail['id']) ?>/replay">Rejouer</a>
        <a class="btn btn-neutral" href="/dead-letters">Retour à la liste</a>
    </p>
<?php elseif ($detailNotFound): ?>
    <p class="meta">Message introuvable.</p>
    <p><a href="/dead-letters">Retour à la liste</a></p>
<?php endif; ?>

<div class="section-title">Liste</div>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Agrégat</th>
            <th>Événement</th>
            <th>Tentatives</th>
            <th>Échec le</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($items === []): ?>
            <tr><td colspan="5" class="meta">Aucune dead letter.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="/dead-letters/<?= h((string) $item['id']) ?>"><?= h(substr((string) $item['id'], 0, 8)) ?>…</a></td>
                <td><?= h((string) $item['aggregate_id']) ?></td>
                <td><?= h((string) $item['event_name']) ?></td>
                <td><?= h((string) $item['attempts']) ?></td>
                <td><?= h((string) $item['failed_at']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
