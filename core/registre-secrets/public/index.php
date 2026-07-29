<?php

declare(strict_types=1);

/**
 * Gouvernance des secrets et clés — vue web EN LECTURE SEULE.
 *
 * Cette page est bornée deux fois : elle ne franchit pas l'exclusion de
 * mission (INV-61) et ne produit aucune valeur secrète (INV-66). Elle n'affiche
 * ni secret, ni clé, ni certificat, ni fragment de l'un d'eux.
 *
 * Exécution locale : php -S 127.0.0.1:8093 -t core/registre-secrets/public
 */

use Gamad\RegistreSecrets\Ctr20;

require_once __DIR__ . '/../src/Ctr20.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr20        = new Ctr20($corpus);
$ecarts       = $ctr20->ecarts();
$schema       = $ctr20->schema();
$interdiction = $ctr20->interdictionAbsolue();
$exclusions   = $ctr20->exclusionsDeMission();
$attestation  = $ctr20->attesterInterdiction();
$inventaire   = $ctr20->inventaire();

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Gouvernance des secrets et clés (CAP-CORE-016)</title>
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
  <h1>GAMAD Core — Gouvernance des secrets et clés</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-016</code> · criticité <code>RACINE</code> · contrat <code>CTR-20</code> · vue en lecture seule</p>

  <div class="card">
    <div class="kpi warn"><?= e((string) $ecarts['inventaire']) ?></div>
    <small>
      Secrets, clés, certificats, coffres, détenteurs — <strong>rien n'est énuméré ici.</strong>
      <br><br>Le service pourrait techniquement énumérer des dépôts, des variables d'environnement, des fichiers de configuration. <strong>Il ne le fait pas.</strong> L'inventaire réel relève exclusivement de l'autorité (<code>INV-61</code>).
      <br>Source&nbsp;: <code><?= e((string) $inventaire['source']) ?></code>
    </small>
  </div>

  <div class="card">
    <div class="kpi <?= $attestation['tenue'] ? 'ok' : 'ko' ?>">
      Interdiction absolue — <?= $attestation['tenue'] ? 'tenue' : 'ENFREINTE' ?>
    </div>
    <small>
      <?= e((string) $interdiction['enonce']) ?>
      <br><br>Fondements&nbsp;: <?php foreach ($interdiction['fondements'] as $f): ?><span class="pill"><?= e((string) $f) ?></span> <?php endforeach; ?>
      <br><br><strong><?= (int) $ecarts['occurrences_de_valeur'] ?> occurrence</strong> de forme de valeur dans les <?= count($attestation['sources_lues']) ?> sources lues.
      Le relevé porte le <em>nom</em> du motif et le <em>nombre</em>, <strong>jamais la correspondance</strong>&nbsp;: un détecteur qui citerait ce qu'il trouve violerait l'interdiction qu'il atteste (<code>INV-66</code>).
    </small>
  </div>

  <div class="card">
    <div class="kpi">Deux bornes, qui ne se valent pas</div>
    <small>
      L'<strong>exclusion de mission</strong> borne ce que le service a le droit de <em>connaître</em>&nbsp;; elle tomberait si l'autorité renseignait l'inventaire elle-même (<code>INV-61</code>).
      <br><br>L'<strong>interdiction absolue</strong> borne ce que le service a le droit de <em>produire</em>&nbsp;; elle survit à la levée de la première. Le jour où l'autorité renseignera l'inventaire, l'Article 3 s'appliquera encore (<code>INV-66</code>).
    </small>
  </div>

  <h2>Les deux exclusions de mission déclarées</h2>
  <table class="tbl">
    <tr><th>Objet</th><th>Déclarée</th><th>Inventaire</th><th>Source</th></tr>
    <?php foreach ($exclusions as $ex): ?>
    <tr>
      <td><?= e((string) $ex['objet']) ?></td>
      <td class="<?= $ex['declaree'] ? 'ok' : 'ko' ?>"><?= $ex['declaree'] ? 'oui' : 'non' ?></td>
      <td class="warn"><?= e((string) $ex['inventaire']) ?></td>
      <td><code><?= e(basename((string) $ex['source'])) ?></code></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <h2>Schéma d'une entrée — <?= count($schema) ?> champs, et la valeur n'en est pas</h2>
  <p><?php foreach ($schema as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub">L'exclusion de la valeur n'est pas une limitation de la famille <code>CTR-20</code>&nbsp;: elle en est la définition. Une famille qui porterait la valeur violerait la Loi 40.</p>

  <h2>Ce que le corpus n'établit pas</h2>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub"><?= e((string) $ecarts['ecart_global_securite']) ?>. Restitués <strong><?= e(Ctr20::NON_ETABLI) ?></strong> ; l'Article 51 les range parmi ses décisions ouvertes, réservées à l'autorité.</p>

  <p class="note">
    Le service n'inventorie, ne crée, ne fait tourner et ne révoque aucun secret, aucune clé, aucun certificat, aucun coffre. Il <strong>n'écrit aucune valeur secrète où que ce soit</strong>.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-016</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
