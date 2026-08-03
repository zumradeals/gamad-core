# CAP-CORE-010 — Canonical Vocabulary

Fiche établie par inspection du code, des gardes et de la CI. Elle décrit ce
qui existe, pas ce qui est souhaité.

**Référence :** `CAP-CORE-010`

**Nom :** Registre du vocabulaire canonique

**Objectif :** donner au Core une fiche persistante, versionnée et gouvernée
de chaque terme partagé entre plusieurs capacités ou entre le Core et un
produit — son code machine stable, sa définition, ses libellés localisés,
ses alias explicites, ses relations sémantiques, ses correspondances
externes et ses usages déclarés — plutôt que des valeurs dispersées et
non gouvernées dans des contraintes SQL `CHECK`, des constantes PHP, des
enums OpenAPI et des chaînes de caractères, sans aucun registre commun
capable de dire ce que signifie un code, qui le consomme, ou si une
modification romprait un consommateur.

**Problème résolu :** avant ce chantier, `CAP-CORE-001`, `CAP-CORE-006`,
`CAP-CORE-007`, `CAP-CORE-009` et `CAP-CORE-011` définissaient chacune ses
propres listes fermées (types, états, niveaux, rôles) à la fois dans une
contrainte SQL `CHECK` et dans une classe `Politique*` PHP, sans lien entre
elles, sans définition partagée, sans libellé localisé gouverné et sans
détection d'une dérive entre le code d'une capacité et un contrat qui
l'utilise. Un nouveau code pouvait être accepté par une capacité et rejeté
par une autre sans qu'aucun mécanisme ne le signale.

**Responsabilité du Core :** posséder la fiche persistante de chaque
vocabulaire — référence immuable, namespace unique, nom, domaine, portée
(`CORE`, `ECOSYSTEME`, `CONTRAT`, `CAPACITE`, `PRODUIT_PARTAGE`),
propriétaire (identité CAP-CORE-001), source descriptive — et ses versions :
format `X.Y.Z`, immuables dès la soumission (empreinte de contenu figée sur
les termes réels), un cycle en ajout seul (`BROUILLON → EN_VALIDATION →
ACTIVE → DEPRECIEE/REMPLACEE → RETIREE`), au plus une version `ACTIVE` par
vocabulaire à tout instant. L'activation exige une analyse de compatibilité,
une projection et une conformité `CONFORME` enregistrées pour la version
exacte. Chaque terme porte un code stable (`MAJUSCULES_SOULIGNEES`), une
définition obligatoire, un type sémantique (liste close), des libellés
localisés, des alias explicites non ambigus, des relations sémantiques
acycliques et des correspondances externes qualifiées (`EXACT`,
`APPROXIMATIF`, `PERTE_INFORMATION`, `INTERDIT`).

**Ce que ce registre ne possède pas :** les identités, les produits, les
sources, les politiques, les contrats, les décisions individuelles, les
données métier, les secrets, les clés, les scores de confiance, les
résultats du Matching, les règles exécutables ni les taxonomies privées
d'un satellite sans utilité transversale. Ajouter un terme au vocabulaire ne
crée jamais automatiquement une permission, une transition métier ou un
comportement nouveau chez un consommateur : `PolitiqueVocabulaire` distingue
structurellement un terme *actif dans le vocabulaire* d'un terme *supporté
par un consommateur précis*, et `ValidateurTerme` refuse toute définition
ressemblant à une expression exécutable ou à un secret réel.

**Données possédées :**

- `vocabulaire` — référence immuable, namespace unique, nom, domaine,
  portée, propriétaire (CAP-CORE-001), source, description facultative ;
- `vocabulaire_version` — versions en ajout seul, format `X.Y.Z`, empreinte
  de contenu (SHA-256) figée à la soumission, immuable ensuite ;
- `vocabulaire_version_cycle` — journal en ajout seul du cycle d'une
  version : état, date d'effet, motif, acteur, politique, preuve,
  corrélation ;
- `terme` — référence stable, code unique dans sa version, définition
  obligatoire, type sémantique (`TYPE`, `ETAT`, `ACTION`, `FINALITE`,
  `RELATION`, `NIVEAU`, `RESULTAT`, `ROLE`, `CATEGORIE`, `ERREUR`,
  `ENVIRONNEMENT`, `CLASSIFICATION`), lien facultatif vers le terme qui le
  remplace ;
- `terme_libelle` — libellés localisés, un principal par locale, sans effet
  sur le code ;
- `terme_alias` — anciens codes, abréviations, codes externes, orthographes
  historiques ; explicites, sourcés, non ambigus dans un même vocabulaire ;
- `terme_relation` — `PLUS_LARGE_QUE`, `PLUS_ETROIT_QUE`,
  `EQUIVALENT_EXPLICITE`, `REMPLACE`, `ASSOCIE_A`, `INCOMPATIBLE_AVEC` ;
  aucune auto-relation, aucun cycle hiérarchique ;
- `terme_mapping_externe` — correspondance avec un système externe, sens
  (`ENTRANT`, `SORTANT`, `BIDIRECTIONNEL`) et statut qualifiés
  (`EXACT`/`APPROXIMATIF`/`PERTE_INFORMATION`/`INTERDIT`) ;
- `terme_usage` — déclare qu'un terme est utilisé par une capacité, un
  contrat, une politique ou un produit, avec un type d'usage (`ENTREE`,
  `SORTIE`, `REGLE`, `ETAT_PERSISTE`, `AFFICHAGE`, `MAPPING`, `EVENEMENT`,
  `SIGNAL`) ; sert de base au calcul d'impact d'un retrait ;
- `vocabulaire_compatibilite` — analyses en ajout seul, liées à l'empreinte
  de la version analysée, résultat structuré ;
- `vocabulaire_conformite` — conformités en ajout seul, résultat
  (`CONFORME`, `NON_CONFORME`, `INCOMPLET`) ;
- `vocabulaire_projection` — projections dérivées en ajout seul (`JSON`,
  `PHP_CONSTANTS`, `OPENAPI_ENUM`, `SQL_CHECK`, `DOCUMENTATION`), jamais
  modifiées en place.

**Données exclues :** aucun secret, aucun mot de passe, aucun jeton, aucune
donnée métier réelle. Le schéma ne porte structurellement aucune colonne
pouvant recevoir un identifiant ; `ValidateurTerme` refuse en outre toute
définition ressemblant à un secret réel ou à une expression exécutable.

**Commandes (`RegistreVocabulaire`) :** `inscrireVocabulaire()`,
`creerVersion()`, `ajouterTerme()`, `evoluerTerme()` (fait naître une
référence de terme neuve dans une version suivante, reliée à l'ancienne par
`terme_relation` de type `REMPLACE`, sans jamais réécrire l'historique),
`ajouterLibelle()`, `ajouterAlias()`, `declarerRelation()`,
`declarerMappingExterne()`, `declarerUsage()`, `soumettreVersion()` (fige
l'empreinte, passe en `EN_VALIDATION`, idempotente), `analyserCompatibilite()`,
`genererProjection()`, `enregistrerConformite()`, `activerVersion()` (refuse
sans analyse, sans projection obligatoire, sans conformité `CONFORME` ;
atomique ; idempotente), `deprecierVersion()`, `retirerVersion()`,
`deprecierTerme()`, `retirerTerme()` (refuse tant qu'un usage obligatoire
actif dépend du terme).

**Analyse de compatibilité (`AnalyseurCompatibilite`) :** comparaison
structurelle entre la version analysée et la version active courante du
vocabulaire (jamais « la version précédemment créée »). `RUPTURE` : code
canonique modifié pour une référence stable, terme actif disparu, type
sémantique modifié. `ADAPTATION_REQUISE` : définition modifiée.
`COMPATIBLE` : ajout de terme, ajout de libellé, absence de version
précédente active (première version d'un vocabulaire, rien à rompre).

**Requêtes :** `resoudreVocabulaire()`, `listerVocabulaires()`,
`listerVersions()`, `resoudreVersion()`, `resoudreVersionActive()`,
`resoudreTerme()`, `resoudreCodeActif()` (résout un code dans la version
active sans dupliquer le registre), `resoudreHistorique()`,
`resoudreCompatibilite()`, `resoudreConformite()`, `diagnostiquerRegistre()`
(vérifie l'invariant central : au plus une version active par vocabulaire).

**Événements :** `VOCABULAIRE` dans le journal opérationnel —
`VOCABULAIRE_INSCRIT`, `VERSION_VOCABULAIRE_CREEE`, `TERME_AJOUTE`,
`TERME_EVOLUE`, `LIBELLE_TERME_AJOUTE`, `ALIAS_TERME_AJOUTE`,
`RELATION_TERME_DECLAREE`, `MAPPING_TERME_DECLARE`, `USAGE_TERME_DECLARE`,
`VERSION_VOCABULAIRE_SOUMISE`, `COMPATIBILITE_VOCABULAIRE_ANALYSEE`,
`PROJECTION_VOCABULAIRE_GENEREE`, `CONFORMITE_VOCABULAIRE_ENREGISTREE`,
`VERSION_VOCABULAIRE_ACTIVEE`, `VERSION_VOCABULAIRE_DEPRECIEE`,
`VERSION_VOCABULAIRE_RETIREE`, `TERME_DEPRECIE`, `TERME_RETIRE`,
`OPERATION_VOCABULAIRE_REFUSEE`. Aucun secret ni donnée métier réelle n'y
figure ; le parcours complet (inscription → activation) est vérifié chaîné
dans l'audit par `vocabulaire_v1_p1.php`.

**Dépendances :** CAP-CORE-001 (identité canonique du propriétaire),
CAP-CORE-004 (décision), CAP-CORE-006 (source), CAP-CORE-007 (politique
d'autorisation `POL-VOCABULAIRE-V1`, portée par le registre des
politiques), CAP-CORE-009 (contrats consommateurs des termes), CAP-CORE-011
(produits consommateurs), CAP-CORE-013 (preuve).

**Autorisations :** `POL-VOCABULAIRE-V1` gouverne ce registre lui-même — y
compris son écran et son API. Seize actions, une par action réellement
soumise à CTR-03 par `AccesVocabulaire`, chacune réservée à
`AUT-GAMAD-001`. Bootstrapée par `core:vocabulaire:bootstrap`, qui l'inscrit
et l'active dans le registre des politiques (CAP-CORE-007) avant de
reprendre l'inventaire des vocabulaires.

**Comportement en panne :** une décision, une preuve ou un registre
indisponible ferme l'opération (`503`) sans écriture partielle. Un
vocabulaire, une version ou un terme inconnu rend `404` ; une référence, un
namespace, une version ou un code déjà utilisé rend `409` ; un dossier
invalide, une version immuable, une analyse, une projection ou une
conformité manquante rendent `422`. Un terme inconnu est toujours refusé en
écriture — aucun rapprochement flou, aucun alias humain utilisé pour une
décision de sécurité, aucune valeur par défaut permissive.

**Sauvegarde et restauration :** le registre vit dans son propre magasin
(`VOCABULARY_REGISTRY_URL` / `VOCABULARY_REGISTRY_PATH`), distinct des huit
autres. `php artisan core:fondation:migrer` applique sa migration ; la
readiness (`/api/v1/health/ready`) la vérifie comme neuvième cible ;
`ops/core-foundation/backup.sh` et `restore-drill.sh` couvrent une neuvième
cible (`vocabulaire`) au même titre que les huit autres.
`core:fondation:importer-sqlite --vocabulaire=` importe un magasin SQLite
existant vers une cible PostgreSQL vide.

**Bootstrap :** `php artisan core:vocabulaire:bootstrap` établit d'abord
`POL-VOCABULAIRE-V1` dans le registre des politiques, puis reprend
fidèlement, sans en inventer, vingt-quatre vocabulaires (cent trente-deux
termes) depuis
`core/registre-vocabulaire/resources/bootstrap-vocabulaire-v1.json`, chacun
relié par `source_reference` à une constante PHP réelle d'une capacité déjà
`GO` (`PolitiqueInscription`, `PolitiquePolitiques` — effets et états —,
`PolitiqueSources`, `PolitiqueContrats`, `PolitiqueProduits`). Le sentinel
`INDETERMINE` du type d'identité n'a volontairement pas été repris comme
terme métier : c'est une valeur de repli technique, pas un concept
canonique. Pour chaque vocabulaire repris : inscription, création de la
version historique, déclaration des termes réels, soumission, analyse
(`COMPATIBLE` — première version, rien à rompre), projection JSON,
conformité `CONFORME`, puis activation. Idempotent.

**Code actuel :** `core/registre-vocabulaire/`,
`apps/console-laravel/app/Application/Vocabulaire/AccesVocabulaire.php`,
`apps/console-laravel/app/Http/Controllers/Api/V1/VocabulaireController.php`,
`apps/console-laravel/app/Http/Controllers/VocabulaireConsoleController.php`,
`apps/console-laravel/resources/views/vocabulaires/`, routes
`/api/v1/vocabulaires*`, `/api/v1/termes*` et `/vocabulaires*` (console),
commande `core:vocabulaire:bootstrap`.

**Écran d'administration :** `Vocabulaires` dans la console — liste
filtrable, fiche par vocabulaire avec ses versions, fiche par version avec
ses termes, libellés, alias, usages et actions de cycle (ajouter un terme,
faire évoluer un terme, soumettre, analyser, générer une projection,
enregistrer une conformité, activer, déprécier, retirer). Une confirmation
explicite est exigée pour l'activation, la dépréciation et le retrait.
L'écran n'ouvre aucun chemin parallèle : il appelle le même cas d'usage
gouverné (`AccesVocabulaire`) que l'API v1.

**Tests actuels :**

- `core/registre-vocabulaire/tests/vocabulaire_p3.php` — garde de capacité,
  80 épreuves : bootstrap et idempotence, référence/namespace uniques,
  propriétaire et source obligatoires, cycle de version
  BROUILLON→immuabilité, codes/définitions/types sémantiques valides et
  invalides, libellé principal unique par locale, alias non ambigu,
  relations acycliques, mappings qualifiés, usages déclarés, quatre règles
  de détection de compatibilité par l'algorithme réel (changement de code,
  disparition de terme et changement de type sémantique en rupture ;
  changement de définition en adaptation), évolution de terme entre
  versions reconnue par lignée (pas confondue avec une suppression),
  projections PHP/SQL dérivées du contenu réel, activation refusée sans
  analyse/projection/conformité, invariant une seule version active,
  dépréciation et retrait de version et de terme, retrait refusé tant qu'un
  usage obligatoire actif dépend du terme, rollback de transaction,
  continuité d'une copie physique du magasin, absence de secrets,
  reconstruction de la baseline sans perte, contre-épreuve ; raccordée à la
  CI ;
- `apps/console-laravel/tests/Integration/vocabulaire_v1_p1.php` — parcours
  HTTP complet sur deux versions successives, de l'inscription au retrait,
  en passant par l'évolution d'un terme, la soumission, l'analyse, la
  projection, la conformité, l'activation, la dépréciation et le retrait ;
  vérifie le chaînage dans l'audit CAP-CORE-013 ; raccordé à la CI ;
- `apps/console-laravel/tests/Integration/vocabulaire_console_p1.php` —
  écran d'administration, même parcours depuis la console, y compris un
  refus d'accès pour un acteur ni autorité ni propriétaire ; raccordé à la
  CI ;
- `apps/console-laravel/tests/Integration/vocabulaire_drift_p1.php` —
  dérive entre les 29 routes Laravel réelles de `/api/v1/vocabulaires*` et
  `/api/v1/termes*` et `openapi/core-v1.yaml` ; raccordé à la CI ;
- `apps/console-laravel/tests/Integration/api_v1_p1.php` — adaptée pour le
  neuvième magasin (readiness, huit → neuf cibles) ;
- `apps/console-laravel/tests/Integration/console_ux_p1.php` — adaptée pour
  le neuvième magasin (accueil console) ;
- `apps/console-laravel/tests/Integration/migration_config_cache_p1.php` —
  adaptée pour `VOCABULARY_REGISTRY_URL` ;
- `apps/console-laravel/tests/Integration/postgresql_p0.sh` /
  `postgresql_p0.php` — neuf magasins PostgreSQL réels, sauvegarde et
  restauration.

**État réel :** `GO` — la garde de capacité (80 épreuves), les trois
intégrations dédiées (HTTP, console, dérive OpenAPI), l'ensemble des
intégrations socle de la CI et l'exercice PostgreSQL réel (neuf magasins,
sauvegarde et restauration isolée sur les neuf cibles) sont verts en local
le 3 août 2026. Les trois régressions introduites par l'ajout d'un neuvième
magasin dans des tests d'intégration préexistants
(`migration_config_cache_p1.php`, `api_v1_p1.php`, `console_ux_p1.php`, qui
ignoraient encore `VOCABULARY_REGISTRY_URL`) ont été corrigées et revérifiées
par exécution réelle avant ce commit. Voir le rapport de chantier de la PR
pour la confirmation CI GitHub.

**Manques non bloquants :**

- `CAP-CORE-002` (organisations) et `CAP-CORE-012` (realms), consommateurs
  prévus de ce vocabulaire, ne sont pas encore codés — ils n'existent pas
  encore pour le consommer ;
- `CAP-CORE-021` (Matching) ne consomme pas encore ce registre ;
- l'analyse de compatibilité ne couvre que les quatre règles listées dans la
  fiche de codage (code, disparition, type sémantique, définition) ; les
  changements de relation, de mapping, de portée ou de finalité cités en
  section 16.10 de la fiche ne sont pas détectés séparément par
  `AnalyseurCompatibilite` ;
- la dérive SQL (`SQL_CHECK` projeté face aux contraintes `CHECK` réellement
  en vigueur dans `SchemaProduits`, `SchemaSources`, etc.) n'est pas vérifiée
  automatiquement par une garde dédiée — seule la projection SQL est
  produite et son contenu contrôlé manuellement dans la garde de capacité ;
- les vingt-quatre vocabulaires bootstrapés sont des projections descriptives
  du vocabulaire existant ; aucune capacité consommatrice (CAP-CORE-001,
  006, 007, 009, 011) n'a encore été modifiée pour lire ses codes depuis ce
  registre plutôt que depuis ses propres constantes — ce chantier décrit le
  vocabulaire, il ne migre pas encore les lecteurs.

**Prochain chantier :** ne pas commencer avant que CAP-CORE-010 soit
confirmée `GO` par la CI GitHub ; ensuite, selon l'ordre fixé par
`docs/capacites/chantiers/README.md`, `CAP-CORE-002` (registre des
organisations).
