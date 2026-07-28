<?php

declare(strict_types=1);

/**
 * Tableau de bord de l'Annuaire des capacités — vue web EN LECTURE SEULE.
 *
 * Restitue ce que le service CTR-14 dérive du corpus : les vingt capacités
 * souveraines et leurs quatre dimensions d'état, les seize familles de contrat
 * et leur domaine gardien, la comparaison Atlas–Registre–réalité exigée par
 * l'Article 55, et le relevé des écarts.
 *
 * L'annuaire décrit ; il ne fonde rien (INV-36). Aucune écriture, aucune base :
 * la page relève les fichiers du dépôt à chaque affichage. Ce qu'elle montre
 * n'a pas plus d'autorité que les textes dont elle procède.
 *
 * Exécution locale : php -S 127.0.0.1:8081 -t core/registre-annuaire/public
 */

use Gamad\RegistreAnnuaire\Ctr14;

require_once __DIR__ . '/../src/Ctr14.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3); // core/registre-annuaire/public -> racine du dépôt
}

$ctr14 = new Ctr14($corpus);

$ecarts = $ctr14->ecarts();
$reel = $ctr14->comparerReel();
$atlas = $ctr14->comparerAtlas();
$partages = $ctr14->partages();
$usurpations = $ctr14->usurpations();
$modules = $ctr14->modules();

$divergentes = array_values(array_filter($reel, static fn (array $l) => $l['verdict'] === 'DIVERGENCE'));

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Abrège un état pour la grille sans en altérer le sens. */
function etat(array $l, string $dimension): string
{
    return (string) ($l['declare'][$dimension] ?? '—');
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Annuaire des capacités (CAP-CORE-020)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --accent:#58a6ff; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1180px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
  .kpi{ font-size:30px; font-weight:650; } .kpi small{ font-size:13px; font-weight:400; color:var(--muted); }
  .card small{ display:block; color:var(--muted); font-size:13px; }
  .ok{ color:var(--ok); } .ko{ color:var(--ko); }
  h2{ font-size:15px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:32px 0 12px; }
  table{ width:100%; border-collapse:collapse; font-size:13.5px; }
  th,td{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  th{ color:var(--muted); font-weight:600; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
  .scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
  .muted{ color:var(--muted); }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Annuaire des capacités et Atlas</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-020</code> · contrat <code>CTR-14</code> (lecture et attestation) · vue en lecture seule</p>

  <div class="grid">
    <div class="card"><div class="kpi"><?= (int) $ecarts['capacites'] ?></div><small>capacités souveraines</small></div>
    <div class="card"><div class="kpi"><?= (int) $ecarts['capacites_codees'] ?></div><small>capacités codées</small></div>
    <div class="card"><div class="kpi <?= $ecarts['divergentes'] ? 'ko' : 'ok' ?>"><?= (int) $ecarts['divergentes'] ?></div><small>divergences carte/réalité</small></div>
    <div class="card"><div class="kpi <?= $ecarts['atlas_divergent'] ? 'ko' : 'ok' ?>"><?= (int) $ecarts['atlas_divergent'] ?></div><small>divergences Atlas/Registre</small></div>
    <div class="card"><div class="kpi"><?= (int) $ecarts['familles'] ?></div><small>familles de contrat</small></div>
    <div class="card"><div class="kpi <?= $usurpations ? 'ko' : 'ok' ?>"><?= count($usurpations) ?></div><small>usurpations de famille</small></div>
  </div>

  <h2>Les vingt capacités — état déclaré et réalité observée</h2>
  <div class="scroll"><table>
    <thead><tr>
      <th>Capacité</th><th>Conception</th><th>Implémentation</th><th>Exploitation</th><th>Preuve</th>
      <th>Module observé</th><th>Garde</th><th>CI</th><th>Verdict</th>
    </tr></thead>
    <tbody>
    <?php foreach ($reel as $l): $o = $l['observe']; ?>
      <tr>
        <td><code><?= e($l['capacite']) ?></code></td>
        <td><?= e(etat($l, 'conception')) ?></td>
        <td><?= e(etat($l, 'implementation')) ?></td>
        <td><?= e(etat($l, 'exploitation')) ?></td>
        <td><?= e(etat($l, 'preuve')) ?></td>
        <td><?= $o['module'] !== null ? '<code>' . e($o['module']) . '</code>' : '<span class="muted">—</span>' ?></td>
        <td><?= $o['garde'] !== null ? '<span class="pill">oui</span>' : '<span class="muted">—</span>' ?></td>
        <td><?= $o['garde'] === null ? '<span class="muted">—</span>' : ($o['garde_en_ci'] ? '<span class="ok">✓</span>' : '<span class="ko">✗</span>') ?></td>
        <td class="<?= $l['verdict'] === 'CONCORDE' ? 'ok' : 'ko' ?>"><?= e($l['verdict']) ?></td>
      </tr>
      <?php foreach ($l['divergences'] as $d): ?>
        <tr><td colspan="9" class="ko"><?= e($d) ?></td></tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Familles de contrat (<?= (int) $ecarts['familles'] ?>) — domaine gardien et capacités servies</h2>
  <p class="sub">Une capacité ne porte qu'une famille dont elle garde le domaine (<code>INV-40</code>). Trois familles en servent deux par construction : le partage est régulier, il n'est pas une collision.</p>
  <div class="scroll"><table>
    <thead><tr><th>Famille</th><th>Capacités qui la revendiquent</th><th>Partagée</th></tr></thead>
    <tbody>
    <?php foreach ($ctr14->attributions() as $famille => $caps): ?>
      <tr>
        <td><code><?= e($famille) ?></code></td>
        <td><?= e(implode(' · ', $caps)) ?></td>
        <td><?= isset($partages[$famille]) ? '<span class="pill">partage régulier</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <?php if ($usurpations): ?>
  <h2 class="ko">Usurpations de famille</h2>
  <div class="scroll"><table>
    <thead><tr><th>Capacité</th><th>Famille</th><th>Constat</th></tr></thead>
    <tbody>
    <?php foreach ($usurpations as $u): ?>
      <tr><td><code><?= e((string) $u['capacite']) ?></code></td><td><code><?= e((string) $u['famille']) ?></code></td><td><?= e((string) $u['detail']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Ces constats sont <strong>nommés et non arbitrés</strong> (<code>INV-38</code>). Leur résolution appartient à l'autorité.</p>
  <?php endif; ?>

  <h2>Modules présents et capacité déclarée</h2>
  <p class="sub">Une famille pouvant servir deux capacités, le numéro ne rattache plus un module : chaque classe déclare la capacité qu'elle sert (<code>INV-41</code>), lue sur le disque.</p>
  <div class="scroll"><table>
    <thead><tr><th>Module</th><th>Classe</th><th>Famille</th><th>Capacité déclarée</th></tr></thead>
    <tbody>
    <?php foreach ($modules as $m): ?>
      <tr>
        <td><code><?= e((string) $m['module']) ?></code></td>
        <td><code><?= e((string) $m['classe']) ?></code></td>
        <td><code><?= e((string) $m['famille']) ?></code></td>
        <td><?= $m['capacite'] !== null ? '<code>' . e((string) $m['capacite']) . '</code>' : '<span class="ko">non rattaché</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Champs que le corpus n'établit pour aucune capacité</h2>
  <?php if ($ecarts['champs_non_etablis']): ?>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e($c) ?></span> <?php endforeach; ?></p>
  <p class="sub">Ces champs sont restitués comme <strong>non établis</strong>, jamais comblés par une valeur plausible (<code>INV-39</code>). Un annuaire qui invente un responsable crée une responsabilité qui n'existe pas.</p>
  <?php else: ?>
  <p class="ok">Aucun — les quatre champs sont établis pour au moins une capacité.</p>
  <?php endif; ?>

  <h2>Concordance Atlas — Registre (<?= count($atlas) ?> capacités confrontées)</h2>
  <p class="<?= $ecarts['atlas_divergent'] ? 'ko' : 'ok' ?>">
    <?= (int) $ecarts['atlas_divergent'] ?> divergence(s) de libellé ou de domaine entre <code>CORE-ATLAS-0001</code> et le Registre initial des capacités.
  </p>

  <p class="note">
    Annuaire dérivé du corpus, jamais autoritatif (<code>INV-36</code>) : cette page décrit, elle ne fonde rien.
    Les quatre dimensions d'état sont tenues distinctes (<code>INV-37</code>) ; les divergences sont nommées, jamais arbitrées (<code>INV-38</code>).
    Aucune écriture, aucune base : les fichiers du dépôt sont relevés à chaque affichage.
    Ce tableau de bord ne rend aucune capacité opérationnelle et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
