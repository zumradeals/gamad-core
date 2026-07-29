<?php

declare(strict_types=1);

/**
 * Journal d'événements communs — vue web EN LECTURE SEULE.
 *
 * Cette page constate une absence et en nomme l'espèce. Elle n'invente aucun
 * type d'événement, aucune convention de version, aucune garantie de
 * livraison (INV-65).
 *
 * Exécution locale : php -S 127.0.0.1:8092 -t core/registre-evenements/public
 */

use Gamad\RegistreEvenements\Ctr07;

require_once __DIR__ . '/../src/Ctr07.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr07   = new Ctr07($corpus);
$ecarts  = $ctr07->ecarts();
$famille = $ctr07->familleRattachee();
$journal = $ctr07->journal();
$especes = $ctr07->especesDAbsence();
$donnees = $ctr07->donnees();

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Journal d'événements communs (CAP-CORE-014)</title>
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
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Journal d'événements communs</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-014</code> · criticité <code>CRITIQUE</code> · contrat <code>CTR-07</code> · vue en lecture seule</p>

  <div class="card">
    <div class="kpi ok">Famille <code><?= e((string) $famille['famille']) ?></code> adoptée</div>
    <small>
      <?= e((string) $famille['objet']) ?> — gardée par <code><?= e((string) $famille['gardien']) ?></code>, rattachée à <code><?= e(Ctr07::CAPACITE) ?></code>.
      <br>Source&nbsp;: <code><?= e((string) $famille['source']) ?></code>
    </small>
  </div>

  <div class="card">
    <div class="kpi ko"><?= e((string) $journal['espece']) ?></div>
    <small>
      Aucun registre d'événements n'existe dans le corpus&nbsp;; aucune déclaration motivée de cette absence n'existe non plus. <strong><?= (int) $ecarts['types_etablis'] ?> type d'événement établi.</strong>
      <br><br>Une famille de contrat adoptée <strong>n'est pas</strong> un registre établi. L'existence de <code>CTR-07</code> n'établit ni les types, ni le mécanisme, ni la conservation (<code>INV-65</code>).
    </small>
  </div>

  <h2>Les trois espèces d'absence</h2>
  <table class="tbl">
    <tr><th>Capacité</th><th>Objet</th><th>Registre</th><th>Espèce</th></tr>
    <?php foreach ($especes as $sp): ?>
    <tr>
      <td><code><?= e((string) $sp['capacite']) ?></code></td>
      <td><?= e((string) $sp['objet']) ?></td>
      <td class="<?= $sp['registre_existe'] ? 'ok' : 'ko' ?>"><?= $sp['registre_existe'] ? 'existe' : 'aucun' ?></td>
      <td class="<?= $sp['espece'] === Ctr07::ABSENCE_NON_DECLAREE ? 'ko' : 'warn' ?>"><?= e((string) $sp['espece']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p class="sub">
    La troisième espèce est la plus dangereuse&nbsp;: elle ne se distingue d'un oubli par aucun signe. Là où l'absence est <em>écrite</em>, elle est vérifiable&nbsp;; là où rien n'est écrit, rien ne peut l'être.
  </p>

  <h2>Données minimales attendues</h2>
  <p><?php foreach ($donnees['minimales'] as $d): ?><span class="pill"><?= e((string) $d) ?></span> <?php endforeach; ?></p>

  <h2>Données exclues — ce qu'un journal ne portera jamais</h2>
  <p><?php foreach ($donnees['exclues'] as $d): ?><span class="pill"><?= e((string) $d) ?></span> <?php endforeach; ?></p>
  <p class="sub"><code>CAP-CORE-014</code> est l'une des rares fiches à porter une ligne « Données exclues ». Ce qu'un journal ne doit jamais porter vaut, ici, autant que ce qu'il doit porter.</p>

  <h2>Ce que le corpus n'établit pas</h2>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub">Restitués <strong><?= e(Ctr07::NON_ETABLI) ?></strong> ; l'Article 48 les range parmi ses décisions ouvertes, réservées à l'autorité.</p>

  <p class="note">
    Le service restitue la famille adoptée et constate l'absence du journal. Il <strong>n'invente aucun type d'événement</strong>, aucune convention de version, aucune garantie de livraison ni politique de conservation.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-014</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
