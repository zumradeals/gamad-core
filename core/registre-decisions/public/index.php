<?php

declare(strict_types=1);

/**
 * Registre des décisions — vue web EN LECTURE SEULE.
 *
 * Restitue ce que le service CTR-05 dérive du corpus : l'inventaire des
 * décisions formelles confronté sur ses trois termes, les décisions demeurées
 * ouvertes et celles qu'un acte a closes, les statuts employés hors du
 * vocabulaire de l'Article 17, et les champs que le corpus n'établit pas.
 *
 * Le registre dérive des actes ; il n'en fonde aucun (INV-46). Aucune écriture,
 * aucune base : la page relève les fichiers du dépôt à chaque affichage.
 *
 * Exécution locale : php -S 127.0.0.1:8083 -t core/registre-decisions/public
 */

use Gamad\RegistreDecisions\Ctr05;

require_once __DIR__ . '/../src/Ctr05.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3); // core/registre-decisions/public -> racine du dépôt
}

$ctr05 = new Ctr05($corpus);

$ecarts     = $ctr05->ecarts();
$inventaire = $ecarts['inventaire'];
$inscrites  = $ctr05->inscrites();
$hors       = $ctr05->statutsHorsVocabulaire();

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
<title>GAMAD Core — Registre des décisions (CAP-CORE-008)</title>
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
  <h1>GAMAD Core — Registre des décisions</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-008</code> · contrat <code>CTR-05</code> (lecture et attestation) · vue en lecture seule</p>

  <div class="grid">
    <div class="card"><div class="kpi"><?= (int) $inventaire['actes'] ?></div><small>décisions formelles au dépôt</small></div>
    <div class="card"><div class="kpi"><?= (int) $inventaire['index'] ?></div><small>inscrites à l'index (Article 4)</small></div>
    <div class="card"><div class="kpi warn"><?= (int) $inventaire['consolide'] ?></div><small>au tableau consolidé (Article 92)</small></div>
    <div class="card"><div class="kpi warn"><?= count($inventaire['absents_consolide']) ?></div><small>hors du tableau consolidé</small></div>
    <div class="card"><div class="kpi ko"><?= (int) $ecarts['ouvertes'] ?></div><small>décisions demeurées ouvertes</small></div>
    <div class="card"><div class="kpi ok"><?= (int) $ecarts['closes'] ?></div><small>closes par un acte</small></div>
  </div>

  <h2>Décisions réservées à l'autorité</h2>
  <p class="sub">Relevées d'une <strong>forme dérivable</strong> (Article 153), jamais cherchées dans la prose. Une décision ne se clôt que par une déclaration qui la nomme (<code>INV-47</code>, Article 154) : ni le silence, ni l'ancienneté, ni l'exécution d'un acte voisin ne closent quoi que ce soit.</p>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Objet</th><th>Source</th><th>État</th></tr></thead>
    <tbody>
    <?php foreach ($inscrites as $reference => $d): ?>
      <tr>
        <td><code><?= e((string) $reference) ?></code></td>
        <td><?= e((string) $d['objet']) ?></td>
        <td class="muted"><?= e((string) $d['source']) ?></td>
        <td><?= $d['close_par'] === null
              ? '<span class="ko">ouverte</span>'
              : '<span class="ok">close</span> <span class="muted">par ' . e((string) $d['close_par']) . '</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Cette inscription <strong>n'est pas déclarée complète</strong> : aucun texte n'établit l'ensemble des décisions ouvertes du corpus, et le service ne peut pas découvrir celles qui n'y figurent pas. La limite est elle-même inscrite comme <code>DECISION-0025</code>.</p>

  <h2>Inventaire confronté, non réconcilié</h2>
  <p class="sub">Trois sources, trois décomptes. Le service les <strong>confronte</strong> (<code>INV-48</code>) : aligner le tableau consolidé sur l'index ferait disparaître un écart que l'Article 133 pose en question à l'autorité.</p>
  <div class="scroll"><table>
    <thead><tr><th>Source</th><th>Décompte</th><th>Nature</th></tr></thead>
    <tbody>
      <tr><td>Actes présents au dépôt</td><td><?= (int) $inventaire['actes'] ?> références · <?= (int) $inventaire['fichiers'] ?> fichiers</td><td class="muted">l'existence</td></tr>
      <tr><td>Index consolidé — Article 4</td><td><?= (int) $inventaire['index'] ?></td><td class="muted">la table tenue à jour</td></tr>
      <tr><td>Tableau consolidé — Article 92</td><td><?= (int) $inventaire['consolide'] ?></td><td class="muted">la vue arrêtée, jamais prolongée</td></tr>
    </tbody>
  </table></div>
  <?php if ($inventaire['absents_index'] !== [] || $inventaire['absents_disque'] !== []): ?>
  <p class="ko">Écart entre le disque et l'index : <?= e(implode(' · ', array_merge($inventaire['absents_index'], $inventaire['absents_disque']))) ?></p>
  <?php else: ?>
  <p class="ok">Disque et index concordent : chaque acte présent est inscrit, chaque inscription a son acte.</p>
  <?php endif; ?>
  <p class="warn"><?= count($inventaire['absents_consolide']) ?> décision(s) inscrite(s) à l'index et absente(s) du tableau consolidé, de <code><?= e((string) ($inventaire['absents_consolide'][0] ?? '—')) ?></code> à <code><?= e((string) (end($inventaire['absents_consolide']) ?: '—')) ?></code>.</p>

  <h2>Statuts employés hors du vocabulaire de l'Article 17</h2>
  <?php if ($hors !== []): ?>
  <div class="scroll"><table>
    <thead><tr><th>Statut employé</th><th>Occurrences</th><th>Terme voisin du vocabulaire</th></tr></thead>
    <tbody>
    <?php foreach ($hors as $statut => $refs): ?>
      <tr>
        <td><code><?= e((string) $statut) ?></code></td>
        <td><?= count($refs) ?></td>
        <td class="muted">non traduit — <?= e(Ctr05::NON_ETABLI) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Ces statuts sont <strong>nommés, jamais traduits</strong> (<code>INV-49</code>). « LU ET ADOPTÉ — EN VIGUEUR » ressemble à <code>ADOPTÉE</code> suivi de <code>EN VIGUEUR</code> : c'est cette ressemblance qui est le piège. Traduire ferait dire au corpus ce qu'il n'a pas écrit, et l'écart cesserait d'être visible.</p>
  <?php else: ?>
  <p class="ok">Tous les statuts employés figurent au vocabulaire de l'Article 17.</p>
  <?php endif; ?>

  <h2>Champs que l'Article 27 exige et que le corpus n'établit pas</h2>
  <p><?php foreach ($ecarts['champs_non_etablis'] as $c): ?><span class="pill"><?= e((string) $c) ?></span> <?php endforeach; ?></p>
  <p class="sub">La classe fait exception pour les dix-sept adoptions que le tableau de l'Article 92 classe : elle est <strong>dérivée</strong> pour celles-là et <strong><?= e(Ctr05::NON_ETABLI) ?></strong> pour les autres. Aucune n'est étendue par ressemblance d'objet (<code>INV-50</code>) — l'Article 132 réserve la classification à l'autorité.</p>

  <p class="note">
    Registre dérivé des actes, jamais autoritatif (<code>INV-46</code>) : décider est l'acte de l'autorité ; cette page en tient l'inventaire et nomme ce qui manque.
    Les inventaires sont confrontés et non réconciliés (<code>INV-48</code>) ; les statuts hors vocabulaire sont nommés et non traduits (<code>INV-49</code>).
    Aucune écriture, aucune base : les fichiers du dépôt sont relevés à chaque affichage.
    Cette page ne rend <code>CAP-CORE-008</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
