<?php

declare(strict_types=1);

/**
 * Catalogue des contrats communs — vue web EN LECTURE SEULE.
 *
 * Restitue ce que le service CTR-06 dérive de l'Atlas et du code : les seize
 * familles de contrat, leur domaine gardien, leurs titulaires déclarés, le
 * module qui les sert, ceux qui les consomment, et les quatre écarts nommés
 * par l'Article 10 de la conception.
 *
 * Le catalogue dérive, il ne crée aucun contrat (INV-42). Aucune écriture,
 * aucune base : la page relève les fichiers du dépôt à chaque affichage. Ce
 * qu'elle montre n'a pas plus d'autorité que les textes dont elle procède.
 *
 * Exécution locale : php -S 127.0.0.1:8082 -t core/registre-contrats/public
 */

use Gamad\RegistreAnnuaire\Ctr14;
use Gamad\RegistreContrats\Ctr06;

require_once dirname(__DIR__, 2) . '/registre-annuaire/src/Ctr14.php';
require_once __DIR__ . '/../src/Ctr06.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3); // core/registre-contrats/public -> racine du dépôt
}

$ctr06 = new Ctr06($corpus, new Ctr14($corpus));

$catalogue    = $ctr06->catalogue();
$ecarts       = $ctr06->ecarts();
$dependances  = $ctr06->dependances();
$malgreGardien = $ctr06->sansTitulaireMalgreGardien();

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
<title>GAMAD Core — Catalogue des contrats (CAP-CORE-009)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --warn:#d29922; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1180px; margin:0 auto; padding:32px 20px 64px; }
  h1{ font-size:22px; margin:0 0 4px; } .sub{ color:var(--muted); margin:0 0 28px; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:28px; }
  .card{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
  .kpi{ font-size:30px; font-weight:650; }
  .card small{ display:block; color:var(--muted); font-size:13px; }
  .ok{ color:var(--ok); } .ko{ color:var(--ko); } .warn{ color:var(--warn); }
  h2{ font-size:15px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin:32px 0 12px; }
  table{ width:100%; border-collapse:collapse; font-size:13.5px; }
  th,td{ text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
  th{ color:var(--muted); font-weight:600; }
  code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12.5px; }
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
  .scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
  .muted{ color:var(--muted); }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Catalogue des contrats communs</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-009</code> · contrat <code>CTR-06</code> (lecture et attestation) · vue en lecture seule</p>

  <div class="grid">
    <div class="card"><div class="kpi"><?= (int) $ecarts['familles'] ?></div><small>familles définies par l'Atlas</small></div>
    <div class="card"><div class="kpi"><?= (int) $ecarts['familles_servies'] ?></div><small>familles servies par un module</small></div>
    <div class="card"><div class="kpi warn"><?= count($ecarts['sans_producteur']) ?></div><small>sans producteur</small></div>
    <div class="card"><div class="kpi warn"><?= count($ecarts['sans_titulaire']) ?></div><small>sans capacité titulaire</small></div>
    <div class="card"><div class="kpi"><?= (int) $ecarts['dependances'] ?></div><small>dépendances observées</small></div>
    <div class="card"><div class="kpi ko"><?= count($ecarts['dependances_non_declarees']) ?></div><small>dépendances non déclarées</small></div>
  </div>

  <h2>Les seize familles de contrat</h2>
  <p class="sub">Le titulaire est <strong>déclaré</strong> par le Registre des capacités ; le producteur est <strong>observé</strong> sur le disque, par la capacité que le module déclare servir (<code>INV-41</code>). Aucun rattachement par ressemblance (<code>INV-43</code>).</p>
  <div class="scroll"><table>
    <thead><tr>
      <th>Famille</th><th>Libellé</th><th>Domaine gardien</th><th>Titulaires déclarés</th>
      <th>Producteur observé</th><th>Consommateurs observés</th>
    </tr></thead>
    <tbody>
    <?php foreach ($catalogue as $reference => $c): ?>
      <tr>
        <td><code><?= e($reference) ?></code></td>
        <td><?= e((string) $c['libelle']) ?></td>
        <td><?= e((string) $c['gardien']) ?></td>
        <td><?= $c['titulaires'] !== [] ? e(implode(' · ', $c['titulaires'])) : '<span class="warn">sans titulaire</span>' ?></td>
        <td><?= $c['producteur'] !== null
              ? '<code>' . e((string) $c['producteur']['module']) . '</code> <span class="muted">(' . e((string) $c['producteur']['capacite']) . ')</span>'
              : '<span class="warn">sans producteur</span>' ?></td>
        <td><?= $c['consommateurs'] !== []
              ? implode(' ', array_map(static fn (string $x) => '<code>' . e($x) . '</code>', $c['consommateurs']))
              : '<span class="muted">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Dépendances entre contrats, relevées dans le code</h2>
  <p class="sub">Un module qui importe la classe de contrat d'un autre en dépend, que le corpus le dise ou non. Le relevé lit les imports ; il ne consulte aucune déclaration (<code>INV-44</code>).</p>
  <?php if ($dependances === []): ?>
  <p class="muted">Aucune dépendance entre contrats observée.</p>
  <?php else: ?>
  <div class="scroll"><table>
    <thead><tr><th>Consommateur</th><th>Module</th><th>Contrat consommé</th><th>Module produit</th><th>Déclarée par un texte adopté</th></tr></thead>
    <tbody>
    <?php foreach ($dependances as $d): ?>
      <tr>
        <td><code><?= e($d['consommateur']) ?></code></td>
        <td><code><?= e($d['module_consommateur']) ?></code></td>
        <td><code><?= e($d['produit']) ?></code></td>
        <td><code><?= e($d['module_produit']) ?></code></td>
        <td class="<?= $d['declaree'] === 'oui' ? 'ok' : 'ko' ?>"><?= $d['declaree'] === 'oui' ? 'oui' : 'non' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Ces constats sont <strong>nommés et non corrigés</strong> (<code>INV-38</code>). Tant que le registre initial des contrats n'est pas adopté, aucune dépendance ne peut être déclarée.</p>
  <?php endif; ?>

  <?php if ($malgreGardien !== []): ?>
  <h2 class="warn">Familles sans titulaire dont une capacité garde pourtant le domaine</h2>
  <p class="sub">Toutes les vacances ne se valent pas. Celles dont aucune capacité ne garde le domaine sont structurelles et prévues ; celles-ci ne le sont pas.</p>
  <div class="scroll"><table>
    <thead><tr><th>Famille</th><th>Domaine gardien</th><th>Capacités gardant ce domaine</th></tr></thead>
    <tbody>
    <?php foreach ($malgreGardien as $famille => $caps): ?>
      <tr>
        <td><code><?= e((string) $famille) ?></code></td>
        <td><?= e((string) ($catalogue[$famille]['gardien'] ?? '—')) ?></td>
        <td><?= e(implode(' · ', $caps)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Le service <strong>nomme</strong> l'écart ; il n'attribue aucune famille (<code>INV-38</code>, <code>INV-42</code>). L'attribution appartient à l'autorité.</p>
  <?php endif; ?>

  <h2>Champs que le corpus n'établit pour aucun contrat</h2>
  <?php if ($ecarts['champs_non_etablis'] !== []): ?>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e($c) ?></span> <?php endforeach; ?></p>
  <p class="sub">Restitués <strong><?= e(Ctr06::NON_ETABLI) ?></strong>, jamais comblés par une valeur plausible (<code>INV-45</code>). Une version inventée serait une promesse de compatibilité que personne n'a faite. Le registre initial des contrats, qui les établirait, n'est pas adopté&nbsp;: <code><?= $ecarts['registre_initial_adopte'] ? 'présent' : 'absent' ?></code>.</p>
  <?php else: ?>
  <p class="ok">Aucun — les quatre champs sont établis par le registre initial des contrats.</p>
  <?php endif; ?>

  <p class="note">
    Catalogue dérivé de l'Atlas et du code, jamais autoritatif (<code>INV-42</code>) : cette page décrit, elle ne crée aucun contrat, n'en approuve et n'en déprécie aucun.
    Le relevé des familles est emprunté au service <code>CTR-14</code> plutôt que dupliqué — deux analyseurs du même tableau finiraient par diverger.
    Aucune écriture, aucune base : les fichiers du dépôt sont relevés à chaque affichage.
    Cette page ne rend <code>CAP-CORE-009</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
