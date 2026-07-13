<?php
/**
 * @var string $message
 * @var string $actionUrl
 * @var string $cancelUrl
 * @var string $csrf
 * @var bool $danger
 */
?>
<div class="confirm-box">
    <p><?= h($message) ?></p>
    <form method="post" action="<?= h($actionUrl) ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="btn <?= $danger ? 'btn-danger' : 'btn-primary' ?>">Confirmer</button>
        <a href="<?= h($cancelUrl) ?>" class="btn btn-neutral">Annuler</a>
    </form>
</div>
