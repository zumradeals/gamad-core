# CAP-CORE-011 — Products Registry

Fiche établie par inspection du code, des gardes et de la CI. Elle décrit ce
qui existe, pas ce qui est souhaité.

**Référence :** `CAP-CORE-011`

**Nom :** Registre des produits

**Objectif :** donner au Core une fiche opérationnelle persistante et
gouvernée de chaque produit — sa référence, son identité canonique, son type,
son propriétaire, son cycle de vie, ses environnements et sa fédérabilité
explicite — plutôt qu'un constat dérivé de l'index documentaire.

**Problème résolu :** avant ce chantier, un produit n'existait au Core que
comme une entité de type `produit` dans l'index reconstructible, avec un état
textuel libre. La fédérabilité (`CAP-CORE-022`) se déduisait d'un
`str_contains($etat, 'RECONNU')` : aucune écriture gouvernée, aucun cycle de
vie, aucun environnement déclaré, aucune preuve d'activation.

**Produits consommateurs :** GamaDrive (`PRD-GAMAD-002`), seul produit
`ACTIF` et `federation_autorisee = true` au bootstrap, reproduisant l'état
`RECONNU` déjà porté par la baseline documentaire — pas une nouvelle décision.
Wasplex (`PRD-GAMAD-003`) et IKOMA (`PRD-GAMAD-004`) restent inscrits en
`PREPARATION`, non fédérables, jusqu'à une activation explicite distincte.
GAMAD ID (`PRD-GAMAD-001`) est inscrit puis retiré, reproduisant sa dissolution
déjà actée dans `CAP-CORE-001-identity-registry.md`.

**Responsabilité du Core :** posséder la fiche opérationnelle du produit —
référence immuable, identité canonique associée, nom, type, propriétaire,
source, fédérabilité explicite, cycle de vie en ajout seul
(`PREPARATION` → `ACTIF` → `SUSPENDU` / `RETIRE`), environnements versionnés
(`DEVELOPPEMENT`, `RECETTE`, `PRODUCTION`) avec audience de fédération unique
parmi les environnements actifs. Chaque écriture exige une décision
CAP-CORE-004 et une preuve CAP-CORE-013 ; le module conserve en outre ses
propres bornes (refus d'auto-activation, refus par défaut sur un état
incompatible, unicité d'audience) même si une politique est mal écrite
ailleurs.

**Ce qui reste dans les satellites :** les comptes utilisateurs du produit,
ses rôles métier, ses abonnements, ses plans, ses quotas, ses transactions, ses
contenus, ses secrets en clair. Le Core n'en connaît rien.

**Ce que ce module ne possède pas :** les identités et leurs relations
(CAP-CORE-001), les jetons fédérés et sessions locales (CAP-CORE-022), les
politiques d'autorisation (CAP-CORE-004), les contrats intercapacités complets
(CAP-CORE-009, à venir), les références de secrets et clés (CAP-CORE-016, à
venir).

**Données possédées :**

- `produit` — référence immuable, identité canonique associée (immuable),
  noms canonique et d'exploitation, type (`PORTAIL`, `SATELLITE`,
  `SERVICE_CORE`, `PARTENAIRE`, `APPLICATION_INTERNE`), propriétaire, source,
  fédération autorisée (booléen gouverné), dates de création et de
  modification ;
- `produit_cycle` — journal en ajout seul du cycle de vie : état, date
  d'effet, motif, acteur, preuve, corrélation ;
- `produit_environnement` — environnements déclarés : URL d'API, URL de santé
  facultative, audience de fédération, actif/fermé, dates de début et de fin.
  Seules `actif` et `date_fin` sont modifiées en place à la fermeture ; rien
  n'est supprimé.

**Données exclues :** aucun secret, aucun mot de passe, aucun jeton, aucune
donnée métier du satellite. Une URL de production doit être HTTPS.

**Commandes :**

- `RegistreProduits::inscrireProduit()` — création gouvernée en `PREPARATION`,
  jamais activée automatiquement ;
- `RegistreProduits::modifierProduit()` — métadonnées non immuables
  seulement ; la référence et l'identité canonique ne changent jamais ;
- `RegistreProduits::activerProduit()` — idempotente, refuse l'auto-activation
  et tout état incompatible ;
- `RegistreProduits::suspendreProduit()` — ferme immédiatement la
  fédérabilité et les jetons fédérés encore ouverts (`Federation::revoquerJetonsDuProduit()`) ;
- `RegistreProduits::retirerProduit()` — en ajout seul, irréversible, ne
  supprime rien, ne réutilise jamais la référence ;
- `RegistreProduits::declarerEnvironnement()` / `fermerEnvironnement()` —
  versionnement des environnements, HTTPS obligatoire en production, audience
  unique parmi les environnements actifs.

**Requêtes :** `resoudreProduit()`, `listerProduits()`, `resoudreEtat()`,
`resoudreHistorique()`, `listerProduitsActifs()`, `listerProduitsFederables()`,
`resoudreEnvironnements()`, `resoudreEnvironnementActif()`,
`verifierAudience()`, `verifierUtilisablePourFederation()`.

**Événements :** `PRODUITS` dans le journal opérationnel —
`PRODUIT_INSCRIT`, `PRODUIT_MODIFIE`, `PRODUIT_ACTIVE`, `PRODUIT_SUSPENDU`,
`PRODUIT_RETIRE`, `ENVIRONNEMENT_PRODUIT_DECLARE`,
`ENVIRONNEMENT_PRODUIT_FERME`, `OPERATION_PRODUIT_REFUSEE`. Chaque événement
porte l'acteur, l'action, la ressource, la décision, la politique, la preuve,
la corrélation et la date. Aucun secret n'y figure.

**Dépendances :** CAP-CORE-001 (identité canonique du produit), CAP-CORE-004
(décision), CAP-CORE-013 (preuve), CAP-CORE-022 (consommateur du catalogue et
de la fermeture de jetons à la suspension/au retrait).

**Autorisations :** `POL-PRODUITS-V1`, portée provisoirement par la source
technique versionnée `core/registre-normes/resources/index-baseline-v1.json`
et évaluée par CAP-CORE-004, tant que CAP-CORE-007 reste `NO GO`. Toutes les
commandes gouvernées sont réservées à `AUT-GAMAD-001` par la politique ; le
code oppose en outre ses propres bornes, notamment le refus d'auto-activation
(`producteur === reference`).

**Comportement en panne :** une décision, une preuve ou un registre
indisponible ferme l'opération (`503`) sans écriture partielle. Une commande
sans dossier gouverné (`politique`, `producteur`, `source`, `preuve`) est
refusée avant toute tentative d'écriture.

**Sauvegarde et restauration :** le registre vit dans son propre magasin
(`PRODUCT_REGISTRY_URL` / `PRODUCT_REGISTRY_PATH`), distinct de l'index, des
identités, de l'accès et du journal. `php artisan core:fondation:migrer`
applique sa migration ; la readiness (`/api/v1/health/ready`) la vérifie ;
`ops/core-foundation/backup.sh` et `restore-drill.sh` couvrent une cinquième
cible (`produits`) au même titre que les quatre autres ; l'exercice complet
(sauvegarde réelle, contrôle d'empreinte, restauration isolée, lecture
minimale) a été rejoué en local sur PostgreSQL 16 pendant ce chantier.
`core:fondation:importer-sqlite --produits=` importe un magasin SQLite
existant vers une cible PostgreSQL vide.

**Bootstrap :** `php artisan core:produits:bootstrap` reprend les quatre
produits déjà connus de l'index sans en inventer de nouveaux — voir
`core/registre-produits/README.md`. Idempotent : le rejouer ne crée aucun
doublon et ne réécrit aucune ligne de cycle déjà présente.

**Code actuel :** `core/registre-produits/`,
`apps/console-laravel/app/Application/Produits/AccesProduits.php`,
`apps/console-laravel/app/Http/Controllers/Api/V1/ProduitController.php`,
`apps/console-laravel/app/Http/Controllers/ProduitConsoleController.php`,
`apps/console-laravel/resources/views/produits/`, routes `/api/v1/produits*`
et `/produits*` (console), commande `core:produits:bootstrap`,
`core/registre-federation/src/Federation.php` (raccordé au registre).

**Écran d'administration :** `Produits` dans la console — liste filtrable par
état et type, fiche par produit avec cycle de vie, métadonnées modifiables,
environnements, historique daté. Les actions de gouvernance ne s'affichent
qu'à l'autorité ou au propriétaire du produit. L'écran n'ouvre aucun chemin
parallèle : il appelle le même cas d'usage gouverné (`AccesProduits`) que
l'API v1.

**Tests actuels :**

- `core/registre-produits/tests/produits_p3.php` — 16 épreuves et
  contre-épreuves, raccordée à la CI ;
- `apps/console-laravel/tests/Integration/produits_v1_p1.php` — parcours HTTP
  complet, de l'inscription au retrait, raccordé à la CI ;
- `apps/console-laravel/tests/Integration/produits_console_p1.php` — écran
  d'administration, raccordé à la CI ;
- `core/registre-federation/tests/federation_p3.php`,
  `apps/console-laravel/tests/Integration/federation_v1_p1.php` et
  `federation_console_p1.php` — adaptées pour construire leurs fixtures via
  `RegistreProduits` plutôt que par un marqueur de texte, et toujours vertes ;
- `apps/console-laravel/tests/Integration/api_v1_p1.php`,
  `migration_config_cache_p1.php`, `reindexation_baseline_p1.php`,
  `ctr01_baseline_p1.php`, `ctr03_baseline_p1.php` — adaptées pour le
  cinquième magasin et les huit nouvelles règles `POL-PRODUITS-V1` dans la
  baseline, toujours vertes ;
- `apps/console-laravel/tests/Integration/postgresql_p0.sh` /
  `postgresql_p0.php` — cinq magasins PostgreSQL réels, sauvegarde et
  restauration, rejoués en local pendant ce chantier.

**État réel :** `GO` — le code, les gardes (SQLite) et l'exercice PostgreSQL
réel (sauvegarde/restauration) sont verts, et la CI GitHub complète (11
contrôles : gardes de capacité CAP-CORE-001/003/004/005/006/007/011/022,
socle-console-API, PostgreSQL réel, syntaxe PHP) a été observée verte sur
`claude/cap-core-011-products-registry-go` (PR #58) le 2 août 2026, après
correction d'un test (`console_ux_p1.php`) qui ne raccordait pas encore
`PRODUCT_REGISTRY_PATH` et passait localement par accident sur un fichier
SQLite de repli laissé par une exécution précédente — la CI, sur un clone
propre, l'a détecté correctement.

**Manques non bloquants :**

- `Federation::ouvrir()` (CAP-CORE-022) ne vérifie pas encore l'environnement
  ni l'audience déclarés par CAP-CORE-011 pour l'ouverture elle-même — seules
  l'existence, l'état `ACTIF` et `federation_autorisee` gouvernent la
  fédérabilité aujourd'hui. `verifierUtilisablePourFederation($ref, $environnement)`
  et `verifierAudience()` existent et sont testées, mais aucun appelant ne les
  invoque encore pour un contrôle par environnement précis ;
- aucun environnement de production n'est déclaré pour GamaDrive : le Core ne
  connaît pas d'URL d'API GamaDrive réelle, et ce chantier n'en invente
  aucune ;
- pas de suspension automatique liée à une expiration d'environnement ;
- la politique `POL-PRODUITS-V1` reste portée par la source technique
  versionnée, comme `POL-FEDERATION-SATELLITES-V1` ; elle migrera vers
  CAP-CORE-007 quand celui-ci sera `GO`.

**Prochain chantier :** ne pas commencer avant que CAP-CORE-011 soit
confirmée `GO` par la CI ; ensuite, CAP-CORE-002 (organisations).
