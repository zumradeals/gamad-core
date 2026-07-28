<?php

declare(strict_types=1);

/**
 * Tableau de bord du Registre des normes — vue web EN LECTURE SEULE.
 *
 * Expose visuellement l'état du Core : adoptions, intégrité des fichiers
 * canoniques, cohérence de l'index et preuve P3 de reconstruction temporelle.
 * Aucune route d'écriture (INV-4). La base n'est qu'un index dérivé ; les
 * fichiers Git restent la source de vérité (INV-5).
 */

use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Gamad\RegistreNormes\Ctr04;

require __DIR__ . '/../bootstrap.php';

$pdo = Db::connect();

// Construit l'index dérivé s'il est absent ou vide, ou sur demande explicite.
$vide = true;
try {
    $vide = ((int) $pdo->query('SELECT count(*) FROM adoption')->fetchColumn()) === 0;
} catch (\Throwable) {
    $vide = true;
}
if ($vide || isset($_GET['refresh'])) {
    (new Ingestion($pdo, REGN_CORPUS))->executer();
}

$ctr04 = new Ctr04($pdo, REGN_CORPUS);

$adoptions = $pdo->query('SELECT reference, autorite, date_adoption, signature_presente FROM adoption ORDER BY reference')->fetchAll();
$integrite = $ctr04->verifierIntegrite();
$index     = $ctr04->resoudreIndex();

$concordants = array_filter($integrite, fn ($l) => $l['concorde']);
$divergents  = array_filter($integrite, fn ($l) => !$l['concorde']);

// Preuve P3 exécutée en direct sur la page.
$p3 = [];
foreach ([['2026-07-26', 'EN CONCEPTION'], ['2026-07-27', 'CONÇUE'], ['2026-08-01', 'CONÇUE']] as [$d, $attendu]) {
    $r = $ctr04->resoudreCapacite('CAP-CORE-007', 'conception', $d);
    $p3[] = ['date' => $d, 'attendu' => $attendu, 'obtenu' => $r['valeur'] ?? '(aucun)', 'ok' => ($r['valeur'] ?? null) === $attendu];
}
$p3_ok = count(array_filter($p3, fn ($c) => $c['ok'])) === count($p3);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Registre des normes (CAP-CORE-007)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --accent:#58a6ff; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:980px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; margin-bottom:28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
  .kpi{ font-size:30px; font-weight:650; } .kpi small{ font-size:13px; font-weight:400; color:var(--muted); }
  .ok{ color:var(--ok); } .ko{ color:var(--ko); }
  h2{ font-size:15px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:32px 0 12px; }
  table{ width:100%; border-collapse:collapse; font-size:13.5px; }
  th,td{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); }
  th{ color:var(--muted); font-weight:600; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
  .scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
  a{ color:var(--accent); }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Registre des normes</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-007</code> · contrat <code>CTR-04</code> (lecture et attestation) · vue en lecture seule</p>

  <div class="grid">
    <div class="card"><div class="kpi"><?= count($adoptions) ?></div><small>actes d'adoption</small></div>
    <div class="card"><div class="kpi ok"><?= count($concordants) ?></div><small>fichiers intègres</small></div>
    <div class="card"><div class="kpi <?= count($divergents) ? 'ko' : 'ok' ?>"><?= count($divergents) ?></div><small>divergences d'empreinte</small></div>
    <div class="card"><div class="kpi <?= count($index['divergences']) ? 'ko' : 'ok' ?>"><?= count($index['divergences']) ?></div><small>divergences d'index</small></div>
    <div class="card"><div class="kpi <?= $p3_ok ? 'ok' : 'ko' ?>"><?= $p3_ok ? 'P3 ✓' : 'P3 ✗' ?></div><small>reconstruction temporelle</small></div>
  </div>

  <h2>Preuve P3 — reconstruction temporelle de <code>CAP-CORE-007</code></h2>
  <div class="scroll"><table>
    <thead><tr><th>Date interrogée</th><th>Statut attendu</th><th>Statut restitué</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($p3 as $c): ?>
      <tr>
        <td><code><?= e($c['date']) ?></code></td>
        <td><?= e($c['attendu']) ?></td>
        <td><?= e($c['obtenu']) ?></td>
        <td class="<?= $c['ok'] ? 'ok' : 'ko' ?>"><?= $c['ok'] ? '✓' : '✗' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Actes d'adoption (<?= count($adoptions) ?>)</h2>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Autorité</th><th>Date</th><th>Signature</th></tr></thead>
    <tbody>
    <?php foreach ($adoptions as $a): ?>
      <tr>
        <td><code><?= e($a['reference']) ?></code></td>
        <td><?= e($a['autorite']) ?></td>
        <td><?= e($a['date_adoption']) ?></td>
        <td><?= ((int) $a['signature_presente']) ? '<span class="pill">signé</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <?php if ($divergents): ?>
  <h2 class="ko">Divergences d'intégrité</h2>
  <div class="scroll"><table>
    <thead><tr><th>Fichier</th><th>Déclarée</th><th>Réelle</th></tr></thead>
    <tbody>
    <?php foreach ($divergents as $d): ?>
      <tr><td><code><?= e($d['chemin']) ?></code></td><td><code><?= e($d['empreinte_declaree']) ?></code></td><td><code><?= e($d['empreinte_reelle'] ?? 'fichier absent') ?></code></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <p class="note">
    Index dérivé des fichiers versionnés — les fichiers Git restent la source de vérité (INV-5).
    Aucune écriture du corpus depuis cette vue (INV-4). Empreintes recalculées, jamais recopiées (INV-1).
    Ce tableau de bord ne rend aucune capacité opérationnelle et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
