# CAP-CORE-006 — Registre des sources

Le Core connaissait les sources par une simple ligne de lecture seule de
l'index documentaire reconstructible (`reference`, `titre`, `categorie`,
`authenticite`, `reserve`), résolue par `CTR-15` en s'appuyant sur les tables
`norme`, `version_norme` et `statut` du registre des normes pour son rang et
son statut. Ce module lui donne une fiche opérationnelle réelle, persistante,
gouvernée et distincte de cet index — et corrige le sens de la dépendance :
c'est désormais CAP-CORE-007 qui dépend de CAP-CORE-006, jamais l'inverse.

## Ce que ce module possède

- la référence de la source, immuable, jamais réattribuée ;
- son nom canonique et son type, immuables (`PRODUIT_GAMAD`, `SERVICE_CORE`,
  `ORGANISATION`, `INSTITUTION`, `PARTENAIRE`, `SYSTEME_EXTERNE`,
  `IMPORT_GOUVERNE`, `CANAL_DECLARATIF`) ;
- sa valeur historique d'authenticité, conservée telle quelle
  (`authenticite_legacy`), jamais réinterprétée automatiquement ;
- ses métadonnées révisables — nom d'exploitation, catégorie, description,
  propriétaire, produit producteur, réserve — en ajout seul : une correction
  crée une nouvelle révision, jamais une réécriture ;
- son cycle de vie en ajout seul : `PREPARATION` → `ACTIVE` → `SUSPENDUE` /
  `RETIREE`, chaque transition datée, motivée, prouvée ;
- ses vérifications opérationnelles historisées et expirables (`NON_VERIFIEE`,
  `DECLAREE`, `CONTROLEE`, `ATTESTEE`), sans auto-attestation possible ;
- ses finalités d'usage, bornées par consommateur (produit CAP-CORE-011,
  facultatif) et par période — aucune n'est jamais implicite ;
- sa lignée (`DERIVEE_DE`, `AGREGE`, `REMPLACE`, `CORRIGE`), acyclique par
  construction : toute relation qui fermerait un cycle est refusée avant
  écriture.

## Ce que ce module ne possède pas

Les données métier complètes produites par la source, les profils des
personnes, les fichiers, les documents justificatifs en clair, les secrets,
les politiques de décision (CAP-CORE-004), le vocabulaire canonique des
finalités (CAP-CORE-010, à venir) ou les contrats intercapacités complets
(CAP-CORE-009, à venir).

## Ce module ne décide rien lui-même

Chaque commande gouvernée exige `politique`, `producteur`, `source` et
`preuve` dans son dossier, comme `Ctr01` (CAP-CORE-001) et `RegistreProduits`
(CAP-CORE-011). La décision d'autoriser une commande donnée vient de
CAP-CORE-004, dans la couche applicative
(`App\Application\Sources\AccesSources`) ; ce module conserve ses propres
bornes — refus d'auto-attestation, détection de cycle de lignée avant
écriture, refus par défaut — même si une politique est mal écrite ailleurs.

## CTR-15 : contrat de lecture, découplé du registre des normes

`Ctr15::resoudreSource()`, `resoudreVerificationCourante()`,
`resoudreFinalites()` et `resoudreLignee()` ne lisent que ce magasin. Aucune
requête vers `norme`, `version_norme`, `statut`, `adoption` ou
`relation_evolution`. `Ctr04` (CAP-CORE-007) délègue à `CTR-15` puis ajoute,
quand elle existe, une projection de compatibilité historique construite
dans `Ctr04.php` lui-même — jamais ici.

## Magasin

Variables dédiées :

```text
SOURCE_REGISTRY_URL   PostgreSQL en exploitation
SOURCE_REGISTRY_PATH  SQLite en local et en CI
```

`DATABASE_URL`, `IDENTITY_REGISTRY_URL`, `MAGASIN_URL`, `PRODUCT_REGISTRY_URL`
et `JOURNAL_OPERATIONNEL_URL` appartiennent à d'autres magasins et ne sont
jamais consultées ici. `php artisan core:fondation:migrer` applique la
migration ; la readiness (`/api/v1/health/ready`) la vérifie.

## Bootstrap des sources historiques

`php artisan core:sources:bootstrap` reprend les sources déjà présentes dans
l'ancien index reconstructible (26 lignes au moment de ce chantier, comptées
à l'exécution, jamais fixées dans le code) sans en inventer de nouvelles.
Propriétaire par défaut : l'autorité d'inscription (`AUT-GAMAD-001`), seule
identité disponible pour ce lot importé. Type par défaut : `IMPORT_GOUVERNE`.
Cycle cible : `ACTIVE`, pour que les lectures déjà servies par `CTR-04`
continuent de trouver une source active. La commande est idempotente : la
rejouer ne crée aucun doublon.

## Limite assumée

Les références de finalité restent du texte libre documenté par le
consommateur, faute de vocabulaire canonique partagé (CAP-CORE-010, `NO GO`).
C'est un manque documenté, pas un oubli : voir
`docs/capacites/CAP-CORE-006-sources-registry.md`.

## Tests

```text
php core/registre-sources/tests/sources_p3.php
```
