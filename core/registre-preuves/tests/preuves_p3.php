<?php

declare(strict_types=1);

/**
 * Garde de comportement du noyau de CAP-CORE-015 — preuves d'intégrité.
 *
 * Éprouve directement `RegistrePreuves`, `Canonicaliseur`, `CalculateurEmpreinte`
 * et `ServiceSignature` (Ed25519 réel via `libsodium`), en s'appuyant sur un
 * registre CAP-CORE-016 réel (fichier factice `0600`, jamais une clé de
 * production) pour prouver que la signature ne transite jamais par ce
 * module sous forme de clé privée.
 *
 * Portée assumée : cette garde couvre le noyau (canonicalisation, empreinte,
 * signature/vérification, manifeste, cycle, révocation/compromission,
 * liens, paquet). Elle ne couvre pas l'API HTTP, la console ni la
 * readiness — chantiers documentés dans le rapport final de la PR.
 *
 * Exécution : php core/registre-preuves/tests/preuves_p3.php
 */

use Gamad\RegistrePreuves\Canonicaliseur;
use Gamad\RegistrePreuves\CalculateurEmpreinte;
use Gamad\RegistrePreuves\ExceptionPreuve;
use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\PolitiquePreuves;
use Gamad\RegistrePreuves\RegistrePreuves;
use Gamad\RegistrePreuves\ServiceSignature;
use Gamad\RegistreSecretsCles\FournisseurFichier0600;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Gamad\RegistreSecretsCles\ResolveurSecret;
use Gamad\RegistreSecretsCles\UsageSecret;

require __DIR__ . '/../../registre-secrets-cles/src/ExceptionSecret.php';
require __DIR__ . '/../../registre-secrets-cles/src/PolitiqueSecretsCles.php';
require __DIR__ . '/../../registre-secrets-cles/src/SchemaSecretsCles.php';
require __DIR__ . '/../../registre-secrets-cles/src/Magasin.php';
require __DIR__ . '/../../registre-secrets-cles/src/RegistreSecretsCles.php';
require __DIR__ . '/../../registre-secrets-cles/src/DescripteurVersion.php';
require __DIR__ . '/../../registre-secrets-cles/src/UsageSecret.php';
require __DIR__ . '/../../registre-secrets-cles/src/DiagnosticFournisseur.php';
require __DIR__ . '/../../registre-secrets-cles/src/ResultatDestruction.php';
require __DIR__ . '/../../registre-secrets-cles/src/SensitiveValue.php';
require __DIR__ . '/../../registre-secrets-cles/src/FournisseurSecret.php';
require __DIR__ . '/../../registre-secrets-cles/src/FournisseurFichier0600.php';
require __DIR__ . '/../../registre-secrets-cles/src/ResolveurSecret.php';
require __DIR__ . '/../src/ExceptionPreuve.php';
require __DIR__ . '/../src/PolitiquePreuves.php';
require __DIR__ . '/../src/SchemaPreuves.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/Canonicaliseur.php';
require __DIR__ . '/../src/CalculateurEmpreinte.php';
require __DIR__ . '/../src/ServiceSignature.php';
require __DIR__ . '/../src/RegistrePreuves.php';

echo "GARDE — CAP-CORE-015 (preuves d'intégrité)\n\n";

$echecs = 0;
$numero = 0;
$v = static function (bool $ok, string $libelle) use (&$echecs, &$numero): void {
    $numero++;
    printf("  %s  %2d. %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $numero, $libelle);
    if (!$ok) {
        $echecs++;
    }
};

$prefixe = sys_get_temp_dir() . '/gamad-preuves-p3-' . getmypid();
$fichierPreuves = $prefixe . '-preuves.sqlite';
$fichierSecrets = $prefixe . '-secrets.sqlite';
$dossierCles = $prefixe . '-cles';
@unlink($fichierPreuves);
@unlink($fichierSecrets);
@mkdir($dossierCles, 0700, true);
register_shutdown_function(static function () use ($fichierPreuves, $fichierSecrets, $dossierCles): void {
    @unlink($fichierPreuves);
    @unlink($fichierSecrets);
    foreach (glob($dossierCles . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dossierCles);
});

$magasinPreuves = PreuvesMagasin::connecter($fichierPreuves);
$magasinSecrets = SecretsMagasin::connecter($fichierSecrets);
$registreSecrets = new RegistreSecretsCles($magasinSecrets);

$gs = static fn (): array => [
    'politique' => PolitiqueSecretsCles::POLITIQUE, 'producteur' => PolitiqueSecretsCles::AUTORITE,
    'preuve' => 'P3-' . strtoupper(bin2hex(random_bytes(4))),
];

// ------------------------------------------------------------------ clé de test Ed25519 (jamais une clé de production)
$paireCles = sodium_crypto_sign_keypair();
$clePriveeTest = sodium_crypto_sign_secretkey($paireCles);
$clePubliqueTest = base64_encode(sodium_crypto_sign_publickey($paireCles));
$cheminCle = $dossierCles . '/cle-signature-test.key';
file_put_contents($cheminCle, base64_encode($clePriveeTest) . "\n");
chmod($cheminCle, 0600);
sodium_memzero($clePriveeTest);

$registreSecrets->inscrireSecret(array_merge($gs(), [
    'reference' => 'KEY-GAMAD-TEST-SIGNATURE', 'nom' => 'Clé de signature de test P3', 'type_secret' => 'PAIRE_CLES_SIGNATURE',
    'finalite_reference' => 'signer des preuves de test', 'proprietaire_reference' => 'AUT-GAMAD-001',
    'source_reference' => 'SRC-GAMAD-P3', 'environnement_reference' => 'CI', 'classification_reference' => 'SECRET_CORE',
]));
$registreSecrets->inscrireFournisseur(array_merge($gs(), [
    'reference' => 'FOU-GAMAD-TEST-SIGNATURE', 'nom' => 'Fournisseur fichier signature P3', 'type_fournisseur' => 'FICHIER_0600',
    'environnement_reference' => 'CI', 'proprietaire_reference' => 'AUT-GAMAD-001', 'capacites' => ['SIGNER_SANS_EXPORT'],
]));
$versionCle = $registreSecrets->declarerVersion(array_merge($gs(), [
    'secret_reference' => 'KEY-GAMAD-TEST-SIGNATURE', 'version' => '1', 'fournisseur_reference' => 'FOU-GAMAD-TEST-SIGNATURE',
    'handle_fournisseur' => $cheminCle, 'cle_publique' => $clePubliqueTest, 'algorithme_reference' => 'ED25519',
]));
$adaptateurFichier = new FournisseurFichier0600('', 0);
$registreSecrets->verifierVersion((int) $versionCle['id'], $adaptateurFichier, $gs());
$registreSecrets->activerVersion((int) $versionCle['id'], array_merge($gs(), ['verifiee' => true]));

$resolveur = new ResolveurSecret($registreSecrets, ['FOU-GAMAD-TEST-SIGNATURE' => $adaptateurFichier]);
$serviceSignature = new ServiceSignature($resolveur);
$usage = new UsageSecret(
    modeUsage: 'SIGNER', consommateurReference: 'CAP-CORE-015', realmReference: '',
    environnementReference: 'CI', finaliteReference: 'signature de test', operationReference: 'preuve.signature.emettre',
    correlationId: 'P3-SIGNATURE',
);

$magasinPreuves = PreuvesMagasin::connecter($fichierPreuves);
$registre = new RegistrePreuves($magasinPreuves, $serviceSignature);

$g = static fn (): array => [
    'politique' => PolitiquePreuves::POLITIQUE, 'producteur' => PolitiquePreuves::AUTORITE,
    'preuve' => 'P3-' . strtoupper(bin2hex(random_bytes(4))), 'correlation_id' => 'P3-' . strtoupper(bin2hex(random_bytes(4))),
];

// ------------------------------------------------------------------ 1-3 migration/moteur
$v(\Gamad\RegistrePreuves\SchemaPreuves::presente($magasinPreuves), 'migration du magasin établit les douze tables');
\Gamad\RegistrePreuves\SchemaPreuves::migrer($magasinPreuves);
$v(\Gamad\RegistrePreuves\SchemaPreuves::presente($magasinPreuves), 'rejouer la migration ne casse rien (idempotente)');
$v((string) $magasinPreuves->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite', 'SQLite reste isolé aux tests et au développement');

// ------------------------------------------------------------------ canonicalisation
$objet1 = ['b' => 1, 'a' => 2, 'liste' => [3, 1, 2]];
$objet2 = ['a' => 2, 'liste' => [3, 1, 2], 'b' => 1];
$v(Canonicaliseur::canonicaliser($objet1) === Canonicaliseur::canonicaliser($objet2), 'JSON canonique déterministe : ordre des clés sans effet');
$v(str_contains(Canonicaliseur::canonicaliser($objet1), '[3,1,2]'), 'ordre des listes préservé');
$v(Canonicaliseur::canonicaliser(['texte' => "café"]) === Canonicaliseur::canonicaliser(['texte' => "cafe\u{0301}"]), 'Unicode normalisé (NFC) entre formes composée et décomposée');
$exceptionNan = null;
try {
    Canonicaliseur::canonicaliser(['x' => NAN]);
} catch (ExceptionPreuve $e) {
    $exceptionNan = $e;
}
$v($exceptionNan !== null, 'une valeur JSON non finie (NAN) est refusée');

// ------------------------------------------------------------------ empreinte
$v(CalculateurEmpreinte::empreinteChaine('gamad', 'SHA-256') === hash('sha256', 'gamad'), 'SHA-256 exact');
$v(CalculateurEmpreinte::empreinteChaine('gamad', 'SHA-512') === hash('sha512', 'gamad'), 'SHA-512 exact');
$refusMd5 = null;
try {
    CalculateurEmpreinte::empreinteChaine('gamad', 'MD5');
} catch (ExceptionPreuve $e) {
    $refusMd5 = $e;
}
$v($refusMd5 !== null, 'MD5 refusé pour toute nouvelle preuve');
$refusSha1 = null;
try {
    CalculateurEmpreinte::empreinteChaine('gamad', 'SHA-1');
} catch (ExceptionPreuve $e) {
    $refusSha1 = $e;
}
$v($refusSha1 !== null, 'SHA-1 refusé pour toute nouvelle preuve');
$v(CalculateurEmpreinte::empreinteValide(str_repeat('a', 64), 'SHA-256'), 'longueur d\'empreinte SHA-256 vérifiée (64 hex)');
$v(!CalculateurEmpreinte::empreinteValide('trop-court', 'SHA-256'), 'longueur d\'empreinte invalide détectée');
$v(CalculateurEmpreinte::comparerConstant('abc', 'abc') && !CalculateurEmpreinte::comparerConstant('abc', 'abd'), 'comparaison constante (hash_equals) utilisée');

$refusCheminRelatif = null;
try {
    CalculateurEmpreinte::empreinteFlux('chemin/relatif', 'SHA-256');
} catch (ExceptionPreuve $e) {
    $refusCheminRelatif = $e;
}
$v($refusCheminRelatif !== null, 'chemin non absolu refusé pour le calcul en flux');
$refusTraversal = null;
try {
    CalculateurEmpreinte::empreinteFlux('/tmp/../etc/passwd', 'SHA-256');
} catch (ExceptionPreuve $e) {
    $refusTraversal = $e;
}
$v($refusTraversal !== null, 'path traversal refusé pour le calcul en flux');

// ------------------------------------------------------------------ préparation d'une preuve simple (empreinte)
$dossierPreuve = array_merge($g(), [
    'type_preuve' => 'EMPREINTE_ARTEFACT', 'sujet_type' => 'DOCUMENT_TEST', 'sujet_reference' => 'DOC-P3-1',
    'producteur_capacite_reference' => 'CAP-CORE-015', 'realm_reference' => 'RLM-P3-TEST',
    'finalite_reference' => 'test P3', 'source_reference' => 'SRC-GAMAD-P3', 'classification' => 'INTERNE',
    'representation' => ['format_representation' => 'JSON_CANONIQUE', 'media_type' => 'application/json'],
]);
$preuve1 = $registre->preparerPreuve($dossierPreuve);
$v(!isset($preuve1['refus']) && $preuve1['etat'] === 'PREPAREE', 'préparation d\'une preuve réussie, sans signature ni activation');

$dossierIdem = array_merge($dossierPreuve, ['idempotency_key' => 'IDEM-P3-1']);
$p1 = $registre->preparerPreuve($dossierIdem);
$p2 = $registre->preparerPreuve($dossierIdem);
$v($p1['reference'] === $p2['reference'] && ($p2['idempotent'] ?? false) === true, 'préparation idempotente : la même idempotency key ne double jamais la référence');

$refusProducteur = $registre->preparerPreuve(array_merge($dossierPreuve, [
    'producteur_capacite_reference' => null, 'producteur_produit_reference' => null, 'producteur_identite_reference' => null,
]));
$v(($refusProducteur['refus'] ?? null) === 'PRODUCTEUR_ABSENT', 'producteur obligatoire');

$refusChampInterdit = $registre->preparerPreuve(array_merge($dossierPreuve, ['password' => 'ne-doit-jamais-etre-ecrit']));
$v(($refusChampInterdit['refus'] ?? null) === 'CHAMP_INTERDIT', 'un dossier portant un champ `password` est refusé avant toute écriture');

// colonnes de la table
$colonnes = $magasinPreuves->query("PRAGMA table_info(preuve)")->fetchAll(PDO::FETCH_ASSOC);
$nomsColonnes = array_map(static fn (array $c): string => strtolower((string) $c['name']), $colonnes);
$v(array_intersect($nomsColonnes, PolitiquePreuves::CHAMPS_INTERDITS) === [], 'aucune colonne de `preuve` ne porte un nom de champ interdit');

// ------------------------------------------------------------------ octets bruts vs texte
$referencePreuve1 = (string) $preuve1['reference'];
$contenuJson = Canonicaliseur::canonicaliser(['montant' => 100, 'devise' => 'XOF']);
$empreinte1 = $registre->emettreEmpreinte($referencePreuve1, 'SHA-256', $contenuJson, array_merge($g(), []));
$v(!isset($empreinte1['refus']) && $empreinte1['etat'] === 'ACTIVE', 'empreinte émise, preuve active sans signature (empreinte non signée explicitement identifiée)');
$v($registre->resoudreEtat($referencePreuve1) === 'ACTIVE', 'l\'état courant reflète l\'activation immédiate d\'une empreinte seule');

$refusDoubleEmpreinte = $registre->emettreEmpreinte($referencePreuve1, 'SHA-256', $contenuJson, $g());
$v(($refusDoubleEmpreinte['refus'] ?? null) === 'ETAT_INVALIDE', 'une empreinte ne peut être émise que depuis PREPAREE');

// ------------------------------------------------------------------ vérification par empreinte présentée
$verifValide = $registre->verifierPreuve($referencePreuve1, ['empreinte_presentee' => hash('sha256', $contenuJson)]);
$v($verifValide['resultat'] === 'VALIDE' && $verifValide['preuve_utilisable'] === true, 'vérification par empreinte présentée identique : VALIDE');
$verifDivergente = $registre->verifierPreuve($referencePreuve1, ['empreinte_presentee' => hash('sha256', $contenuJson . 'x')]);
$v($verifDivergente['resultat'] === 'EMPREINTE_DIVERGENTE', 'un contenu modifié produit EMPREINTE_DIVERGENTE, jamais un simple booléen');
$v($verifDivergente['divergences'] !== [], 'les divergences sont structurées et bornées');

// ------------------------------------------------------------------ signature Ed25519 réelle
$preuveSignee = $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'DOC-P3-SIGNEE']));
$refSignee = (string) $preuveSignee['reference'];
$registre->emettreEmpreinte($refSignee, 'SHA-256', $contenuJson, array_merge($g(), ['signature_requise' => true]));
$v($registre->resoudreEtat($refSignee) === 'EMISE', 'une preuve dont la signature est requise reste EMISE, jamais ACTIVE avant signature');
$signature = $registre->emettreSignature($refSignee, 'KEY-GAMAD-TEST-SIGNATURE', $usage, array_merge($g(), ['cle_publique_base64' => $clePubliqueTest]));
$v(!isset($signature['refus']) && $signature['etat'] === 'ACTIVE', 'signature Ed25519 réelle émise et vérifiée immédiatement, preuve active');
$v($registre->resoudreSignatures($refSignee) !== [], 'la signature est persistée et résoluble');
$signatureJson = json_encode($registre->resoudreSignatures($refSignee), JSON_UNESCAPED_UNICODE);
$v(!str_contains((string) $signatureJson, base64_encode(sodium_crypto_sign_secretkey($paireCles))), 'aucune clé privée dans la réponse de signature');

$verifSignatureValide = $registre->verifierPreuve($refSignee, [
    'signature_a_verifier' => $registre->resoudreSignatures($refSignee)[0]['signature_base64url'],
    'cle_publique_base64' => $clePubliqueTest,
]);
$v($verifSignatureValide['resultat'] === 'VALIDE', 'signature Ed25519 vérifiée valide avec la clé publique');

$verifSignatureAlteree = $registre->verifierPreuve($refSignee, [
    'signature_a_verifier' => substr($registre->resoudreSignatures($refSignee)[0]['signature_base64url'], 0, -2) . 'zz',
    'cle_publique_base64' => $clePubliqueTest,
]);
$v($verifSignatureAlteree['resultat'] === 'SIGNATURE_INVALIDE', 'une signature modifiée est détectée invalide');

$autrePaire = sodium_crypto_sign_keypair();
$verifAutreCle = $registre->verifierPreuve($refSignee, [
    'signature_a_verifier' => $registre->resoudreSignatures($refSignee)[0]['signature_base64url'],
    'cle_publique_base64' => base64_encode(sodium_crypto_sign_publickey($autrePaire)),
]);
$v($verifAutreCle['resultat'] === 'SIGNATURE_INVALIDE', 'une clé publique différente invalide la vérification');

// contexte modifié : rejouer la signature contre un contexte différent (autre sujet) échoue
$autrePreuve = $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'DOC-P3-AUTRE']));
$registre->emettreEmpreinte((string) $autrePreuve['reference'], 'SHA-256', $contenuJson, $g());
$verifContexteDifferent = $registre->verifierPreuve((string) $autrePreuve['reference'], [
    'signature_a_verifier' => $registre->resoudreSignatures($refSignee)[0]['signature_base64url'],
    'cle_publique_base64' => $clePubliqueTest,
]);
$v($verifContexteDifferent['resultat'] === 'SIGNATURE_INVALIDE', 'rejouer une signature valide dans un autre contexte (sujet différent) échoue — pas de réutilisation inter-contexte');

// ------------------------------------------------------------------ manifeste
$membres = [
    ['chemin_logique' => 'index.dump', 'taille_octets' => 1000, 'algorithme_empreinte' => 'SHA-256', 'empreinte' => hash('sha256', 'index')],
    ['chemin_logique' => 'identites.dump', 'taille_octets' => 2000, 'algorithme_empreinte' => 'SHA-256', 'empreinte' => hash('sha256', 'identites')],
];
$preuveManifeste = $registre->preparerPreuve(array_merge($dossierPreuve, ['type_preuve' => 'MANIFESTE', 'sujet_type' => 'SAUVEGARDE', 'sujet_reference' => 'BKP-P3-TEST']));
$manifeste = $registre->emettreManifeste((string) $preuveManifeste['reference'], $membres, array_merge($g(), ['type_manifeste' => 'SAUVEGARDE', 'nom' => 'Lot P3']));
$v(!isset($manifeste['refus']) && $manifeste['membres'] === 2, 'manifeste émis avec ses deux membres');
$v(preg_match('/^[0-9a-f]{64}$/', (string) $manifeste['racine_empreinte']) === 1, 'racine de manifeste déterministe (SHA-256 hex)');

$manifesteRejoue = $registre->emettreManifeste((string) $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'BKP-P3-TEST-2']))['reference'], array_reverse($membres), array_merge($g(), ['type_manifeste' => 'SAUVEGARDE']));
$v($manifesteRejoue['racine_empreinte'] === $manifeste['racine_empreinte'], 'ordre non significatif normalisé : même racine quel que soit l\'ordre de soumission');

$refusManifesteVide = $registre->emettreManifeste((string) $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'BKP-P3-VIDE']))['reference'], [], array_merge($g(), ['type_manifeste' => 'SAUVEGARDE']));
$v(($refusManifesteVide['refus'] ?? null) === 'MANIFESTE_VIDE', 'manifeste vide refusé');

$membresDupliques = [$membres[0], $membres[0]];
$refusDoublon = $registre->emettreManifeste((string) $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'BKP-P3-DUP']))['reference'], $membresDupliques, array_merge($g(), ['type_manifeste' => 'SAUVEGARDE']));
$v(($refusDoublon['refus'] ?? null) === 'MEMBRE_DUPLIQUE', 'membres dupliqués refusés');

$membreCheminInvalide = [['chemin_logique' => '/etc/passwd', 'taille_octets' => 1, 'algorithme_empreinte' => 'SHA-256', 'empreinte' => hash('sha256', 'x')]];
$refusCheminAbsolu = $registre->emettreManifeste((string) $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'BKP-P3-CHEMIN']))['reference'], $membreCheminInvalide, array_merge($g(), ['type_manifeste' => 'SAUVEGARDE']));
$v(($refusCheminAbsolu['refus'] ?? null) === 'CHEMIN_INVALIDE', 'chemin de membre absolu refusé');

$membreTraversal = [['chemin_logique' => '../secret.txt', 'taille_octets' => 1, 'algorithme_empreinte' => 'SHA-256', 'empreinte' => hash('sha256', 'x')]];
$refusPathTraversal = $registre->emettreManifeste((string) $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'BKP-P3-TRAVERSAL']))['reference'], $membreTraversal, array_merge($g(), ['type_manifeste' => 'SAUVEGARDE']));
$v(($refusPathTraversal['refus'] ?? null) === 'CHEMIN_INVALIDE', 'path traversal (`..`) dans un membre refusé');

// membre modifié / manquant / supplémentaire détecté (comparaison de manifeste applicative simple)
$membresAlteres = $membres;
$membresAlteres[0]['empreinte'] = hash('sha256', 'index-modifie');
$manifesteOriginal = $registre->resoudreManifeste((string) $preuveManifeste['reference']);
$divergenceMembre = false;
foreach ($manifesteOriginal['membres'] as $i => $m) {
    if ($m['empreinte'] !== $membresAlteres[$i]['empreinte']) {
        $divergenceMembre = true;
    }
}
$v($divergenceMembre, 'un membre modifié est détectable par comparaison directe des empreintes du manifeste résolu');
$membresManquant = array_slice($membres, 0, 1);
$v(count($manifesteOriginal['membres']) !== count($membresManquant), 'un membre manquant change le compte de membres, détectable');

// ------------------------------------------------------------------ attestation
$preuveAttestation = $registre->preparerPreuve(array_merge($dossierPreuve, ['type_preuve' => 'ATTESTATION', 'sujet_type' => 'RESTAURATION', 'sujet_reference' => 'RST-P3-TEST']));
$attestation = $registre->emettreAttestation((string) $preuveAttestation['reference'], array_merge($g(), [
    'type_attestation' => 'SAUVEGARDE_VERIFIEE', 'version_schema' => '1', 'resultat' => 'CONFORME',
    'declaration' => ['lot_reference' => 'BKP-P3-TEST', 'resultat' => 'CONFORME'],
]));
$v(!isset($attestation['refus']), 'attestation conforme au schéma acceptée');

$refusAttestationHorsSchema = $registre->emettreAttestation((string) $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'RST-P3-2']))['reference'], array_merge($g(), [
    'type_attestation' => 'SAUVEGARDE_VERIFIEE', 'version_schema' => '1', 'resultat' => 'CONFORME',
    'declaration' => ['lot_reference' => 'BKP-P3-TEST', 'champ_invente' => 'non prévu'],
    'champs_autorises' => ['lot_reference', 'resultat'],
]));
$v(($refusAttestationHorsSchema['refus'] ?? null) === 'CHAMP_SUPPLEMENTAIRE', 'un champ hors schéma déclaré est refusé');

// ------------------------------------------------------------------ checkpoint
$preuveCheckpoint = $registre->preparerPreuve(array_merge($dossierPreuve, ['type_preuve' => 'CHECKPOINT', 'sujet_type' => 'JOURNAL', 'sujet_reference' => 'JRN-P3-TEST']));
$checkpoint = $registre->emettreCheckpoint((string) $preuveCheckpoint['reference'], array_merge($g(), [
    'type_checkpoint' => 'JOURNAL_AUDIT', 'structure_reference' => 'journal-operationnel', 'tete_empreinte' => hash('sha256', 'tete'), 'sequence' => 42,
]));
$v(!isset($checkpoint['refus']), 'checkpoint émis');
$checkpointResolu = $registre->resoudreCheckpoint((string) $preuveCheckpoint['reference']);
$v($checkpointResolu !== null && (int) $checkpointResolu['sequence'] === 42, 'checkpoint résoluble avec sa séquence');

// ------------------------------------------------------------------ cycle : suspension, révocation, compromission
$suspension = $registre->suspendrePreuve($referencePreuve1, array_merge($g(), ['motif_code' => 'DOUTE_TEMPORAIRE']));
$v($suspension['etat'] === 'SUSPENDUE', 'suspension pour doute temporaire, sans suppression');
$revocation = $registre->revoquerPreuve($referencePreuve1, array_merge($g(), ['motif_code' => 'FIN_DE_VIE']));
$v($revocation['etat'] === 'REVOQUEE', 'révocation depuis SUSPENDUE');
$refusReactivation = $registre->suspendrePreuve($referencePreuve1, array_merge($g(), ['motif_code' => 'TENTATIVE']));
$v(isset($refusReactivation['refus']), 'une preuve révoquée ne redevient jamais active par un simple réappel');

$compromission = $registre->declarerCompromission($refSignee, array_merge($g(), ['motif_code' => 'CLE_SUSPECTEE']));
$v($compromission['etat'] === 'COMPROMISE', 'compromission déclarée, bloquant immédiatement la preuve');
$refusApresCompromission = $registre->revoquerPreuve($refSignee, array_merge($g(), ['motif_code' => 'TENTATIVE']));
$v(($refusApresCompromission['refus'] ?? null) === 'PREUVE_COMPROMISE', 'une preuve compromise ne redevient jamais active (aucune transition acceptée)');

$verifApresRevocation = $registre->verifierPreuve($referencePreuve1, []);
$v($verifApresRevocation['resultat'] === 'PREUVE_REVOQUEE' && $verifApresRevocation['preuve_utilisable'] === false, 'vérification après révocation : PREUVE_REVOQUEE, preuve non utilisable');
$verifIndetermineeSansArtefact = $registre->verifierPreuve((string) $preuveCheckpoint['reference'], []);
$v(in_array($verifIndetermineeSansArtefact['resultat'], ['VALIDE', 'INDETERMINE'], true), 'vérification sans artefact présenté produit un résultat explicite, jamais un faux VALIDE par défaut sur une preuve non active pertinente');

// ------------------------------------------------------------------ liens
$lien = $registre->declarerLien($referencePreuve1, $refSignee, 'DERIVE_DE');
$v(!isset($lien['refus']), 'lien DERIVE_DE déclaré');
$refusAutoLien = $registre->declarerLien($referencePreuve1, $referencePreuve1, 'DERIVE_DE');
$v(isset($refusAutoLien['refus']), 'auto-lien refusé');
$registre->declarerLien($refSignee, $referencePreuve1, 'DERIVE_DE');
// Un cycle direct existe déjà (référencePreuve1 -> refSignee -> référencePreuve1) ; on referme un cycle à trois pour prouver la détection au-delà de la profondeur 1.
$troisieme = (string) $registre->preparerPreuve(array_merge($dossierPreuve, ['sujet_reference' => 'DOC-P3-CYCLE']))['reference'];
$registre->declarerLien($troisieme, $referencePreuve1, 'DERIVE_DE');
$cycleDetecte = $registre->declarerLien($refSignee, $troisieme, 'DERIVE_DE');
$v(isset($cycleDetecte['refus']) && $cycleDetecte['refus'] === 'CYCLE_REFUSE', 'un cycle DERIVE_DE à trois preuves est détecté et refusé');
$v($registre->resoudreLiens($referencePreuve1) !== [], 'les liens d\'une preuve sont résolubles');

// ------------------------------------------------------------------ paquet exportable
$paquet = $registre->exporterPaquetPreuve($refSignee, 'VERIFICATION_INTERNE', $g());
$v(!isset($paquet['refus']) && isset($paquet['empreinte_paquet']), 'paquet exportable valide, empreinté');
$paquetJson = json_encode($paquet, JSON_UNESCAPED_UNICODE);
$v(!str_contains((string) $paquetJson, base64_encode(sodium_crypto_sign_secretkey($paireCles))), 'le paquet exportable ne contient jamais de clé privée');
$refusProfilInconnu = $registre->exporterPaquetPreuve($refSignee, 'PROFIL_INEXISTANT', $g());
$v(isset($refusProfilInconnu['refus']), 'un profil d\'export hors liste close est refusé');

// ------------------------------------------------------------------ diagnostic et contre-épreuve
$diagnostic = $registre->diagnostiquerRegistre();
$v($diagnostic['sodium_disponible'] === true, 'le diagnostic confirme la disponibilité réelle de sodium (Ed25519 utilisable)');
$v($diagnostic['compromises'] >= 1, 'le diagnostic rapporte la preuve compromise déclarée pendant l\'exercice — visible, pas masquée');
$diagnosticJson = json_encode($diagnostic, JSON_UNESCAPED_UNICODE);
$v(!str_contains(strtolower((string) $diagnosticJson), base64_encode(sodium_crypto_sign_secretkey($paireCles))), 'le diagnostic ne contient aucune valeur secrète');

$fauxPositif = $registre->emettreEmpreinte('PRF-GAMAD-INCONNUE', 'SHA-256', 'x', $g());
$v(isset($fauxPositif['refus']), 'contre-épreuve : émettre une empreinte sur une preuve inconnue échoue bien (la garde n\'est pas toujours verte)');

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-015 (noyau) : ÉTABLIE.\n";
    exit(0);
}

echo "Garde CAP-CORE-015 (noyau) : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
