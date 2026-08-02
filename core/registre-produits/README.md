# CAP-CORE-011 — Registre des produits

Le Core connaissait les produits par un simple constat dérivé de l'index
documentaire reconstructible : une entité de type `produit`, un état textuel
libre, et une fédérabilité devinée par `str_contains($etat, 'RECONNU')`. Ce
module lui donne une fiche opérationnelle réelle, persistante, gouvernée et
distincte de cet index.

## Ce que ce module possède

- la référence du produit, immuable, jamais réattribuée ;
- son identité canonique associée (CAP-CORE-001), elle aussi immuable une fois
  inscrite ;
- son nom canonique et son nom d'exploitation ;
- son type, dans une liste close (`PORTAIL`, `SATELLITE`, `SERVICE_CORE`,
  `PARTENAIRE`, `APPLICATION_INTERNE`) ;
- son propriétaire ou responsable, sa source technique ;
- sa fédérabilité explicite (`federation_autorisee`), un booléen gouverné —
  plus un marqueur de texte libre ;
- son cycle de vie en ajout seul : `PREPARATION` → `ACTIF` → `SUSPENDU` /
  `RETIRE`, chaque transition datée, motivée, prouvée ;
- ses environnements versionnés (`DEVELOPPEMENT`, `RECETTE`, `PRODUCTION`),
  chacun avec une URL d'API, une URL de santé facultative et une audience de
  fédération unique parmi les environnements actifs.

## Ce que ce module ne possède pas

Les comptes utilisateurs du produit, ses rôles métier, ses abonnements, ses
quotas, ses transactions, ses contenus, ses secrets en clair, les jetons
fédérés (CAP-CORE-022), les relations identité-produit (CAP-CORE-001), les
politiques d'autorisation (CAP-CORE-004) ou les contrats intercapacités
complets (CAP-CORE-009, à venir).

## Ce module ne décide rien lui-même

Chaque commande gouvernée exige `politique`, `producteur`, `source` et
`preuve` dans son dossier, comme `Ctr01` (CAP-CORE-001) et `Federation`
(CAP-CORE-022). La décision d'autoriser une commande donnée vient de
CAP-CORE-004, dans la couche applicative (`App\Application\Produits\AccesProduits`) ;
ce module conserve ses propres bornes — refus d'auto-activation, refus par
défaut sur un état incompatible, unicité d'audience — même si une politique
est mal écrite ailleurs.

## Magasin

Variables dédiées :

```text
PRODUCT_REGISTRY_URL   PostgreSQL en exploitation
PRODUCT_REGISTRY_PATH  SQLite en local et en CI
```

`DATABASE_URL`, `IDENTITY_REGISTRY_URL`, `MAGASIN_URL` et
`JOURNAL_OPERATIONNEL_URL` appartiennent à d'autres magasins et ne sont jamais
consultées ici. `php artisan core:fondation:migrer` applique la migration ; la
readiness (`/api/v1/health/ready`) la vérifie.

## Bootstrap des produits historiques

`php artisan core:produits:bootstrap` reprend les quatre produits déjà connus
de l'index (`PRD-GAMAD-001` à `004`) sans en inventer de nouveaux : GamaDrive
est inscrit puis activé avec `federation_autorisee = true`, reproduisant l'état
`RECONNU` déjà présent dans la baseline ; Wasplex et IKOMA sont inscrits en
`PREPARATION`, non fédérables, reproduisant leur état `PARTENAIRE EXTERNE —
APPARTENANCE NON ENTÉRINÉE` ; GAMAD ID est inscrit puis retiré, reproduisant sa
dissolution déjà actée dans `CAP-CORE-001-identity-registry.md`. La commande
est idempotente : la rejouer ne crée ni doublon, ni seconde ligne de cycle.

## Limite assumée

Aucun environnement de production réel n'est déclaré pour GamaDrive : le Core
ne connaît pas encore d'URL d'API GamaDrive réelle, et ce module n'en invente
aucune. `activerProduit()` n'exige donc pas d'environnement actif ;
`verifierUtilisablePourFederation($reference, $environnement)` reste
disponible pour l'appelant qui veut vérifier un environnement précis, mais
`Federation::ouvrir()` (CAP-CORE-022) ne l'exige pas encore. C'est un manque
documenté, pas un oubli : voir `docs/capacites/CAP-CORE-011-products-registry.md`.

## Tests

```text
php core/registre-produits/tests/produits_p3.php
```
