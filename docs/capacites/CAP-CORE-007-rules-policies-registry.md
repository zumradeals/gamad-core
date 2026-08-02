# CAP-CORE-007 — Rules / Policies Registry

Fiche établie par inspection du code, des gardes et de la CI. Elle décrit ce
qui existe, pas ce qui est souhaité.

**Référence :** `CAP-CORE-007`

**Nom :** Registre des politiques

**Objectif :** donner au Core une fiche opérationnelle persistante et
gouvernée de chaque politique technique — sa référence, son propriétaire, ses
versions numérotées et immuables une fois soumises, ses règles, son cycle de
vie en ajout seul, sa simulation obligatoire avant activation — plutôt qu'une
ligne de lecture seule de l'index documentaire reconstructible, modifiable en
éditant un fichier JSON versionné mais jamais gouvernée par une décision
CAP-CORE-004 ni prouvée par CAP-CORE-013.

**Problème résolu :** avant ce chantier, une politique et ses règles
n'existaient au Core que comme des lignes des tables `politique` et `regle`
de l'index reconstructible (`core/registre-normes`), lues directement par
`CTR-03` (CAP-CORE-004) pour rendre ses décisions. Aucune version, aucun
cycle de vie, aucune simulation, aucune écriture gouvernée : changer une
règle exigeait un correctif direct du fichier source
`index-baseline-v1.json` et un recalcul de son empreinte SHA-256 — un geste
documentaire, jamais un acte gouverné, tracé et prouvé. `CTR-03` exerçait en
outre un rapprochement lexical par sous-chaîne (`str_contains`) entre
l'action demandée et l'action réglée, jamais exercé par aucun appelant réel
du dépôt, mais laissant ouverte une correspondance approchée dans un moteur
qui décide d'une permission.

**Responsabilité du Core :** posséder la fiche persistante de chaque
politique — référence immuable, propriétaire (identité CAP-CORE-001), source
descriptive — et ses versions : format `X.Y.Z`, immuables dès la soumission
(empreinte de contenu figée), un cycle en ajout seul
(`BROUILLON → EN_VALIDATION → ACTIVE → SUSPENDUE → REMPLACEE → RETIREE`), au
plus une version `ACTIVE` par politique à tout instant, remplacement
atomique de l'ancienne version active dans la même transaction que
l'activation de la nouvelle, et une simulation réussie de la version exacte
exigée avant toute activation. Chaque écriture exige une décision
CAP-CORE-004 et une preuve CAP-CORE-013 ; le module conserve en outre ses
propres bornes (immutabilité post-`BROUILLON`, simulation requise avant
activation, refus par défaut) même si une politique est mal écrite ailleurs.

**Responsabilité de CTR-03 (CAP-CORE-004) :** lire exclusivement ce magasin
persistant pour rendre ses décisions — il ne lit plus jamais `politique` ni
`regle` depuis l'index documentaire. Seules les versions dont l'état courant
est `ACTIVE` sont considérées. La correspondance entre l'action demandée et
l'action réglée est désormais une égalité stricte après normalisation des
deux côtés (`Ctr03::normaliser()` appliquée à la fois à la demande et à
chaque règle lue) ; le rapprochement par sous-chaîne a été retiré.

**Ce qui reste dans les satellites et les autres capacités :** les règles
métier détaillées et les moteurs de tarification des satellites, les
contrats intercapacités complets (CAP-CORE-009, à venir), le vocabulaire
canonique (CAP-CORE-010, à venir). Ce registre ne porte que les politiques
techniques communes déjà exploitées par le Core.

**Données possédées :**

- `politique` — référence immuable, libellé, domaine facultatif, propriétaire
  (CAP-CORE-001), source descriptive libre, politique d'inscription,
  producteur, preuve, dates de création et de modification ;
- `politique_version` — versions en ajout seul, format `X.Y.Z`, description
  et date d'effet prévue facultatives, empreinte de contenu (SHA-256 du jeu
  de règles canonique, ordonné) figée à la soumission ;
- `regle_politique` — règles d'une version précise, en ajout seul tant que la
  version reste `BROUILLON` : effet (`PERMET`/`REFUSE`), action, sujet
  (référence ou type), ressource facultative, motif obligatoire ;
- `politique_version_cycle` — journal en ajout seul du cycle d'une version :
  état, date d'effet, motif, acteur, preuve, corrélation ; l'état courant est
  toujours la dernière ligne par date d'effet, jamais une valeur réécrite ;
- `politique_simulation` — simulations en ajout seul : jeu de cas, résultat
  (`REUSSIE`/`ECHEC`/`INCOMPLETE`), résumé des divergences le cas échéant ;
  une activation exige au moins une simulation `REUSSIE` de la version exacte
  qu'elle active.

**Données exclues :** aucun secret, aucun mot de passe, aucun jeton. Le
schéma ne porte structurellement aucune colonne pouvant recevoir un
identifiant.

**Commandes (`RegistrePolitiques`) :**

- `inscrirePolitique()` — création gouvernée sans version, refuse un
  propriétaire inconnu de CAP-CORE-001 et une référence déjà utilisée ;
- `creerVersion()` — naît en `BROUILLON`, format `X.Y.Z` exigé, refuse une
  version déjà utilisée pour cette politique ;
- `ajouterRegle()` / `modifierRegle()` — seule une version `BROUILLON` les
  accepte ; refuse un effet hors la liste close, une action vide, un motif
  absent, un ordre déjà utilisé ;
- `soumettreVersion()` — exige au moins une règle, fige l'empreinte de
  contenu, passe la version en `EN_VALIDATION` : elle devient immuable ;
  idempotente ;
- `simulerVersion()` — n'agit que sur une version `EN_VALIDATION`, rejoue un
  jeu de cas explicite contre les règles de cette version exacte seule
  (jamais les versions actives), sans aucun effet de bord sur une décision
  réelle ;
- `activerVersion()` — refuse sans simulation `REUSSIE` de la version exacte
  (`SIMULATION_MANQUANTE`) ; remplace l'ancienne version active
  (`REMPLACEE`) et active la nouvelle dans la même transaction ; idempotente
  si la version est déjà active ;
- `suspendreVersion()` — seule une version `ACTIVE` se suspend ; ferme
  immédiatement la permission qu'elle portait ; idempotente ;
- `retirerPolitique()` — refuse sans version active ; retire irréversiblement
  la version active courante.

**Requêtes :** `resoudrePolitique()`, `listerPolitiques()`,
`listerVersions()`, `resoudreVersion()`, `resoudreVersionActive()`,
`resoudreReglesActives()` (introspection publique de la même donnée que
celle qui fonde la décision de CTR-03), `resoudreHistorique()`,
`resoudreSimulation()`, `diagnostiquerRegistre()` (vérifie l'invariant
central : au plus une version active par politique à l'instant présent).

**Événements :** `POLITIQUES` dans le journal opérationnel —
`POLITIQUE_INSCRITE`, `VERSION_POLITIQUE_CREEE`, `REGLE_POLITIQUE_AJOUTEE`,
`REGLE_POLITIQUE_MODIFIEE`, `VERSION_POLITIQUE_SOUMISE`,
`VERSION_POLITIQUE_SIMULEE`, `VERSION_POLITIQUE_ACTIVEE`,
`VERSION_POLITIQUE_SUSPENDUE`, `POLITIQUE_RETIREE`,
`OPERATION_POLITIQUE_REFUSEE`. Chaque événement porte l'acteur, l'action, la
ressource, la décision, la corrélation et la date. Aucun secret n'y figure.

**Dépendances :** CAP-CORE-001 (identité canonique du propriétaire),
CAP-CORE-004 (décision — et lecteur exclusif de ce magasin), CAP-CORE-013
(preuve).

**Autorisations :** `POL-POLITIQUES-V1` gouverne ce registre lui-même — y
compris son écran et son API — et n'existait pas avant ce chantier : elle
n'est reprise d'aucun historique, elle est l'auto-gouvernance dont ce
registre a lui-même besoin pour ne pas rester fermé à `403` dès sa première
écriture gouvernée. Huit règles, une par action réellement soumise à CTR-03
par `AccesPolitiques` (inscrire, créer une version, modifier une version en
brouillon, soumettre, simuler, activer, suspendre, retirer), chacune réservée
à `AUT-GAMAD-001`. Bootstrapée par `core:politiques:bootstrap` au même titre
que les huit politiques historiques.

**Comportement en panne :** une décision, une preuve ou un registre
indisponible ferme l'opération (`503`) sans écriture partielle. Une commande
sans dossier gouverné (`politique`, `producteur`, `source`, `preuve`) est
refusée avant toute tentative d'écriture. Une politique, une version ou une
règle inconnue rend `404` ; une référence ou une version déjà utilisée rend
`409` ; un dossier invalide, une version immuable ou une simulation manquante
rendent `422`.

**Sauvegarde et restauration :** le registre vit dans son propre magasin
(`POLICY_REGISTRY_URL` / `POLICY_REGISTRY_PATH`), distinct de l'index, de
l'accès, des identités, des produits, des sources et du journal. `php
artisan core:fondation:migrer` applique sa migration ; la readiness
(`/api/v1/health/ready`) la vérifie comme septième cible ;
`ops/core-foundation/backup.sh` et `restore-drill.sh` couvrent une septième
cible (`politiques`) au même titre que les six autres ; l'exercice complet
(sauvegarde réelle, contrôle d'empreinte, restauration isolée, lecture
minimale) a été rejoué en local sur PostgreSQL 16 pendant ce chantier.
`core:fondation:importer-sqlite --politiques=` importe un magasin SQLite
existant vers une cible PostgreSQL vide.

**Bootstrap :** `php artisan core:politiques:bootstrap` reprend fidèlement,
sans en inventer, les huit politiques et quarante-deux règles déjà exploitées
avant ce chantier, depuis une photographie figée et vérifiée par empreinte
SHA-256 (`core/registre-politiques/resources/bootstrap-politiques-v1.json`,
capturée depuis l'index documentaire avant le retrait de ses tables
`politique`/`regle`) — puis authentifie et active `POL-POLITIQUES-V1`
(auto-gouvernance, voir « Autorisations »). Pour chaque politique reprise :
inscription, création de la version historique, ajout de ses règles,
soumission, simulation de reprise (chaque règle rejouée comme son propre cas
attendu — une preuve de reproduction fidèle, pas un jugement métier nouveau),
puis activation. Idempotent : le rejouer ne crée aucun doublon et ne
réactive pas une version déjà active.

**Code actuel :** `core/registre-politiques/`,
`core/registre-autorisation/src/Ctr03.php` (réécrit pour ce chantier),
`apps/console-laravel/app/Application/Politiques/AccesPolitiques.php`,
`apps/console-laravel/app/Http/Controllers/Api/V1/PolitiqueController.php`,
`apps/console-laravel/app/Http/Controllers/PolitiqueConsoleController.php`,
`apps/console-laravel/resources/views/politiques/`, routes
`/api/v1/politiques*` et `/politiques*` (console), commande
`core:politiques:bootstrap`.

**Écran d'administration :** `Politiques` dans la console — liste filtrable
par visibilité (version active, propriétaire, autorité), fiche par politique
avec ses versions et son historique, fiche par version avec ses règles et les
actions de cycle (soumettre, simuler, activer, suspendre). Les actions de
gouvernance ne s'affichent qu'à l'autorité ; la fiche de version elle-même
n'est accessible qu'à l'autorité ou au propriétaire de la politique. L'écran
n'ouvre aucun chemin parallèle : il appelle le même cas d'usage gouverné
(`AccesPolitiques`) que l'API v1 — y compris pour `POL-POLITIQUES-V1` qui le
gouverne lui-même.

**Retrait de l'index documentaire :** les tables `politique` et `regle` ont
été retirées de `core/registre-normes/src/Schema.php`,
`BaselineOperationnelle.php` et `index-baseline-v1.json`, une fois tous les
consommateurs identifiés migrés vers ce registre persistant. L'empreinte
SHA-256 de la baseline a été recalculée en conséquence
(`docs/06-transition-hors-genesis-ii.md` reste le pointeur vers l'historique
Git antérieur).

**Tests actuels :**

- `core/registre-politiques/tests/politiques_p3.php` — garde de capacité :
  bootstrap fidèle des huit politiques et quarante-deux règles, idempotence,
  inscription gouvernée, validation des versions et des règles, immutabilité
  post-soumission, simulation obligatoire avant activation (y compris les
  résultats `INCOMPLETE` et `ECHEC`), remplacement atomique d'une version
  active, suspension, retrait irréversible, évaluation à correspondance
  exacte (jamais approchée), CTR-03 raccordé à ce seul magasin, absence de
  secrets, reconstruction de la baseline sans perte du registre,
  contre-épreuve ; raccordée à la CI ;
- `apps/console-laravel/tests/Integration/politiques_v1_p1.php` — parcours
  HTTP complet, de l'inscription au retrait, en passant par la version, les
  règles, la soumission, la simulation et l'activation, avec vérification
  qu'une version activée par l'API gouverne immédiatement une vraie décision
  CTR-03 ; raccordé à la CI ;
- `apps/console-laravel/tests/Integration/politiques_console_p1.php` — écran
  d'administration, même parcours depuis la console, raccordé à la CI ;
- `core/registre-autorisation/tests/autorisation_p3.php`,
  `core/registre-produits/tests/produits_p3.php`,
  `core/registre-federation/tests/federation_p3.php` — adaptées pour
  bootstrapper les politiques réelles dans ce registre avant d'éprouver leurs
  propres décisions gouvernées, toujours vertes ;
- `apps/console-laravel/tests/Integration/reindexation_baseline_p1.php`,
  `ctr01_baseline_p1.php`, `ctr03_baseline_p1.php` — adaptées à l'absence de
  `politique`/`regle` dans l'index reconstructible ;
- `apps/console-laravel/tests/Integration/api_v1_p1.php`, `console_ux_p1.php`,
  `acces_console_p1.php`, `continuite_console_p1.php`,
  `federation_v1_p1.php`, `federation_console_p1.php`, `produits_v1_p1.php`,
  `produits_console_p1.php`, `sources_v1_p1.php`, `sources_console_p1.php`,
  `migration_config_cache_p1.php` — adaptées pour le septième magasin et pour
  bootstrapper les politiques réelles avant d'exercer tout parcours gouverné,
  toujours vertes ;
- `apps/console-laravel/tests/Integration/postgresql_p0.sh` /
  `postgresql_p0.php` — sept magasins PostgreSQL réels, sauvegarde et
  restauration, rejoués en local pendant ce chantier.

**État réel :** `GO` — le code, la garde SQLite et les intégrations HTTP et
console sont vertes en local, ainsi que l'exercice PostgreSQL réel
(sauvegarde/restauration sur les sept magasins). Voir le rapport de chantier
de la PR pour la confirmation CI GitHub.

**Manques non bloquants :**

- aucun contrat CAP-CORE-009 formel entre producteurs et consommateurs de
  politiques : la relation reste portée par le code et cette fiche ;
- `PolitiqueAdministration::ACTION_LIRE` et `ACTION_VERSION_REMPLACER` sont
  déclarées dans le vocabulaire fermé mais ne sont soumises à CTR-03 par
  aucun appelant réel : les lectures ne passent pas par une décision, et le
  remplacement d'une version active est aujourd'hui un effet de bord de
  `ACTION_VERSION_ACTIVER`, pas une action distincte.

**Prochain chantier :** ne pas commencer avant que CAP-CORE-007 soit
confirmée `GO` par la CI ; ensuite, selon la priorité déjà proposée par le
catalogue.
