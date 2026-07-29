<?php

declare(strict_types=1);

/**
 * Audit commun — vue web EN LECTURE SEULE.
 *
 * Cette page restitue les levées de réserve AVEC les restrictions qu'elles
 * portent elles-mêmes, et nomme la non-indépendance de la fonction sous
 * laquelle elle est écrite (INV-62).
 *
 * Exécution locale : php -S 127.0.0.1:8091 -t core/registre-audit/public
 */

use Gamad\RegistreAudit\Ctr10;

require_once __DIR__ . '/../src/Ctr10.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$audit        = new Ctr10($corpus);
$ecarts       = $audit->ecarts();
$reserves     = $audit->reserves();
$independance = $audit->independanceDeLAudit();
$formes       = $audit->formesDeTrace();

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Audit commun (CAP-CORE-013)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --warn:#d29922; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1000px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:18px 20px; margin-bottom:22px; }
  .kpi{ font-size:24px; font-weight:650; } .card small{ display:block; color:var(--muted); font-size:13px; margin-top:6px; }
  .ok{ color:var(--ok); } .ko{ color:var(--ko); } .warn{ color:var(--warn); } .muted{ color:var(--muted); }
  h2{ font-size:15px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:32px 0 12px; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
  .tbl{ width:100%; border-collapse:collapse; font-size:13.5px; display:block; overflow-x:auto; }
  .tbl th,.tbl td{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  .tbl th{ color:var(--muted); font-weight:500; text-transform:uppercase; font-size:11.5px; letter-spacing:.04em; }
  ul{ margin:8px 0 0; padding-left:20px; } li{ margin:3px 0; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Audit commun</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-013</code> · criticité <code>CRITIQUE</code> · contrat <code>CTR-10</code> (volet audit) · vue en lecture seule</p>

  <div class="card">
    <div class="kpi ko">Audit non indépendant</div>
    <small>
      <?= e((string) $independance['constat']) ?>
      <br><br>Détenteur&nbsp;: <strong><?= e((string) $independance['detenteur']) ?></strong>
      — fonctions&nbsp;: <?php foreach ($independance['fonctions_transitoires'] as $f): ?><span class="pill"><?= e((string) $f) ?></span> <?php endforeach; ?>
      <br><br>Risque associé&nbsp;: <strong><?= e((string) $independance['risque_associe']) ?></strong>.
      Cette capacité a mission d'établir « qui a fait quoi, <em>sous quelle autorité</em> ». Elle ne peut pas taire que l'autorité de la fonction d'audit et l'autorité auditée sont la même personne. Le service <strong>nomme</strong> la non-indépendance&nbsp;; il ne l'atténue pas et ne la corrige pas.
      <br>Source&nbsp;: <code><?= e((string) $independance['source']) ?></code>
    </small>
  </div>

  <div class="card">
    <div class="kpi warn"><?= (int) $ecarts['reserves_restreintes'] ?> levée(s) sur <?= (int) $ecarts['reserves_levees'] ?> portent leur propre restriction</div>
    <small>
      Le Titre V du dossier d'audit lève les cinq écarts de <code>G0</code>. Deux de ces levées <strong>écrivent elles-mêmes ce qu'elles ne valent pas</strong>. Un lecteur du seul constat final — « les cinq écarts sont tous levés » — en tirerait l'inverse (<code>INV-62</code>).
    </small>
  </div>

  <h2>Les cinq réserves et ce que leur levée vaut</h2>
  <table class="tbl">
    <tr><th>Écart</th><th>Objet</th><th>Levée</th><th>Restriction écrite</th></tr>
    <?php foreach ($reserves as $r): ?>
    <tr>
      <td><code><?= e((string) $r['ecart']) ?></code></td>
      <td><?= e((string) $r['objet']) ?></td>
      <td class="<?= $r['levee'] ? 'ok' : 'ko' ?>"><?= $r['levee'] ? 'levée' : 'non levée' ?></td>
      <td class="<?= $r['restreinte'] ? 'warn' : 'muted' ?>"><?= $r['restreinte'] ? e((string) $r['restriction']) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <h2>Trace d'adoption — <?= (int) $formes['nombre_de_formes'] ?> formes pour <?= (int) $formes['actes'] ?> actes</h2>
  <p>
    <?php foreach ($formes['formes'] as $forme => $n): ?><span class="pill"><?= e((string) $forme) ?> — <?= (int) $n ?></span> <?php endforeach; ?>
  </p>
  <p class="sub">
    L'Article 49 range « impossibilité de reconstruire une action » parmi les risques de cette capacité. Le corpus enregistre sa propre trace sous trois formes jamais unifiées&nbsp;; une trace à trois formes n'est reconstituable que par un lecteur qui les connaît toutes les trois.
  </p>
  <?php if ($formes['incompletes'] !== []): ?>
  <p class="ko"><?= count($formes['incompletes']) ?> acte(s) dont la trace ne se reconstitue par aucune des formes connues&nbsp;:</p>
  <ul><?php foreach ($formes['incompletes'] as $a): ?><li><code><?= e((string) $a) ?></code></li><?php endforeach; ?></ul>
  <p class="sub"><?= e((string) $formes['portee']) ?></p>
  <?php endif; ?>

  <h2>Ce que le corpus n'établit pas</h2>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub"><?= e((string) $ecarts['ecart_global_preuve']) ?>. Restitués <strong><?= e(Ctr10::NON_ETABLI) ?></strong> ; l'Article 49 les range parmi ses décisions ouvertes, réservées à l'autorité.</p>

  <p class="note">
    Le service restitue les levées <strong>avec leurs restrictions</strong>. Il ne prononce, ne requalifie et ne juge aucune levée, et ne réécrit aucun acte pour uniformiser la trace.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-013</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
