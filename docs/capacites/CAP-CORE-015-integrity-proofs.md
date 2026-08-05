# CAP-CORE-015 — INTEGRITY PROOFS

**Nom :** Registre des preuves d'intégrité
**État réel au commit de tête de ce chantier :** voir `docs/capacites/CATALOGUE.md` — ne jamais présumer `GO` à partir de ce seul document.

Cette fiche décrit le code réellement livré. Elle n'est ni une loi, ni un acte d'adoption, ni une preuve d'exécution en production.

## 1. Objectif

Produire des preuves techniques vérifiables — empreintes, signatures, manifestes, checkpoints, attestations — pour des artefacts, journaux et cycles de sauvegarde/restauration du Core, **sans jamais conserver ni retourner de matériel de clé privée**. Le registre répond à « cet artefact correspond-il à cette empreinte, cette signature est-elle valide, ce manifeste est-il intact » — jamais à « voici la clé qui a signé ».

## 2. Frontières

`CAP-CORE-015` possède les preuves elles-mêmes (empreinte, représentation canonique, signature facultative, cycle de vie), les manifestes, checkpoints, attestations, liens entre preuves, et les paquets exportables qui en résultent.

`CAP-CORE-015` ne possède jamais : une clé privée, un secret, la valeur d'un artefact de production complet (seule son empreinte est conservée), ou une décision d'autorisation — `CAP-CORE-004` reste seul décideur, `CAP-CORE-013` reste seul détenteur du journal d'audit qu'elle checkpointe, `CAP-CORE-016` reste seul détenteur des clés qu'elle utilise pour signer.

## 3. Modèle de données livré

`core/registre-preuves/src/SchemaPreuves.php` — douze tables persistantes dans un magasin `gamad_preuves` séparé (`PROOF_REGISTRY_URL`/`PROOF_REGISTRY_PATH`) :

- `preuve` — référence stable (`PRF-GAMAD-…`), type (9 valeurs closes), sujet, producteur, realm, finalité, source, contrat, classification — **aucune colonne de valeur secrète** ;
- `preuve_representation` — format canonique (JSON/texte/octets bruts/manifeste/checkpoint/déclaration), taille bornée, chemin logique validé (jamais absolu, jamais de `..`) ;
- `preuve_empreinte` — SHA-256/SHA-512 uniquement, MD5/SHA-1 explicitement refusés à l'écriture ;
- `preuve_signature` — algorithme Ed25519 uniquement, référence de clé, jamais la clé elle-même ;
- `preuve_cycle` — ajout seul, états `PREPAREE → EMISE → ACTIVE → EXPIREE/SUSPENDUE/REVOQUEE/COMPROMISE/ARCHIVEE`, `COMPROMISE` terminal (jamais réactivable) ;
- `manifeste`, `manifeste_membre` — racine d'empreinte déterministe, ordre significatif ou non selon déclaration ;
- `attestation` — schéma fermé par type, champ supplémentaire refusé ;
- `checkpoint_preuve` — tête et séquence d'une structure chaînée (journal ou événements) ;
- `verification_preuve` — résultat immuable une fois écrit (13 résultats possibles, jamais un simple booléen) ;
- `preuve_lien` — types fermés (`DERIVE_DE`, `REMPLACE`, `RESTAURE_DEPUIS`, …), détection de cycle récursive sur `DERIVE_DE` ;
- `paquet_preuve` — export borné, jamais de clé privée.

**Garde absolue** (`RegistrePreuves::refuserChampsInterdits()`) : tout dossier portant un champ `value`, `secret`, `private_key`, `cle_privee`, `mot_de_passe`, `token`, `jeton` ou équivalent est refusé avant toute écriture — même garde de principe que `CAP-CORE-016`.

## 4. Canonicalisation, empreinte et signature

`Canonicaliseur` — JSON canonique déterministe : clés triées récursivement, ordre des listes préservé, Unicode normalisé NFC, valeurs non finies (`NAN`/`INF`) refusées. Utilisé pour construire aussi bien le contexte signé que la racine de manifeste.

`CalculateurEmpreinte` — SHA-256/SHA-512 exclusivement, en flux (`hash_update_stream`) pour les gros artefacts, refuse chemin relatif, path traversal, lien symbolique. Comparaison de signature toujours en temps constant (`hash_equals`).

`ServiceSignature` — signe et vérifie Ed25519 via l'extension `sodium`. **La clé privée n'existe jamais en dehors du callback fourni à `CAP-CORE-016`** : `ServiceSignature::signer()` appelle `ResolveurSecret::avecSecret()`, qui base64-décode la clé, appelle `sodium_crypto_sign_detached()`, efface la mémoire (`sodium_memzero`) et ne retourne que la signature produite. `RegistrePreuves` ne voit jamais la clé, seulement son résultat. Une preuve signée est **toujours immédiatement re-vérifiée avec la clé publique avant activation** — jamais activée sur une signature non vérifiable.

## 5. Vocabulaire, politique et contrats

- **Politique (`CAP-CORE-007`)** — `POL-PREUVES-V1`, quatorze actions, réservées à `AUT-GAMAD-001` (autorité unique confirmée pour le Core, même principe que `POL-SECRETS-CLES-V1` et `POL-EVENEMENTS-V1`).
- **Contrats (`CAP-CORE-009`)** — sept contrats techniques `CTR-GAMAD-PREUVE-*` (`preuve.resoudre`, `preuve.verifier`, `preuve.paquet.verifier`, `preuve.emettre`, `preuve.revoquer`, `manifeste.resoudre`, `attestation.resoudre`).
- **Vocabulaire (`CAP-CORE-010`)** — **non intégré dans ce chantier.** Types, formats, algorithmes et résultats sont des listes PHP fermées dans `PolitiquePreuves`, vérifiées en code — même limite assumée que `CAP-CORE-014` et `CAP-CORE-016`.
- **Baseline (`CAP-CORE-007`/registre-normes)** — le bootstrap ré-empreinte le fichier réel `core/registre-normes/resources/index-baseline-v1.json` et cross-vérifie le résultat contre `BaselineOperationnelle::standard()->empreinte()` via `hash_equals()`, plutôt que de faire confiance à l'empreinte déjà calculée par un autre module.

## 6. API et console

API v1 : 18 routes sous `/api/v1/preuves*`. Volontairement plus restreinte que la liste complète envisagée par la fiche (partie 4 §1) : l'API n'expose **aucune** signature de contenu libre, aucun choix de clé par l'appelant, aucune lecture de chemin arbitraire. La seule émission possible par API est l'empreinte d'un contenu JSON borné fourni inline (`POST /preuves`). Signature, manifeste, checkpoint et attestation restent réservés à la CLI, qui seule connaît les chemins et magasins réels autorisés — ce n'est pas un chantier remis à plus tard, c'est la frontière de sécurité que la fiche elle-même exige.

Une route de vérification de paquet (`POST /preuves/verification-paquet`) ne fait jamais confiance au contenu déclaré du paquet soumis : `RegistrePreuves::verifierPaquetPreuve()` reconstruit le paquet attendu depuis le registre pour la même référence et le même profil, compare structurellement, puis retourne le résultat cryptographique réel de `verifierPreuve()`.

Console (`PreuveConsoleController`, 6 routes) : tableau de bord (compteurs de cycle, disponibilité `sodium`), fiche de preuve (empreintes, signatures, manifeste lié, vérifications, liens), vérification à la demande, suspension/révocation/compromission avec motif codé obligatoire (`motif_code`) — aucune signature de texte libre depuis cet écran.

## 7. CLI d'exploitation

Huit commandes, dont trois raccordements réels à d'autres capacités :

- `core:preuves:diagnostiquer`, `core:preuves:verifier`, `core:preuves:empreinter` (chemin borné à `/var/backups/gamad-core` et `/var/www/gamad-core`), `core:preuves:manifeste-release` — génériques.
- `core:preuves:checkpoint-journal` — lit réellement la tête et le nombre d'événements du journal opérationnel (`CAP-CORE-013`) via `JournalMagasin::connecter()`, jamais une supposition.
- `core:preuves:manifeste-sauvegarde` — lit le `SHA256SUMS` réel du dernier lot de `GAMAD_BACKUP_DIR` et **ré-empreinte indépendamment chaque membre** avant de construire le manifeste ; refuse si un membre diverge du fichier de contrôle produit par `backup.sh`.
- `core:preuves:attester-restauration` — enregistre les comptages réels fournis par l'opérateur après un exercice `CAP-CORE-019` déjà exécuté, et lie l'attestation au manifeste d'origine par `RESTAURE_DEPUIS` (résolvable par la référence de manifeste **ou** par la référence de preuve porteuse — bug réel trouvé et corrigé en cours de chantier, voir §9).

**Non livrée** : `core:preuves:reconcilier-signatures` (envisagée par la fiche partie 3). Aucune signature orpheline ou divergente n'a été observée nécessitant une réconciliation automatisée dans ce chantier ; à construire au premier besoin réel plutôt que par anticipation.

## 8. Readiness, sauvegarde et restauration

`EtatFondation` inspecte désormais **quatorze magasins** : la cible `preuves` vérifie les douze tables et rapporte préparées-bloquées, compromises, et disponibilité de `sodium` (bloquant l'intégrité de la cible si l'extension est absente). `MigrerFondationCommand` exige `PROOF_REGISTRY_URL` en production, comme les treize autres cibles. `ops/core-foundation/backup.sh` et `restore-drill.sh` incluent la cible `preuves` (dump, SHA-256, comptages post-restauration sur `preuve`, `preuve_empreinte`, `preuve_signature`). `postgresql_p0.sh` provisionne `gamad_preuves`/`drill_preuves` et exécute le cycle complet aux côtés des treize autres magasins.

## 9. Bugs réels trouvés et corrigés en cours de chantier

- **Index sur colonne inexistante** — `verification_preuve` utilise `reference TEXT PRIMARY KEY` (pas de colonne `id`), mais un index le référençait ; corrigé pour indexer sur `cree_le`.
- **Auto-activation prématurée** — `emettreEmpreinte()` transitionnait systématiquement vers `ACTIVE`, cassant le parcours signé (`emettreSignature()` exige `PREPAREE`/`EMISE`). Corrigé par un drapeau `signature_requise` explicite dans le dossier d'émission.
- **Double hachage au bootstrap** — `bootstrapEmpreinteBaseline()` passait initialement l'empreinte déjà calculée de la baseline comme si c'était le contenu à hacher. Corrigé pour lire et hacher le fichier réel, puis cross-vérifier le résultat.
- **Résolution du manifeste par la mauvaise référence** — `resoudreManifeste()` n'acceptait que la référence de la preuve porteuse (`PRF-…`), alors que tous les producteurs (CLI de manifeste-sauvegarde, manifeste-release) présentent d'abord à l'opérateur la référence propre du manifeste (`MNF-…`). `core:preuves:attester-restauration` ne pouvait donc jamais résoudre le manifeste qu'un opérateur collerait réellement. Trouvé en exerçant le parcours API de bout en bout, pas par relecture de code — corrigé pour accepter les deux références.
- **Champ `motif`/`motif_code`** — la première version de l'API, de la console et du test d'intégration utilisait un champ `motif` pour suspension/révocation/compromission, alors que le registre (déjà éprouvé par la garde de 66 épreuves) exige `motif_code` (+ `motif_detail` facultatif). Trouvé par l'échec réel du test d'intégration HTTP, pas anticipé.

## 10. Code livré

```text
core/registre-preuves/
├── src/
│   ├── Magasin.php, SchemaPreuves.php, PolitiquePreuves.php
│   ├── Canonicaliseur.php, CalculateurEmpreinte.php, ServiceSignature.php
│   ├── RegistrePreuves.php, ExceptionPreuve.php
└── tests/preuves_p3.php

apps/console-laravel/
├── app/Application/Preuves/AccesPreuves.php
├── app/Http/Controllers/Api/V1/PreuveController.php
├── app/Http/Controllers/PreuveConsoleController.php
├── app/Console/Commands/{Bootstrap,Diagnostiquer,Verifier,Empreinter,
│   ManifesteRelease,CheckpointJournal,ManifesteSauvegarde,
│   AttesterRestauration}PreuvesCommand.php
├── app/Support/EtatFondation.php (cible preuves)
├── app/Console/Commands/MigrerFondationCommand.php (cible preuves)
├── resources/views/preuves/{tableau-de-bord,preuve}.blade.php
└── tests/Integration/preuves_v1_p1.php

ops/core-foundation/
└── backup.sh, restore-drill.sh (cible preuves)

apps/console-laravel/tests/Integration/postgresql_p0.sh, postgresql_p0.php (cible preuves)
```

## 11. Tests exécutés et résultats réels

Exécutés le 5 août 2026, sur ce commit :

- `core/registre-preuves/tests/preuves_p3.php` — **66/66** épreuves (SQLite) : keypair Ed25519 réel via `sodium_crypto_sign_keypair()`, cycle complet préparation → empreinte → signature → activation → vérification, JSON canonique déterministe, refus MD5/SHA-1, path traversal refusé, cycle de compromission terminal, manifeste avec membre modifié/manquant/supplémentaire détecté, cycle `DERIVE_DE` refusé, paquet exportable sans clé privée, checkpoint CAP-CORE-013 et manifeste CAP-CORE-019 réels, contre-épreuve finale.
- `apps/console-laravel/tests/Integration/preuves_v1_p1.php` — **19/19** : parcours HTTP complet — refus sans session, émission d'empreinte bornée, empreinte exacte SHA-256, vérification `VALIDE` puis `EMPREINTE_DIVERGENTE` (HTTP 200 dans les deux cas), export de paquet, contre-vérification du paquet, paquet altéré détecté `PAQUET_DIVERGENT`, suspension → révocation, chaînage dans l'audit CAP-CORE-013.
- **Trois raccordements réels exercés avec de vraies données**, pas seulement des CLI syntaxiquement valides :
  - `core:preuves:checkpoint-journal` contre un événement réellement écrit via `Journal::enregistrer()` dans un journal isolé — checkpoint créé, vérifié `VALIDE`.
  - `core:preuves:manifeste-sauvegarde` contre le **vrai dernier lot de production** sous `/var/backups/gamad-core/daily` — 13 membres re-empreintés indépendamment, racine calculée, manifeste `MNF-GAMAD-…` émis.
  - `core:preuves:attester-restauration` chaîné sur ce même manifeste réel, résolu par sa référence `MNF-…`, attestation liée par `RESTAURE_DEPUIS`.
- **PostgreSQL réel** (`postgresql_p0.sh`, sous l'utilisateur `postgres`) — quatorze magasins réellement PostgreSQL, sauvegarde et restauration sur quatorze cibles isolées, comptages post-restauration cohérents (`preuve`, `preuve_empreinte`, `preuve_signature` inclus).
- Reste de la suite d'intégration existante (39 fichiers) — **sans régression observée**, y compris après la correction du compte de cibles readiness (13 → 14) dans `api_v1_p1.php` et l'ajout de `PROOF_REGISTRY_URL` dans `migration_config_cache_p1.php`.
- `core/journal-operationnel/tests/fondation_operationnelle_p3.php` — sans régression.
- Syntaxe PHP de l'ensemble `core/` et `apps/` — aucune erreur.

Total des épreuves propres à CAP-CORE-015 : **85** (66 + 19), plus le cycle PostgreSQL réel et les trois raccordements exercés avec des données réelles.

**Non exécuté dans ce chantier :** la CI GitHub Actions réelle sur une PR (à confirmer une fois ouverte).

## 12. Limites non bloquantes et réserves

- **Signature, manifeste, checkpoint et attestation ne sont pas exposés par API** — décision de sécurité délibérée (fiche partie 4 §1), pas une omission : ces émissions restent CLI-only, exécutées par un opérateur ou un futur job planifié, jamais par un client HTTP.
- **`core:preuves:reconcilier-signatures` non livrée** — aucune signature orpheline observée nécessitant cet outil dans ce chantier.
- **OpenAPI (`openapi/core-v1.yaml`) non renseigné** pour ces routes — même réserve, non traitée non plus, que `CAP-CORE-016`.
- **Aucun consommateur réel planifié en production** — les trois raccordements (checkpoint, manifeste, attestation) sont exécutables et exercés manuellement avec des données réelles dans ce chantier, mais aucune unité systemd/timer ne les déclenche encore automatiquement (contrairement à `CAP-CORE-014`, qui a cinq unités dédiées). Prochain consommateur réel logique : planifier `core:preuves:checkpoint-journal` et `core:preuves:manifeste-sauvegarde` après chaque sauvegarde quotidienne.
- **Vocabulaire CAP-CORE-010 non intégré** — listes PHP fermées, alignées sur le précédent de CAP-CORE-014/016.
- **Rotation de clé de signature non exercée en production** — le mécanisme de rotation appartient à `CAP-CORE-016` et y est déjà éprouvé (voir sa propre fiche) ; ce chantier consomme une clé active, il ne fait pas tourner de clé lui-même.

## 13. Décision

```text
CAP-CORE-015 — GO
```

Sous réserve explicite des limites listées en §12 — en particulier l'absence de planification automatique des raccordements CAP-CORE-013/019 et l'OpenAPI non renseigné — à traiter au premier besoin réel d'exploitation continue, pas par anticipation dans ce chantier.
