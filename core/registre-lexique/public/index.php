<?php

declare(strict_types=1);

/**
 * Lexique canonique — vue web EN LECTURE SEULE.
 *
 * Cette page vérifie une empreinte plutôt que de la croire, et nomme ce que
 * nul n'a tranché. Elle ne crée, ne modifie et ne déprécie aucune entrée de
 * `LEXICON-0001` (INV-63, INV-64).
 *
 * Exécution locale : php -S 127.0.0.1:8090 -t core/registre-lexique/public
 */

use Gamad\RegistreLexique\Ctr19;

require_once __DIR__ . '/../src/Ctr19.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr19        = new Ctr19($corpus);
$ecarts       = $ctr19->ecarts();
$version      = $ctr19->versionDeReference();
$observations = $ctr19->observationsNonTranchees();
$decisions    = $ctr19->decisionsEtConflits();

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Lexique canonique (CAP-CORE-010)</title>
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
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; word-break:break-all; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
  ul{ margin:8px 0 0; padding-left:20px; } li{ margin:3px 0; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Lexique canonique</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-010</code> · criticité <code>CRITIQUE</code> · contrat <code>CTR-19</code> · vue en lecture seule</p>

  <div class="card">
    <div class="kpi <?= $version['concordante'] ? 'ok' : 'ko' ?>">
      <?= $version['concordante'] ? 'Version de référence vérifiée' : 'ÉCART D’EMPREINTE' ?>
    </div>
    <small>
      Version <code><?= e((string) $version['version']) ?></code> de <code>LEXICON-0001</code>, <?= (int) $ecarts['entrees'] ?> entrées.
      <br><br>Déclarée&nbsp;: <code><?= e((string) $version['empreinte_declaree']) ?></code>
      <br>Recalculée&nbsp;: <code><?= e((string) $version['empreinte_reelle']) ?></code>
      <br><br>L'empreinte n'est pas crue&nbsp;: elle est <strong>recalculée</strong> sur le fichier canonique et confrontée à la déclaration de l'<?= e('Article 6') ?> du Registre lexical (<code>INV-64</code>). Un écart serait un fait à soumettre à l'autorité, <strong>jamais un défaut à corriger d'office</strong> (<code>INV-43</code>).
    </small>
  </div>

  <?php foreach ($observations as $observation): ?>
  <div class="card">
    <div class="kpi warn"><?= e((string) $observation['statut']) ?> — « <?= e((string) $observation['terme']) ?> »</div>
    <small>
      <?= e((string) $observation['constat']) ?>
      <br><br>Signalée par&nbsp;: <code><?= e((string) $observation['signalee_par']) ?></code>
      <br>Employé dans&nbsp;:
      <ul><?php foreach ($observation['employe_dans'] as $t): ?><li><code><?= e((string) $t) ?></code></li><?php endforeach; ?></ul>
      Reportée dans&nbsp;:
      <ul><?php foreach ($observation['reportee_dans'] as $t): ?><li><code><?= e((string) $t) ?></code></li><?php endforeach; ?></ul>
      <br><strong>Arbitrages&nbsp;: <?= (int) $observation['arbitrages'] ?>.</strong> Un report n'est pas un arbitrage. Le service compte les arbitrages, jamais les mentions&nbsp;: deux textes qui signalent la même observation sans la trancher ne font pas un traitement (<code>INV-63</code>).
    </small>
  </div>
  <?php endforeach; ?>

  <h2>Décisions lexicales et conflits enregistrés</h2>
  <p>
    <span class="pill"><?= (int) $decisions['decisions_lexicales'] ?> décision(s)</span>
    <span class="pill"><?= (int) $decisions['conflits'] ?> conflit(s)</span>
  </p>
  <p class="sub"><?= e((string) $decisions['qualification']) ?> — <?= e((string) $decisions['source']) ?>.</p>

  <h2>Ce que le corpus n'établit pas</h2>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub">Restitués <strong><?= e(Ctr19::NON_ETABLI) ?></strong> ; l'Article 45 les range parmi ses décisions ouvertes, réservées à l'autorité. Le contrôle lexical mécanisé de l'Article 3 du Registre lexical n'est pas davantage établi.</p>

  <p class="note">
    Le service résout un terme, vérifie une version et relève ce qui n'est pas tranché. Il <strong>ne tranche aucune ambiguïté</strong>, ne crée, ne modifie, ne déprécie aucune entrée et ne met à jour aucune empreinte déclarée.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-010</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
