<?php
/**
 * @var array<string, mixed>|null $person
 * @var string $personId
 * @var bool $personNotFound
 */
?>
<h1>Personnes</h1>
<p class="meta">
    Miroir direct de <code>openapi/persons-and-accounts-v1.yaml</code> — il n'existe pas de liste globale
    des personnes dans ce contrat, donc cet écran n'en propose pas (DIRECTIVE-005).
</p>

<div class="section-title">Rechercher une personne</div>
<form method="get" action="/personnes" class="filters">
    <div class="form-field">
        <label for="search_person_id">Identifiant</label>
        <input type="text" id="search_person_id" name="person_id" value="<?= h($personId) ?>" placeholder="GAM-GAT-PER-000001">
    </div>
    <button type="submit" class="btn btn-primary">Rechercher</button>
</form>

<?php if ($personId !== '' && $personNotFound): ?>
    <p class="meta">Personne introuvable.</p>
<?php elseif ($person !== null): ?>
    <table>
        <tbody>
            <tr><th>Identifiant</th><td><?= h((string) $person['person_id']) ?></td></tr>
            <tr><th>Nom déclaré</th><td><?= h((string) $person['declared_name']) ?></td></tr>
            <tr><th>Statut</th><td><span class="badge badge-neutral"><?= h((string) $person['status']) ?></span></td></tr>
            <tr><th>Enregistrée le</th><td><?= h((string) $person['registered_at']) ?></td></tr>
            <tr><th>Contact</th><td><?= h((string) ($person['contact'] ?? '')) ?></td></tr>
        </tbody>
    </table>

    <form method="post" action="/personnes/<?= h((string) $person['person_id']) ?>/account" style="margin-top: 1rem;">
        <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
        <button type="submit" class="btn btn-neutral">Créer le compte</button>
    </form>
    <p class="meta">
        La fiche Personne n'indique pas si un compte existe déjà — si c'est le cas, le Core refuse avec une
        erreur explicite ; la console ne le devine jamais à l'avance.
    </p>
<?php endif; ?>

<div class="section-title">Enregistrer une nouvelle personne</div>
<form method="post" action="/personnes" style="max-width: 420px;">
    <input type="hidden" name="csrf" value="<?= h(\Gamad\Console\Lib\Session::csrfToken()) ?>">
    <div class="form-field">
        <label for="identity_id">Identifiant d'identité (type <code>person</code>, déjà active dans le Registry)</label>
        <input type="text" id="identity_id" name="identity_id" required placeholder="GAM-GAT-PER-000002">
    </div>
    <div class="form-field">
        <label for="declared_name">Nom déclaré</label>
        <input type="text" id="declared_name" name="declared_name" required>
    </div>
    <div class="form-field">
        <label for="contact">Contact</label>
        <input type="text" id="contact" name="contact" required>
    </div>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
</form>
