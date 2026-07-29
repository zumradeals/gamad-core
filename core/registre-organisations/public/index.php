<?php

declare(strict_types=1);

/**
 * Registre des organisations — vue web EN LECTURE SEULE.
 *
 * Restitue ce que le service CTR-17 dérive du Registre initial des
 * organisations : les organisations inscrites, leur type, leur statut et le
 * texte adopté qui les fonde.
 *
 * Le service ne crée aucune organisation et ne confère aucune personnalité
 * juridique. Être nommée par un texte ne vaut pas reconnaissance (INV-56).
 *
 * Exécution locale : php -S 127.0.0.1:8086 -t core/registre-organisations/public
 */

use Gamad\RegistreOrganisations\Ctr17;

require_once __DIR__ . '/../src/Ctr17.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr17 = new Ctr17($corpus);

$organisations = $ctr17->organisations();
$ecarts        = $ctr17->ecarts();

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
<title>GAMAD Core — Registre des organisations (CAP-CORE-002)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --warn:#d29922; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1000px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; margin-bottom:28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
  .kpi{ font-size:30px; font-weight:650; } .card small{ display:block; color:var(--muted); font-size:13px; }
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
  <h1>GAMAD Core — Registre des organisations</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-002</code> · contrat <code>CTR-17</code> — famille créée par le Titre XV de l'Atlas · vue en lecture seule</p>

  <div class="grid">
    <div class="card"><div class="kpi"><?= (int) $ecarts['organisations'] ?></div><small>organisations inscrites</small></div>
    <div class="card"><div class="kpi ok"><?= count($ecarts['reconnues']) ?></div><small>reconnues</small></div>
    <div class="card"><div class="kpi warn"><?= count($ecarts['champs_non_etablis']) ?></div><small>champs non établis</small></div>
  </div>

  <h2>Organisations inscrites</h2>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Organisation</th><th>Type</th><th>Statut</th><th>Source</th></tr></thead>
    <tbody>
    <?php foreach ($organisations as $reference => $o): ?>
      <tr>
        <td><code><?= e((string) $reference) ?></code></td>
        <td><?= e((string) $o['libelle']) ?></td>
        <td><?= e((string) $o['type']) ?></td>
        <td class="<?= $o['statut'] === 'RECONNUE' ? 'ok' : 'warn' ?>"><?= e((string) $o['statut']) ?></td>
        <td><code><?= e((string) $o['source']) ?></code></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Entités nommées et non inscrites</h2>
  <p class="sub">
    <code>ADOPTION-0025</code>, Art. 3.d nomme <strong>Wasplex</strong> et <strong>IKOMA</strong> comme extérieurs à GAMAD ; le Registre des produits les inscrit comme <strong>familles de produits partenaires</strong>, non comme organisations.
    Les organisations qui les possèdent ne sont nommées par aucun texte adopté&nbsp;: les inscrire exigerait de leur donner un nom que le corpus n'a pas écrit.
  </p>
  <p class="sub"><strong>Être nommée par un texte ne vaut pas reconnaissance</strong> — seule l'inscription reconnaît (<code>INV-56</code>).</p>

  <h2>Champs que l'Article 37 exige et que le corpus n'établit pas</h2>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub">Restitués <strong><?= e(Ctr17::NON_ETABLI) ?></strong>, jamais comblés par une valeur plausible. La typologie, l'autorité d'admission et l'articulation avec les réalités juridiques demeurent réservées à l'autorité.</p>

  <p class="note">
    Registre dérivé, jamais autoritatif : il <strong>ne crée aucune organisation, n'en admet aucune et ne confère aucune personnalité juridique</strong>.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-002</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
