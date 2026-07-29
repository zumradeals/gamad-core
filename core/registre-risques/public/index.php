<?php

declare(strict_types=1);

/**
 * Registre des risques et exceptions — vue web EN LECTURE SEULE.
 *
 * Le service n'évalue, n'accepte et ne clôt aucun risque : la Loi 65 de
 * CORE-LAWS-0001 réserve l'acceptation à l'autorité compétente.
 *
 * Exécution locale : php -S 127.0.0.1:8087 -t core/registre-risques/public
 */

use Gamad\RegistreRisques\Ctr11;

require_once __DIR__ . '/../src/Ctr11.php';

$corpus = getenv('CORPUS_PATH');
if (!is_string($corpus) || $corpus === '') {
    $corpus = dirname(__DIR__, 3);
}

$ctr11 = new Ctr11($corpus);
$risques    = $ctr11->risques();
$exceptions = $ctr11->exceptions();
$ecarts     = $ctr11->ecarts();

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMAD Core — Risques et exceptions (CAP-CORE-017)</title>
<style>
  :root { color-scheme: light dark; --bg:#0f1115; --card:#171a21; --fg:#e7e9ee; --muted:#9aa2b1; --line:#262b36; --ok:#3fb950; --ko:#f85149; --warn:#d29922; }
  @media (prefers-color-scheme: light){ :root{ --bg:#f6f7f9; --card:#fff; --fg:#1b1f27; --muted:#5b6472; --line:#e4e7ec; } }
  * { box-sizing:border-box; } body{ margin:0; background:var(--bg); color:var(--fg); font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
  .wrap{ max-width:1060px; margin:0 auto; padding:32px 20px 64px; }
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
  .pill{ display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; border:1px solid var(--line); }
  .scroll{ overflow-x:auto; border:1px solid var(--line); border-radius:12px; }
  .note{ color:var(--muted); font-size:13px; margin-top:26px; border-top:1px solid var(--line); padding-top:16px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>GAMAD Core — Risques et exceptions</h1>
  <p class="sub">Capacité souveraine <code>CAP-CORE-017</code> · contrat <code>CTR-11</code>, partagé avec <code>CAP-CORE-018</code> · vue en lecture seule</p>

  <div class="grid">
    <div class="card"><div class="kpi"><?= (int) $ecarts['risques'] ?></div><small>risques inscrits</small></div>
    <div class="card"><div class="kpi warn"><?= count($ecarts['non_arbitres']) ?></div><small>niveaux non arbitrés</small></div>
    <div class="card"><div class="kpi ko"><?= count($ecarts['sans_echeance_ferme']) ?></div><small>sans échéance ferme</small></div>
    <div class="card"><div class="kpi ko"><?= count($ecarts['exceptions_ouvertes']) ?></div><small>exceptions ouvertes</small></div>
  </div>

  <h2>Risques inscrits</h2>
  <p class="sub">Le niveau relevé au tableau de l'Article 5 est <strong>proposé par un agent artificiel</strong>. Il ne devient arbitré que par un acte de l'autorité — la Loi 65 de <code>CORE-LAWS-0001</code> lui réserve l'acceptation du risque (<code>INV-58</code>).</p>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Risque</th><th>Proposé</th><th>Arbitré</th><th>Par</th><th>Réexamen</th></tr></thead>
    <tbody>
    <?php foreach ($risques as $reference => $r): ?>
      <tr>
        <td><code><?= e((string) $reference) ?></code></td>
        <td><?= e((string) $r['libelle']) ?></td>
        <td><?= e((string) $r['niveau_propose']) ?></td>
        <td class="<?= $r['niveau_arbitre'] !== null ? 'ok' : 'warn' ?>"><?= $r['niveau_arbitre'] !== null ? e((string) $r['niveau_arbitre']) : 'non arbitré' ?></td>
        <td><?= $r['arbitre_par'] !== null ? '<code>' . e((string) $r['arbitre_par']) . '</code>' : '<span class="muted">—</span>' ?></td>
        <td class="muted"><?= $r['reexamen'] !== null ? e((string) $r['reexamen']) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2 class="ko">Acceptations et exceptions sans échéance ferme</h2>
  <p class="sub">L'Article 52 range « échéance obligatoire » parmi les <strong>contrôles requis</strong> de cette capacité, et « exception permanente » parmi ses risques. Une acceptation dont le réexamen est suspendu à un événement incertain n'a pas d'échéance : elle a une <strong>condition</strong>.</p>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Espèce</th><th>Terme déclaré</th></tr></thead>
    <tbody>
    <?php foreach ($ecarts['sans_echeance_ferme'] as $s): ?>
      <tr><td><code><?= e((string) $s['reference']) ?></code></td><td><?= e((string) $s['espece']) ?></td><td class="warn"><?= e((string) $s['terme']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Le service <strong>nomme</strong> la différence ; il ne fixe aucun terme. Le fixer serait accepter le risque à la place de l'autorité.</p>

  <h2>Exceptions de sécurité</h2>
  <div class="scroll"><table>
    <thead><tr><th>Référence</th><th>Contrôle contourné</th><th>Compensations</th><th>Statut de sortie</th></tr></thead>
    <tbody>
    <?php foreach ($exceptions as $reference => $x): ?>
      <tr>
        <td><code><?= e((string) $reference) ?></code></td>
        <td><?= e((string) $x['contourne']) ?></td>
        <td class="<?= in_array($reference, $ecarts['sans_compensation_technique'], true) ? 'warn' : '' ?>"><?= e((string) $x['compensations']) ?></td>
        <td class="ko"><?= e((string) $x['sortie']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <h2>Ce que le corpus n'établit pas</h2>
  <p><span class="pill">méthode d'évaluation</span> <span class="pill">seuils</span> <span class="pill">fréquence de revue</span></p>
  <p class="sub">Restitués <strong><?= e(Ctr11::NON_ETABLI) ?></strong>. L'Article 52 les réserve à l'autorité.</p>

  <p class="note">
    Le service <strong>n'évalue, n'accepte et ne clôt aucun risque</strong> : la Loi 65 de <code>CORE-LAWS-0001</code> réserve l'acceptation à l'autorité compétente.
    Aucune écriture, aucune base. Cette page ne rend <code>CAP-CORE-017</code> ni admise, ni active, et ne constate pas <code>G0</code>.
  </p>
</div>
</body>
</html>
