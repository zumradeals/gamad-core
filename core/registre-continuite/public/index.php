<?php

declare(strict_types=1);

/**
 * Sauvegarde et restauration souveraines — vue web EN LECTURE SEULE.
 *
 * Cette page s'arrête devant une frontière et le déclare : l'inventaire des
 * sauvegardes techniques réelles est réservé à l'autorité (Article 4 du
 * Registre initial des sauvegardes ; ADOPTION-0025, Art. 3.a).
 *
 * Exécution locale : php -S 127.0.0.1:8089 -t core/registre-continuite/public
 */

use Gamad\RegistreContinuite\Ctr18;

require_once __DIR__ . '/../src/Ctr18.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr18 = new Ctr18($corpus);
$ecarts     = $ctr18->ecarts();
$redondance = $ctr18->redondanceDeFait();
$exclusion  = $ctr18->exclusionDeMission();

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Sauvegarde et restauration (CAP-CORE-019)</title>
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
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Sauvegarde et restauration souveraines</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-019</code> · criticité <code>RACINE</code> · contrat <code>CTR-18</code> · vue en lecture seule</p>

  <div class="card">
    <div class="kpi ko">Aucune sauvegarde éprouvée</div>
    <small>
      <?= e((string) $redondance['constat']) ?>
      <br><br><strong><?= e((string) $redondance['qualification']) ?></strong> — la Loi 44 de <code>CORE-LAWS-0001</code> est explicite : une sauvegarde n'est réputée fiable qu'après vérification de son intégrité et tests périodiques de restauration. Deux copies non éprouvées ne sont pas une sauvegarde&nbsp;; elles sont deux copies.
    </small>
  </div>

  <div class="card">
    <div class="kpi warn"><?= e((string) $ecarts['inventaire_technique']) ?></div>
    <small>
      <?= e((string) $exclusion['motif']) ?>
      <br><br>Le service pourrait techniquement énumérer des artefacts, des dépôts, des emplacements. <strong>Il ne le fait pas.</strong> Un service qui franchirait cette frontière « pour être utile » rendrait le corpus faux sur le point même où il se veut le plus strict (<code>INV-61</code>).
      <br>Source : <code><?= e((string) $exclusion['source']) ?></code>
    </small>
  </div>

  <h2>Tests de restauration inscrits</h2>
  <p class="<?= $ecarts['tests_de_restauration'] === 0 ? 'ko' : 'ok' ?>">
    <?= (int) $ecarts['tests_de_restauration'] ?> — l'Article 54 attend « au moins un exercice de restauration pour les preuves et sources racines » parmi ses preuves <code>G0</code>. Cette attente n'est pas satisfaite, et la redondance de fait ne la satisfait pas à sa place.
  </p>

  <h2>Ce que le corpus n'établit pas</h2>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub"><?= e((string) $ecarts['ecart_global_continuite']) ?>. Restitués <strong><?= e(Ctr18::NON_ETABLI) ?></strong> ; l'Article 54 les réserve à l'autorité.</p>

  <p class="note">
    Le service restitue ce que le corpus déclare et <strong>s'arrête à l'exclusion de mission</strong>. Il n'atteste aucune sauvegarde, ne demande aucune restauration et ne réconcilie rien.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-019</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
