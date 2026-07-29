<?php

declare(strict_types=1);

/**
 * Registre des incidents — vue web EN LECTURE SEULE.
 *
 * Le registre existe, il est ouvert, il est vide, et il porte une déclaration
 * motivée d'absence. Ce n'est pas la même chose qu'un inventaire manquant.
 *
 * Exécution locale : php -S 127.0.0.1:8088 -t core/registre-incidents/public
 */

use Gamad\RegistreIncidents\Ctr11;

require_once __DIR__ . '/../src/Ctr11.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr11 = new Ctr11($corpus);
$ecarts   = $ctr11->ecarts();
$absence  = $ctr11->declarationAbsence();
$exclus   = $ctr11->nonClassifications();

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Registre des incidents (CAP-CORE-018)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --warn:#d29922; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1000px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:18px 20px; margin-bottom:22px; }
  .kpi{ font-size:26px; font-weight:650; } .card small{ display:block; color:var(--muted); font-size:13px; margin-top:6px; }
  .ok{ color:var(--ok); } .ko{ color:var(--ko); } .warn{ color:var(--warn); } .muted{ color:var(--muted); }
  h2{ font-size:15px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:32px 0 12px; }
  table{ width:100%; border-collapse:collapse; font-size:13.5px; }
  th,td{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  th{ color:var(--muted); font-weight:600; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
  .scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Registre des incidents</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-018</code> · contrat <code>CTR-11</code>, partagé avec <code>CAP-CORE-017</code> · vue en lecture seule</p>

  <div class="card">
    <div class="kpi <?= $absence['declaree'] ? 'ok' : 'ko' ?>">Aucun incident — absence <?= $absence['declaree'] ? 'déclarée et motivée' : 'non déclarée' ?></div>
    <small><?= e((string) ($absence['motif'] ?? 'Le registre est muet sur la raison de son vide.')) ?></small>
  </div>

  <h2>Pourquoi cette absence n'en est pas une comme les autres</h2>
  <p class="sub">
    L'Article 53 attend « registre initial des incidents connus <strong>ou déclaration motivée d'absence</strong> ». La seconde branche est satisfaite&nbsp;: le registre <strong>existe</strong>, il est ouvert, il est vide, et il <strong>dit pourquoi</strong>.
    Un registre vide et muet laisserait ignorer si nul incident n'est survenu ou si nul n'a regardé. Confondre les deux situations effacerait cette différence (<code>INV-59</code>).
  </p>

  <h2>Faits écartés de la qualification d'incident</h2>
  <?php if ($exclus !== []): ?>
  <div class="scroll"><table>
    <thead><tr><th>Objet</th><th>Motif de l'exclusion</th></tr></thead>
    <tbody>
    <?php foreach ($exclus as $x): ?>
      <tr><td><?= e((string) $x['objet']) ?></td><td class="muted"><?= e((string) $x['motif']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Un fait écarté sans motif serait un fait caché — « incident caché » est le premier risque que l'Article 53 énumère. Le service restitue l'exclusion <strong>avec sa raison</strong>, et n'en juge pas le bien-fondé.</p>
  <?php else: ?>
  <p class="muted">Aucun fait n'est expressément écarté.</p>
  <?php endif; ?>

  <h2>Ce que le corpus n'établit pas</h2>
  <p>
    <?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?>
    <span class="pill">exercice sur scénario</span> <span class="pill">canal de signalement</span>
  </p>
  <p class="sub">Restitués <strong><?= e(Ctr11::NON_ETABLI) ?></strong>. L'Article 53 les réserve à l'autorité.</p>

  <p class="note">
    Le service <strong>ne déclare, ne classe et ne clôt aucun incident</strong> : l'Article 176 de <code>SECURITY-GOVERNANCE-0001</code> réserve la déclaration aux acteurs.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-018</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
