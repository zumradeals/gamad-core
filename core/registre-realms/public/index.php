<?php

declare(strict_types=1);

/**
 * Registre des realms — vue web EN LECTURE SEULE.
 *
 * Cette page restitue principalement une ABSENCE, et c'est son objet : aucun
 * realm n'est reconnu, et le Registre des realms — l'une des trois sources
 * canoniques de DOM-04 que l'Article 35 de l'Atlas nomme — n'est pas constitué.
 *
 * Le service ne fédère rien et n'accorde aucune confiance.
 *
 * Exécution locale : php -S 127.0.0.1:8085 -t core/registre-realms/public
 */

use Gamad\RegistreRealms\Ctr08;

require_once __DIR__ . '/../src/Ctr08.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr08 = new Ctr08($corpus);

$ecarts      = $ctr08->ecarts();
$sources     = $ctr08->sourcesCanoniques();
$definitions = $ctr08->definitions();
$externes    = $ctr08->externesNonRealms();

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
<title>GAMAD Core — Registre des realms (CAP-CORE-012)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --warn:#d29922; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1000px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:18px 20px; margin-bottom:22px; }
  .kpi{ font-size:30px; font-weight:650; } .card small{ display:block; color:var(--muted); font-size:13px; }
  .ok{ color:var(--ok); } .ko{ color:var(--ko); } .warn{ color:var(--warn); } .muted{ color:var(--muted); }
  h2{ font-size:15px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:32px 0 12px; }
  table{ width:100%; border-collapse:collapse; font-size:13.5px; }
  th,td{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  th{ color:var(--muted); font-weight:600; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
  .scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Registre des realms</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-012</code> · contrat <code>CTR-08</code>, partagé avec <code>CAP-CORE-011</code> · vue en lecture seule</p>

  <div class="card">
    <div class="kpi ko">Aucun realm reconnu</div>
    <small>
      Le Registre des realms n'est pas constitué. Ce n'est ni l'inventaire initial ni la décision motivée d'absence que l'Article 47 attend&nbsp;: c'est une troisième situation, et la nommer vaut mieux que la ranger dans l'une des deux.
    </small>
  </div>

  <h2>Les trois sources canoniques de <code>DOM-04</code> — Article 35 de l'Atlas</h2>
  <div class="scroll"><table>
    <thead><tr><th>Source canonique</th><th>Chemin attendu</th><th>Présence</th></tr></thead>
    <tbody>
    <?php foreach ($sources as $s): ?>
      <tr>
        <td><?= e((string) $s['libelle']) ?></td>
        <td><code><?= e((string) $s['chemin']) ?></code></td>
        <td class="<?= $s['presente'] ? 'ok' : 'ko' ?>"><?= $s['presente'] ? 'présente' : 'absente' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">L'absence d'une source canonique est <strong>constatée, jamais suppléée</strong> par une source voisine (<code>INV-55</code>). Aucun realm n'est tiré du Registre des produits.</p>

  <h2>Entités extérieures — et pourquoi elles ne sont pas des realms</h2>
  <?php if ($externes !== []): ?>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Entité</th><th>État au Registre des produits</th><th>Realm</th></tr></thead>
    <tbody>
    <?php foreach ($externes as $x): ?>
      <tr>
        <td><code><?= e((string) $x['reference']) ?></code></td>
        <td><?= e((string) $x['libelle']) ?></td>
        <td class="warn"><?= e((string) $x['etat']) ?></td>
        <td class="ko"><?= e((string) $x['realm']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Ce sont des <strong>familles de produits partenaires</strong>, non des realms. Les tenir pour des realms fédérés serait la « confiance implicite » que l'Article 47 range en tête de ses risques (<code>INV-54</code>).</p>
  <?php endif; ?>

  <h2>Définitions adoptées, restituées mot pour mot</h2>
  <div class="scroll"><table>
    <thead><tr><th>Terme</th><th>Définition — <code>LEXICON-0001</code></th></tr></thead>
    <tbody>
    <?php foreach ($definitions as $terme => $definition): ?>
      <tr><td><code><?= e((string) $terme) ?></code></td><td><?= e((string) $definition) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Ce que le corpus n'établit pas</h2>
  <p>
    <span class="pill">contrat de fédération</span>
    <span class="pill">procédure de retrait</span>
    <span class="pill">niveaux de confiance</span>
  </p>
  <p class="sub">Restitués <strong><?= e(Ctr08::NON_ETABLI) ?></strong>. Une fédération suppose un contrat ; aucun n'est adopté, et le service n'en propose aucun.</p>

  <p class="note">
    Le service <strong>ne reconnaît aucun realm, n'établit aucune fédération et n'accorde aucune confiance</strong>.
    Aucune écriture, aucune base : les fichiers du dépôt sont relevés à chaque affichage.
    Cette page ne rend <code>CAP-CORE-012</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
