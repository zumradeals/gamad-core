<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-004 — refus par défaut et opposabilité des
 * limites.
 *
 * Depuis CAP-CORE-007, `CTR-03` ne lit plus `politique`/`regle` dans l'index
 * documentaire : il lit le magasin persistant et gouverné de
 * `core/registre-politiques`. Cette garde bootstrappe donc les politiques
 * réelles (Article 48, Article 49, inscription des identités) dans ce magasin
 * — exactement comme `core:politiques:bootstrap` le fait en production — avant
 * d'éprouver le moteur de décision.
 *
 * Ce que le test éprouve :
 *   · une action inconnue est REFUSÉE (INV-27) — l'absence de règle n'est
 *     jamais une permission ;
 *   · une compétence de l'Article 48 est PERMISE ;
 *   · une limite de l'Article 49 est REFUSÉE AU TITULAIRE DU MANDAT LUI-MÊME
 *     (INV-30) — c'est l'assertion centrale de cette capacité ;
 *   · toute décision porte un motif non vide (INV-28) ;
 *   · les règles sont des données du registre persistant, non du code (INV-29) ;
 *   · seule une version ACTIVE de politique permet quoi que ce soit ;
 *   · le rapprochement lexical par sous-chaîne n'est plus exercé.
 *
 * Exécution : php core/registre-autorisation/tests/autorisation_p3.php
 */

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../../registre-politiques/src/PolitiqueAdministration.php';
require __DIR__ . '/../../registre-politiques/src/SchemaPolitiques.php';
require __DIR__ . '/../../registre-politiques/src/Magasin.php';
require __DIR__ . '/../../registre-politiques/src/RegistrePolitiques.php';
require __DIR__ . '/../src/Ctr03.php';

$pid = getmypid();
$fichiers = [
    'index' => sys_get_temp_dir() . "/regn-autz-p3-index-{$pid}.sqlite",
    'identites' => sys_get_temp_dir() . "/regn-autz-p3-identites-{$pid}.sqlite",
    'politiques' => sys_get_temp_dir() . "/regn-autz-p3-politiques-{$pid}.sqlite",
];
foreach ($fichiers as $f) {
    @unlink($f);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $f) {
        @unlink($f);
    }
});

putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichiers['index']);
$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);

$identitesReg = IdentiteMagasin::connecter($fichiers['identites']);
$ctr01 = new Ctr01($index, $identitesReg);
$polMagasin = PolitiquesMagasin::connecter($fichiers['politiques']);
$registre = new RegistrePolitiques($index, $identitesReg, $polMagasin, $ctr01);

$titulaire = 'AUT-GAMAD-001';
$politiqueAdmin = PolitiqueAdministration::POLITIQUE;
$source = 'garde CAP-CORE-004';
$g = static fn (): array => [
    'politique' => $politiqueAdmin, 'producteur' => $titulaire, 'source' => $source,
    'preuve' => 'P-' . bin2hex(random_bytes(4)),
];

// Bootstrap fidèle des politiques réelles — même photographie figée et même
// mécanisme que `core:politiques:bootstrap`, condensé pour cette garde
// autonome. Ne lit plus l'index : `politique`/`regle` n'y vivent plus.
$bootstrap = json_decode(
    file_get_contents(__DIR__ . '/../../registre-politiques/resources/bootstrap-politiques-v1.json'),
    true,
);
$politiques = $bootstrap['politiques'];
foreach ($politiques as $p) {
    $reference = $p['reference'];
    $version = $p['version'];
    $sourceRef = $p['source'] . (!empty($p['adoption_reference']) ? ' (' . $p['adoption_reference'] . ')' : '');
    $regles = $p['regles'];

    $registre->inscrirePolitique(array_merge($g(), [
        'reference' => $reference, 'libelle' => $p['libelle'],
        'proprietaire_reference' => $titulaire, 'source_reference' => $sourceRef,
    ]));
    $registre->creerVersion($reference, array_merge($g(), ['version' => $version]));
    $cas = [];
    foreach ($regles as $r) {
        $registre->ajouterRegle($reference, $version, array_merge($g(), [
            'effet' => $r['effet'], 'action_reference' => $r['action'],
            'sujet_reference' => $r['sujet_type'], 'motif' => $r['motif'],
        ]));
        $cas[] = ['sujet' => $r['sujet_type'] ?? $titulaire, 'action' => $r['action'], 'attendu' => $r['effet'] === 'PERMET' ? 'PERMIS' : 'REFUSE'];
    }
    $registre->soumettreVersion($reference, $version, $g());
    $registre->simulerVersion($reference, $version, array_merge($g(), ['jeu_reference' => 'GARDE', 'cas' => $cas]));
    $registre->activerVersion($reference, $version, $g());
}

$ctr03 = new Ctr03($polMagasin);

$echecs = 0;
$verifier = function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "GARDE — REFUS PAR DÉFAUT ET OPPOSABILITÉ DES LIMITES (CAP-CORE-004)\n\n";

// --- INV-27 : une action qu'aucune politique ne prévoit est refusée.
$inconnue = $ctr03->autoriser($titulaire, 'déployer une application en production');
$verifier(
    $inconnue['decision'] === 'REFUSÉ' && $inconnue['politique'] === null,
    "une action inconnue est REFUSÉE par défaut (INV-27)",
);

// --- Une compétence de l'Article 48 est permise.
$competence = $ctr03->autoriser($titulaire, 'proposition des textes Genesis II');
$verifier(
    $competence['decision'] === 'PERMIS' && $competence['politique'] === 'POL-MANDAT-COMPETENCES',
    "une compétence de l'Article 48 est PERMISE",
);

// --- La politique d'inscription permet précisément l'autorité désignée.
$inscription = $ctr03->autoriser($titulaire, 'inscrire une identité', 'personne');
$verifier(
    $inscription['decision'] === 'PERMIS'
        && $inscription['politique'] === 'POL-INSCRIPTION-IDENTITES-V1'
        && str_contains((string) $inscription['source'], 'POLITIQUE-INSCRIPTION-IDENTITES-0001'),
    "l'autorité désignée peut inscrire une identité sous la politique adoptée",
);
$agentSansDroit = $ctr03->autoriser('AGENT-IA-002', 'inscrire une identité', 'personne');
$verifier(
    $agentSansDroit['decision'] === 'REFUSÉ' && $agentSansDroit['politique'] === null,
    "la politique d'inscription ne donne aucun droit à l'agent qui l'implémente",
);

// --- INV-30 : une limite de l'Article 49 est refusée AU TITULAIRE LUI-MÊME.
$limites = [
    'falsifier une source ou l’histoire',
    'effacer injustement une preuve',
    'convertir le Core en propriété personnelle',
    'prononcer G0 sans l’acte distinct et les contrôles requis',
];
foreach ($limites as $limite) {
    $d = $ctr03->autoriser($titulaire, $limite);
    $verifier(
        $d['decision'] === 'REFUSÉ' && $d['politique'] === 'POL-MANDAT-LIMITES',
        "REFUSÉ au titulaire du mandat : « {$limite} » (INV-30)",
    );
}

// --- INV-28 : toute décision porte un motif.
$sansMotif = 0;
foreach ([$inconnue, $competence] as $d) {
    if (trim((string) ($d['motif'] ?? '')) === '') {
        $sansMotif++;
    }
}
$verifier($sansMotif === 0, 'toute décision porte un motif non vide (INV-28)');

// --- INV-29 : les règles viennent du registre persistant, avec leur source citée.
$interdits = $ctr03->resoudreInterdits();
$verifier(
    count($interdits) >= 7 && str_contains((string) ($interdits[0]['source'] ?? ''), 'Art. 49'),
    sprintf('%d interdits dérivés du registre persistant, source citée (INV-29)', count($interdits)),
);

// --- Les interdits s'opposent à tout sujet, non à une catégorie.
$opposableATous = true;
foreach ($interdits as $i) {
    if (!str_contains((string) $i['opposable_a'], 'tout sujet')) {
        $opposableATous = false;
    }
}
$verifier($opposableATous, 'les interdits sont opposables à tout sujet, titulaire compris');

// --- Le rapprochement par sous-chaîne n'est plus exercé : une sous-chaîne ou
// un mot isolé d'une action réelle ne doit plus jamais correspondre.
$sousChaine1 = $ctr03->autoriser($titulaire, 'proposition');
$sousChaine2 = $ctr03->autoriser($titulaire, 'identité');
$verifier(
    $sousChaine1['decision'] === 'REFUSÉ' && $sousChaine2['decision'] === 'REFUSÉ',
    'une sous-chaîne d’une action réelle ne correspond plus par approximation',
);

// --- Seule une version ACTIVE permet : suspendre POL-MANDAT-COMPETENCES ferme
// immédiatement la compétence de l'Article 48, sans y toucher.
$verComp = $registre->resoudreVersionActive('POL-MANDAT-COMPETENCES');
$registre->suspendreVersion('POL-MANDAT-COMPETENCES', $verComp['version'], $g());
$apresSuspension = $ctr03->autoriser($titulaire, 'proposition des textes Genesis II');
$verifier(
    $apresSuspension['decision'] === 'REFUSÉ',
    'suspendre la version active ferme immédiatement la permission qu’elle portait',
);
$registre->activerVersion('POL-MANDAT-COMPETENCES', $verComp['version'], $g());

// --- Contre-épreuve : sans règle, tout est refusé, jamais permis.
$polMagasin->exec('DELETE FROM regle_politique');
$apres = $ctr03->autoriser($titulaire, 'inscrire une identité', 'personne');
$verifier(
    $apres['decision'] === 'REFUSÉ' && $apres['politique'] === null,
    'un registre sans règle refuse tout : l’absence n’est jamais une permission',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-004 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-004 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
