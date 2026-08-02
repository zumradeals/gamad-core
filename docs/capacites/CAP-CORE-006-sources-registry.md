# CAP-CORE-006 — Sources Registry

Fiche établie par inspection du code, des gardes et de la CI. Elle décrit ce
qui existe, pas ce qui est souhaité.

**Référence :** `CAP-CORE-006`

**Nom :** Registre des sources

**Objectif :** donner au Core une fiche opérationnelle persistante et
gouvernée de chaque source — sa référence, son propriétaire, son type, son
cycle de vie, ses révisions, ses vérifications, ses finalités bornées par
consommateur et par période, et sa lignée acyclique — plutôt qu'une ligne de
lecture seule dérivée de l'index documentaire.

**Problème résolu :** avant ce chantier, une source n'existait au Core que
comme une ligne de la table `source` de l'index reconstructible
(`reference`, `titre`, `categorie`, `authenticite`, `reserve`), résolue par
`CTR-15`, lui-même dépendant des tables `norme`, `version_norme` et `statut`
du registre des normes pour son rang et son statut. Aucune écriture
gouvernée, aucun cycle de vie, aucune vérification, aucune finalité, aucune
lignée traçable.

**Correction de dépendance :** la fiche de conception exigeait que
`CAP-CORE-007` dépende de `CAP-CORE-006`, jamais l'inverse. `CTR-15` ne lit
plus aucune table du registre des normes ; il lit exclusivement le magasin
persistant de CAP-CORE-006. `CTR-04` (CAP-CORE-007) continue d'exposer
`resoudreSource()` pour les appelants existants : il délègue à `CTR-15` puis
ajoute, quand elle existe, une projection de compatibilité historique
(`rang`, `statut`, `adoption_reference`, `versionnee`) construite dans
`Ctr04.php` lui-même — jamais dans `CTR-15`. L'absence de version normative
correspondante ne fait jamais échouer la résolution.

**Responsabilité du Core :** posséder la fiche de provenance de la source —
référence immuable, nom canonique et type immuables, propriétaire (identité
CAP-CORE-001) et produit producteur (CAP-CORE-011) révisables, cycle de vie
en ajout seul (`PREPARATION` → `ACTIVE` → `SUSPENDUE` / `RETIREE`),
révisions de métadonnées en ajout seul, vérifications historisées et
expirables, finalités bornées par consommateur et par période, lignée
acyclique traçable. Chaque écriture exige une décision CAP-CORE-004 et une
preuve CAP-CORE-013 ; le module conserve en outre ses propres bornes (refus
d'auto-attestation, détection de cycle de lignée avant écriture, refus par
défaut) même si une politique est mal écrite ailleurs.

**Ce qui reste dans les satellites et les autres capacités :** les données
métier complètes produites par la source, les profils des personnes, les
fichiers, les transactions, les documents justificatifs en clair, les
secrets, les politiques de décision (CAP-CORE-004), les contrats
intercapacités complets (CAP-CORE-009, à venir), le vocabulaire canonique
des finalités (CAP-CORE-010, à venir — les références de finalité restent en
texte explicite, documenté par le consommateur, tant que CAP-CORE-010 reste
`NO GO`).

**Données possédées :**

- `source` — référence immuable, nom canonique immuable, type immuable
  (`PRODUIT_GAMAD`, `SERVICE_CORE`, `ORGANISATION`, `INSTITUTION`,
  `PARTENAIRE`, `SYSTEME_EXTERNE`, `IMPORT_GOUVERNE`, `CANAL_DECLARATIF`),
  valeur historique d'authenticité conservée telle quelle
  (`authenticite_legacy`, jamais réinterprétée), politique d'inscription,
  producteur, preuve, dates de création et de modification ;
- `source_cycle` — journal en ajout seul du cycle de vie : état, date
  d'effet, motif, acteur, politique, preuve, corrélation ;
- `source_revision` — journal en ajout seul des métadonnées révisables : nom
  d'exploitation, catégorie, description, propriétaire, produit producteur,
  réserve. Chaque correction crée une nouvelle révision numérotée ; la
  lecture courante prend la dernière révision applicable à la date demandée ;
- `source_verification` — journal en ajout seul : niveau
  (`NON_VERIFIEE`, `DECLAREE`, `CONTROLEE`, `ATTESTEE`), résultat (`VALIDE`,
  `INVALIDE`, `EXPIREE`), vérificateur, preuve, date de vérification,
  échéance facultative. Sans vérification enregistrée, le niveau courant lu
  reste `NON_VERIFIEE` par convention de lecture — rien n'est inventé ;
- `source_finalite` — finalités bornées par consommateur (produit
  CAP-CORE-011, facultatif) et par période (`date_debut`/`date_fin`) ;
  `actif`/`date_fin` sont les seules colonnes mutables, à la fermeture ;
  rien n'est supprimé ;
- `source_lignee` — relations de lignée en ajout seul
  (`DERIVEE_DE`, `AGREGE`, `REMPLACE`, `CORRIGE`), toute relation qui
  fermerait un cycle étant refusée avant écriture.

**Données exclues :** aucun secret, aucun mot de passe, aucun jeton, aucun
contenu métier de la source. Le schéma ne porte structurellement aucune
colonne pouvant recevoir un identifiant.

**Commandes :**

- `RegistreSources::inscrireSource()` — création gouvernée en `PREPARATION`,
  jamais activée automatiquement ; refuse un propriétaire inconnu de
  CAP-CORE-001 et un produit producteur inconnu ou non actif dans
  CAP-CORE-011 ;
- `RegistreSources::modifierSource()` — métadonnées révisables seulement ; la
  référence, le nom canonique et le type ne changent jamais ; chaque
  modification crée une nouvelle révision ;
- `RegistreSources::activerSource()` / `suspendreSource()` /
  `retirerSource()` — idempotentes ; le retrait est irréversible, ne
  supprime rien, et ne réutilise jamais la référence ;
- `RegistreSources::declarerFinalite()` / `fermerFinalite()` — aucune
  finalité n'est jamais implicite ; conflits de dates refusés ; consommateur
  vérifié actif dans CAP-CORE-011 ;
- `RegistreSources::enregistrerVerification()` — refuse l'auto-attestation
  (`ATTESTEE` exige un vérificateur distinct du producteur) ;
- `RegistreSources::declarerLignee()` — détecte tout cycle avant écriture par
  parcours du graphe existant.

**Requêtes :** `resoudreSource()`, `listerSources()`, `resoudreEtat()`,
`resoudreRevision()`, `resoudreRevisions()`, `resoudreVerificationCourante()`,
`resoudreVerifications()`, `resoudreFinalites()`, `resoudreLignee()`,
`listerSourcesActives()`, `listerSourcesParProduit()`,
`verifierUtilisable()` — réponse explicable énumérant chaque motif de refus
(`SOURCE_INCONNUE`, `SOURCE_SUSPENDUE`, `SOURCE_RETIREE`,
`SOURCE_EN_PREPARATION`, `FINALITE_NON_DECLAREE`, `FINALITE_EXPIREE`) ; ne
constitue pas à elle seule une autorisation, la couche applicative demande
encore une décision à CAP-CORE-004 pour l'opération qu'elle prépare.

**Événements :** `SOURCES` dans le journal opérationnel — `SOURCE_INSCRITE`,
`SOURCE_MODIFIEE`, `SOURCE_ACTIVEE`, `SOURCE_SUSPENDUE`, `SOURCE_RETIREE`,
`FINALITE_SOURCE_DECLAREE`, `FINALITE_SOURCE_FERMEE`,
`VERIFICATION_SOURCE_ENREGISTREE`, `LIGNEE_SOURCE_DECLAREE`,
`OPERATION_SOURCE_REFUSEE`. Chaque événement porte l'acteur, l'action, la
ressource, la décision, la politique, la preuve, la corrélation et la date.
Aucun secret n'y figure.

**Dépendances :** CAP-CORE-001 (identité canonique du propriétaire),
CAP-CORE-004 (décision), CAP-CORE-011 (produit producteur et produit
consommateur, `GO` requis avant ce chantier), CAP-CORE-013 (preuve).
CAP-CORE-007 dépend désormais de CAP-CORE-006 pour la résolution des
sources, jamais l'inverse.

**Autorisations :** `POL-SOURCES-V1`, reprise fidèlement (chantier
CAP-CORE-007) depuis la source technique versionnée dans le registre
persistant et gouverné `core/registre-politiques`, activée, et évaluée par
CAP-CORE-004 depuis ce même magasin. Les onze règles associées réservent
chaque action gouvernée à `AUT-GAMAD-001` ; le code oppose en outre ses
propres bornes, notamment le refus d'auto-attestation et la détection de
cycle de lignée.

**Comportement en panne :** une décision, une preuve ou un registre
indisponible ferme l'opération (`503`) sans écriture partielle. Une commande
sans dossier gouverné (`politique`, `producteur`, `source`, `preuve`) est
refusée avant toute tentative d'écriture. Une source, un propriétaire ou un
produit inconnu rend `404` ; une référence déjà utilisée ou un conflit de
dates rend `409` ; un dossier invalide ou un cycle de lignée rend `422`.

**Sauvegarde et restauration :** le registre vit dans son propre magasin
(`SOURCE_REGISTRY_URL` / `SOURCE_REGISTRY_PATH`), distinct de l'index, des
identités, de l'accès, des produits et du journal. `php artisan
core:fondation:migrer` applique sa migration ; la readiness
(`/api/v1/health/ready`) la vérifie comme sixième cible ;
`ops/core-foundation/backup.sh` et `restore-drill.sh` couvrent une sixième
cible (`sources`) au même titre que les cinq autres ; l'exercice complet
(sauvegarde réelle, contrôle d'empreinte, restauration isolée, lecture
minimale) a été rejoué en local sur PostgreSQL 16 pendant ce chantier.
`core:fondation:importer-sqlite --sources=` importe un magasin SQLite
existant vers une cible PostgreSQL vide.

**Bootstrap :** `php artisan core:sources:bootstrap` reprend les sources déjà
présentes dans l'ancien index reconstructible (26 lignes au moment de ce
chantier — le nombre n'est pas fixé dans le code, il se compte à
l'exécution) sans en inventer de nouvelles : référence, titre devenu
`nom_affichage`, catégorie, valeur historique d'authenticité conservée dans
`authenticite_legacy`, réserve. Propriétaire par défaut : l'autorité
d'inscription (`AUT-GAMAD-001`), seule identité canonique disponible pour ce
lot importé. Type par défaut : `IMPORT_GOUVERNE`. Cycle cible : `ACTIVE`,
pour que les lectures déjà servies par `CTR-04` continuent de trouver une
source active. Aucune vérification n'est inventée : le niveau reste
`NON_VERIFIEE` par défaut. Idempotent : le rejouer ne crée aucun doublon.

**Code actuel :** `core/registre-sources/`,
`apps/console-laravel/app/Application/Sources/AccesSources.php`,
`apps/console-laravel/app/Http/Controllers/Api/V1/SourceController.php`,
`apps/console-laravel/app/Http/Controllers/SourceConsoleController.php`,
`apps/console-laravel/resources/views/sources/`, routes `/api/v1/sources*`
et `/registre-sources*` (console — `/sources/{reference}` reste la route web
JSON historique de CTR-04, préservée pour compatibilité), commande
`core:sources:bootstrap`, `core/registre-normes/src/Ctr04.php` (projection de
compatibilité).

**Écran d'administration :** `Sources` dans la console — liste filtrable par
état et type, fiche par source avec cycle de vie, métadonnées révisables,
finalités, vérifications, lignée amont/aval, historique daté. Les actions de
gouvernance ne s'affichent qu'à l'autorité ou au propriétaire de la source.
L'écran n'ouvre aucun chemin parallèle : il appelle le même cas d'usage
gouverné (`AccesSources`) que l'API v1.

**Tests actuels :**

- `core/registre-sources/tests/sources_p3.php` — 38 épreuves et
  contre-épreuves (bootstrap réel des 26 sources historiques, identité
  canonique, authenticité historique préservée, inscription gouvernée,
  cycle de vie, révisions en ajout seul, finalités bornées, vérifications
  expirables et non auto-attestées, lignée acyclique, refus par défaut,
  absence de secrets, découplage de CTR-15 du registre des normes,
  reconstruction de la baseline sans perte, contre-épreuve), raccordée à la
  CI ;
- `apps/console-laravel/tests/Integration/sources_v1_p1.php` — parcours HTTP
  complet, de l'inscription au retrait, en passant par les finalités, les
  vérifications et la lignée, raccordé à la CI ;
- `apps/console-laravel/tests/Integration/sources_console_p1.php` — écran
  d'administration, raccordé à la CI ;
- `apps/console-laravel/tests/Integration/api_v1_p1.php`,
  `console_ux_p1.php`, `federation_v1_p1.php`, `federation_console_p1.php`,
  `produits_v1_p1.php`, `migration_config_cache_p1.php`,
  `reindexation_baseline_p1.php`, `ctr01_baseline_p1.php`,
  `ctr03_baseline_p1.php` — adaptées pour le sixième magasin et les onze
  nouvelles règles `POL-SOURCES-V1` dans la baseline, toujours vertes ;
- `apps/console-laravel/tests/Integration/import_sqlite_p0.php` — étendue
  pour couvrir `ImportateurSqlite::importerSources()` ;
- `apps/console-laravel/tests/Integration/postgresql_p0.sh` /
  `postgresql_p0.php` — six magasins PostgreSQL réels, sauvegarde et
  restauration, rejoués en local pendant ce chantier.

**État réel :** `GO` — le code, la garde SQLite (38 épreuves) et l'exercice
PostgreSQL réel (sauvegarde/restauration sur les six magasins) sont verts en
local. Voir le rapport de chantier de la PR pour la confirmation CI GitHub.

**Manques non bloquants :**

- `POL-SOURCES-V1` reste portée par la source technique versionnée, comme
  `POL-PRODUITS-V1` ; elle migrera vers CAP-CORE-007 quand celui-ci sera
  `GO` ;
- les références de finalité restent du texte libre documenté par le
  consommateur, faute de vocabulaire canonique (CAP-CORE-010, `NO GO`) ;
- `CAP-CORE-021` (Matching) ne consomme pas encore les sources autorisées —
  `verifierUtilisable()` existe et est testée, mais aucun appelant ne
  l'invoque encore pour une décision de Matching réelle ;
- aucun contrat CAP-CORE-009 formel entre producteurs et consommateurs de
  sources : la relation reste portée par le code et cette fiche.

**Prochain chantier :** ne pas commencer avant que CAP-CORE-006 soit
confirmée `GO` par la CI ; ensuite, selon la priorité déjà proposée par le
catalogue.
