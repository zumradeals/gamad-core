<?php
/**
 * @var array<string, mixed>|null $organization
 * @var string $orgId
 * @var list<array<string, mixed>> $items
 */
?>
<p><a href="/organisations/<?= h($orgId) ?>">&larr; Retour à l'organisation</a></p>

<h1>Membres — <?= h($organization !== null ? (string) $organization['name'] : $orgId) ?></h1>
<p class="meta">Memberships actifs de <code><?= h($orgId) ?></code>.</p>

<table>
    <thead>
        <tr>
            <th>Personne</th>
            <th>Type</th>
            <th>Statut</th>
            <th>Débuté le</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($items === []): ?>
            <tr><td colspan="5" class="meta">Aucun membership actif.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= h((string) $item['person_id']) ?></td>
                <td><span class="badge badge-neutral"><?= h((string) $item['membership_type']) ?></span></td>
                <td><span class="badge badge-neutral"><?= h((string) $item['status']) ?></span></td>
                <td><?= h((string) $item['started_at']) ?></td>
                <td>
                    <form method="post" action="/memberships/<?= h((string) $item['membership_id']) ?>/end">
                        <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
                        <input type="hidden" name="org_id" value="<?= h($orgId) ?>">
                        <button type="submit" class="btn btn-danger">Terminer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="section-title">Ajouter un membre</div>
<form method="post" action="/organisations/<?= h($orgId) ?>/membres" style="max-width: 420px;">
    <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
    <div class="form-field">
        <label for="person_id">Identifiant de la personne (déjà existante dans le Core)</label>
        <input type="text" id="person_id" name="person_id" required placeholder="GAM-GAT-PER-000002">
    </div>
    <div class="form-field">
        <label for="membership_type">Type de membership</label>
        <select id="membership_type" name="membership_type" required>
            <option value="GAMAD_CITIZEN">GAMAD_CITIZEN — JE SUIS GAMAD</option>
            <option value="ORDINARY_CITIZEN">ORDINARY_CITIZEN — JE TRAVAILLE POUR GAMAD</option>
            <option value="PARTNER">PARTNER — JE TRAVAILLE AVEC GAMAD</option>
        </select>
    </div>
    <div class="form-field">
        <label for="department_id">Département (optionnel)</label>
        <input type="text" id="department_id" name="department_id" placeholder="uuid du département">
    </div>
    <button type="submit" class="btn btn-primary">Ajouter</button>
</form>
