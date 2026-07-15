<?php
/**
 * @var string $csrf
 * @var bool $adminAuthenticated
 * @var bool $personAuthenticated
 */
?>
<div class="confirm-box">
    <h1>Déconnexion</h1>
    <p class="meta">
        Les deux contextes sont indépendants (ADR-0019) — choisissez ce qui doit être déconnecté.
        Aucune option ne déconnecte l'autre contexte à votre insu.
    </p>

    <?php if (!$adminAuthenticated && !$personAuthenticated): ?>
        <p class="meta">Aucune session active pour le moment.</p>
    <?php endif; ?>

    <?php if ($adminAuthenticated): ?>
    <form method="post" action="/logout/admin" style="margin-bottom: 0.75rem;">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="btn btn-neutral">Déconnexion admin</button>
    </form>
    <?php endif; ?>

    <?php if ($personAuthenticated): ?>
    <form method="post" action="/logout/operateur" style="margin-bottom: 0.75rem;">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="btn btn-neutral">Déconnexion opérateur</button>
    </form>
    <?php endif; ?>

    <?php if ($adminAuthenticated && $personAuthenticated): ?>
    <form method="post" action="/logout/all">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="btn btn-danger">Déconnexion des deux</button>
    </form>
    <?php endif; ?>
</div>
