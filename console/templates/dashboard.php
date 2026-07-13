<?php
/**
 * @var \Gamad\Console\Lib\CoreApiResponse $health
 * @var \Gamad\Console\Lib\CoreApiResponse $outbox
 * @var \Gamad\Console\Lib\CoreApiResponse $audit
 */
?>
<h1>Tableau de bord</h1>

<div class="card-grid">
    <div class="card">
        <h3>Santé des workers</h3>
        <?php if ($health->ok()): ?>
            <div class="value">
                <span class="badge <?= $health->data['healthy'] ? 'badge-success' : 'badge-danger' ?>">
                    <?= $health->data['healthy'] ? 'Sain' : 'Dégradé' ?>
                </span>
            </div>
            <p class="meta">
                <?= (int) $health->data['live_workers'] ?> vivant(s) ·
                <?= (int) $health->data['ready_workers'] ?> prêt(s) ·
                <?= (int) $health->data['stale_workers'] ?> périmé(s)
            </p>
        <?php else: ?>
            <p class="meta">Indisponible (<?= h((string) $health->status) ?>)</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Outbox</h3>
        <?php if ($outbox->ok()): ?>
            <div class="value"><?= (int) $outbox->data['pending'] ?> en attente</div>
            <p class="meta">
                <?= (int) $outbox->data['locked'] ?> verrouillé(s) ·
                <?= (int) $outbox->data['published'] ?> publié(s) ·
                <?= (int) $outbox->data['dead_letters'] ?> dead letter(s)
            </p>
        <?php else: ?>
            <p class="meta">Indisponible (<?= h((string) $outbox->status) ?>)</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Chaîne d'audit</h3>
        <?php if ($audit->ok()): ?>
            <div class="value">
                <span class="badge <?= $audit->data['valid'] ? 'badge-success' : 'badge-danger' ?>">
                    <?= $audit->data['valid'] ? 'Intègre' : 'Rompue' ?>
                </span>
            </div>
            <p class="meta">
                <?= (int) $audit->data['verified_count'] ?> enregistrement(s) vérifié(s)
                <?php if (!$audit->data['valid'] && $audit->data['failed_record_id'] !== null): ?>
                    · rupture à l'entrée #<?= h((string) $audit->data['failed_record_id']) ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="meta">Indisponible (<?= h((string) $audit->status) ?>)</p>
        <?php endif; ?>
    </div>
</div>

<p class="meta">Les cartes ci-dessus reflètent exactement ce que retourne l'API du Core — aucune donnée n'est calculée dans la console.</p>
