<?php

declare(strict_types=1);

/**
 * Portefeuille des produits — vue web EN LECTURE SEULE.
 *
 * Restitue ce que le service CTR-08 dérive du Registre initial des produits :
 * les quatre produits historiques, leur état initial et courant, leur
 * admission, leur conformité et leur propriétaire institutionnel.
 *
 * Le service n'admet, ne qualifie et ne certifie aucun produit. Aucune
 * écriture, aucune base : les fichiers du dépôt sont relevés à chaque
 * affichage.
 *
 * Exécution locale : php -S 127.0.0.1:8084 -t core/registre-produits/public
 */

use Gamad\RegistreProduits\Ctr08;

require_once __DIR__ . '/../src/Ctr08.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr08 = new Ctr08($corpus);

$portefeuille = $ctr08->portefeuille();
$ecarts       = $ctr08->ecarts();
$hors         = $ctr08->etatsHorsVocabulaire();

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
<title>GAMAD Core — Portefeuille des produits (CAP-CORE-011)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --warn:#d29922; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1120px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
  .kpi{ font-size:30px; font-weight:650; } .card small{ display:block; color:var(--muted); font-size:13px; }
  .ok{ color:var(--ok); } .ko{ color:var(--ko); } .warn{ color:var(--warn); } .muted{ color:var(--muted); }
  h2{ font-size:15px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:32px 0 12px; }
  table{ width:100%; border-collapse:collapse; font-size:13.5px; }
  th,td{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  th{ color:var(--muted); font-weight:600; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
  .scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Portefeuille des produits</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-011</code> · contrat <code>CTR-08</code>, partagé avec <code>CAP-CORE-012</code> · vue en lecture seule</p>

  <div class="grid">
    <div class="card"><div class="kpi"><?= (int) $ecarts['produits'] ?></div><small>produits inscrits</small></div>
    <div class="card"><div class="kpi warn"><?= count($ecarts['non_admis']) ?></div><small>sans admission acquise</small></div>
    <div class="card"><div class="kpi ok"><?= (int) $ecarts['produits_certifies'] ?></div><small>produits certifiés</small></div>
    <div class="card"><div class="kpi warn"><?= count($ecarts['sans_proprietaire']) ?></div><small>sans propriétaire désigné</small></div>
  </div>

  <h2>Les quatre produits historiques</h2>
  <p class="sub">L'état courant procède du dernier Titre qui l'a constaté ; l'état initial demeure lisible à côté (<code>INV-53</code>). Un registre qui perdrait l'état antérieur perdrait la trace de la décision qui l'a changé.</p>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Produit</th><th>État initial</th><th>État courant</th><th>Admission</th><th>Conformité</th><th>Propriétaire</th></tr></thead>
    <tbody>
    <?php foreach ($portefeuille as $reference => $p): ?>
      <tr>
        <td><code><?= e((string) $reference) ?></code></td>
        <td><?= e((string) $p['libelle']) ?></td>
        <td class="muted"><?= e((string) $p['etat_initial']) ?></td>
        <td><strong><?= e((string) $p['etat']) ?></strong></td>
        <td class="<?= in_array($p['admission'], Ctr08::ADMISSION_ACQUISE, true) ? 'ok' : 'warn' ?>"><?= e((string) $p['admission']) ?></td>
        <td class="<?= in_array($p['conformite'], Ctr08::CONFORMITE_ACQUISE, true) ? 'ok' : 'warn' ?>"><?= e((string) $p['conformite']) ?></td>
        <td class="muted"><?= e((string) $p['proprietaire']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Aucun produit certifié</h2>
  <p class="sub">
    La réserve d'<code>ADOPTION-0025</code>, Art. 3.c est ici <strong>dérivée du corpus</strong>, non recopiée : aucun des quatre produits n'a d'admission acquise, et aucun n'est évalué conforme.
    GamaDrive est <strong>reconnu produit officiel</strong> depuis <code>ADOPTION-0023</code> et son dossier d'admission demeure à constituer&nbsp;: reconnaissance et admission sont deux choses, et les confondre certifierait un produit que nul n'a évalué (<code>INV-52</code>).
  </p>

  <h2>États courants hors du vocabulaire de l'Article 22</h2>
  <?php if ($hors !== []): ?>
  <div class="scroll"><table>
    <thead><tr><th>État employé</th><th>Produits</th><th>Terme voisin</th></tr></thead>
    <tbody>
    <?php foreach ($hors as $etat => $refs): ?>
      <tr><td><code><?= e((string) $etat) ?></code></td><td><?= e(implode(' · ', $refs)) ?></td><td class="muted">non traduit</td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Ces états procèdent d'un Titre adopté : ils sont réguliers <em>et</em> hors vocabulaire. Le service les <strong>nomme</strong> et ne les rapproche ni d'<code>ADMIS</code>, ni de <code>RETIRÉ</code>, ni d'<code>ARCHIVÉ</code> (<code>INV-53</code>).</p>
  <?php else: ?>
  <p class="ok">Tous les états courants figurent au vocabulaire de l'Article 22.</p>
  <?php endif; ?>

  <p class="note">
    Portefeuille dérivé du Registre initial des produits. Le service <strong>n'admet, ne qualifie et ne certifie aucun produit</strong> : ces actes appartiennent à l'autorité.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-011</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
