# CAP-CORE-009 — Contracts Registry

Fiche établie par inspection du code, des gardes et de la CI. Elle décrit ce
qui existe, pas ce qui est souhaité.

**Référence :** `CAP-CORE-009`

**Nom :** Registre des contrats

**Objectif :** donner au Core une fiche opérationnelle persistante et
gouvernée de chaque contrat d'échange — sa référence, son producteur
principal, ses versions immuables une fois soumises, ses parties, ses
opérations, ses schémas, ses erreurs, ses obligations, son analyse de
compatibilité, sa conformité et son cycle de vie — plutôt que des échanges
dispersés entre les classes `CTR-*`, les contrôleurs, les routes et
`openapi/core-v1.yaml`, sans aucun registre commun connaissant producteur,
consommateurs, compatibilité, dépréciation ou conformité.

**Problème résolu :** avant ce chantier, aucun registre ne savait répondre,
pour un échange donné, à « qui le produit, qui le consomme, quelle version
est active, quelles versions sont compatibles, quels consommateurs seraient
affectés par une modification, quelles preuves de conformité ont été
exécutées ». `openapi/core-v1.yaml` décrivait une partie des échanges HTTP,
sans producteur ni consommateur ni cycle de vie ni analyse de compatibilité ;
les classes `CTR-*` portaient un numéro et un nom, sans fiche ni version. Une
rupture de compatibilité ne pouvait être détectée par aucun mécanisme
systématique.

**Responsabilité du Core :** posséder la fiche persistante de chaque
contrat — référence immuable, nom, type (liste close), finalité, producteur
principal (une capacité *ou* un produit, jamais les deux), propriétaire
(identité CAP-CORE-001), source descriptive — et ses versions : format
`X.Y.Z`, immuables dès la soumission (empreinte de contenu figée sur
parties, opérations, schémas, erreurs et obligations), un cycle en ajout
seul (`BROUILLON → EN_VALIDATION → ACTIVE → DEPRECIEE → SUSPENDUE →
REMPLACEE → RETIREE`), au plus une version `ACTIVE` par contrat à tout
instant, remplacement atomique de l'ancienne version active dans la même
transaction que l'activation de la nouvelle. L'activation exige une analyse
de compatibilité et une conformité `CONFORME` enregistrées pour la version
exacte ; une analyse `RUPTURE` exige en outre un plan de migration et une
date limite explicites. Chaque écriture exige une décision CAP-CORE-004 et
une preuve CAP-CORE-013.

**Ce que ce registre ne possède pas :** les données échangées, les dossiers
métier, les identités, les produits, les sources, les politiques, les
décisions d'autorisation, les secrets, les clés, le transport réel des
événements (CAP-CORE-014, non livré) ni les résultats du Matching. Une
permission n'est jamais générée depuis un contrat : `CAP-CORE-004` décide,
ce registre décrit seulement ce qui est échangé.

**Données possédées :**

- `contrat` — référence immuable, nom, type (`COMMANDE`, `REQUETE`,
  `EVENEMENT`, `SIGNAL`, `ATTESTATION`, `REFERENCE_TEMPORAIRE`, `HTTP_API`,
  `INTERCAPACITE`), finalité, producteur principal (capacité *xor* produit),
  propriétaire (CAP-CORE-001), source descriptive libre, description
  facultative ;
- `contrat_version` — versions en ajout seul, format `X.Y.Z`, compatibilité
  annoncée par le producteur, empreinte de contenu (SHA-256) figée à la
  soumission ;
- `contrat_version_cycle` — journal en ajout seul du cycle d'une version :
  état, date d'effet, motif, plan de migration et date limite (rupture
  uniquement), acteur, preuve, corrélation ;
- `contrat_partie` — parties déclarées **par version** (`PRODUCTEUR`,
  `CONSOMMATEUR`, `OPERATEUR`, `VERIFICATEUR`), référençant une capacité ou
  un produit connu ; un consommateur non redéclaré d'une version à l'autre
  n'est simplement pas repris — c'est ainsi que l'analyse détecte un
  consommateur retiré ;
- `contrat_operation` — référence, type (`COMMANDER`, `INTERROGER`,
  `PUBLIER`, `CONSOMMER`, `VERIFIER`, `REVOQUER`), méthode et chemin HTTP
  facultatifs, action d'autorisation, durée, idempotence, audit obligatoire ;
- `contrat_schema` — sens (`ENTREE`, `SORTIE`, `EVENEMENT`, `ERREUR`), format
  (`JSON_SCHEMA`, `OPENAPI_SCHEMA`, `TEXTE_STRUCTURE`, `AUCUN_CORPS`),
  contenu, empreinte ; validé structurellement et contrôlé contre les
  secrets avant acceptation ;
- `contrat_erreur` — code stable, statut HTTP facultatif, retentable,
  détail exposable, description ;
- `contrat_obligation` — type (`AUTORISATION`, `AUDIT`, `FINALITE`,
  `SOURCE`, `EXPIRATION`, `MINIMISATION`, `CONFIDENTIALITE`, `IDEMPOTENCE`,
  `ASSURANCE_SESSION`), description ; jamais une expression exécutable ;
- `contrat_compatibilite` — analyses en ajout seul, liées à l'empreinte de
  la version analysée, résultat (`COMPATIBLE`, `ADAPTATION_REQUISE`,
  `RUPTURE`, `INDETERMINE`) et divergences structurées ;
- `contrat_conformite` — conformités en ajout seul, résultat (`CONFORME`,
  `NON_CONFORME`, `INCOMPLET`), artefact de preuve obligatoire (commit,
  rapport) ;
- `contrat_projection` — projections dérivées en ajout seul (`OPENAPI`,
  `JSON_SCHEMA`, `PHP_INTERFACE`, `DOCUMENTATION`), jamais la source
  canonique en cas de divergence avec le registre.

**Données exclues :** aucun secret, aucun mot de passe, aucun jeton. Le
schéma ne porte structurellement aucune colonne pouvant recevoir un
identifiant ; `ValidateurContrat` refuse en outre tout contenu de schéma
ressemblant à un secret réel (motif, clé API, JWT) et toute obligation
ressemblant à une expression exécutable.

**Commandes (`RegistreContrats`) :**

- `inscrireContrat()` — refuse un producteur principal ambigu (capacité et
  produit à la fois, ou ni l'un ni l'autre), un propriétaire inconnu, une
  référence déjà utilisée, un type hors liste close ;
- `creerVersion()` — naît en `BROUILLON`, format `X.Y.Z` exigé, refuse une
  version déjà utilisée (jamais réutilisable, même après retrait) ;
- `declarerPartie()` / `declarerOperation()` / `declarerSchema()` /
  `declarerErreur()` / `declarerObligation()` — seule une version
  `BROUILLON` les accepte ;
- `soumettreVersion()` — exige au moins une opération ou un schéma, une
  partie `PRODUCTEUR`, et un consommateur pour tout contrat `HTTP_API` ; fige
  l'empreinte de contenu, passe la version en `EN_VALIDATION` : elle devient
  immuable ; idempotente ;
- `analyserCompatibilite()` — n'agit que sur une version `EN_VALIDATION` (ou
  déjà `ACTIVE`, pour réanalyse) ; compare structurellement la version
  analysée à la version active courante du contrat (`AnalyseurCompatibilite`
  — jamais « la version précédente créée », toujours celle réellement
  active) ; enregistre un résultat en ajout seul ;
- `enregistrerConformite()` — n'agit que sur une version au-delà de
  `BROUILLON`, exige un artefact de preuve ;
- `activerVersion()` — refuse sans analyse enregistrée
  (`ANALYSE_MANQUANTE`), sans conformité `CONFORME` (`CONFORMITE_MANQUANTE`),
  et sans plan de migration explicite pour une analyse `RUPTURE`
  (`PLAN_MIGRATION_REQUIS`) ; remplace l'ancienne version active
  (`REMPLACEE`) et active la nouvelle dans la même transaction ; idempotente ;
- `deprecierVersion()` — seule une version `ACTIVE` se déprécie ;
- `suspendreVersion()` — `ACTIVE` ou `DEPRECIEE` ; ferme immédiatement l'usage
  qu'elle portait ; idempotente ;
- `retirerVersion()` — `ACTIVE`, `DEPRECIEE` ou `SUSPENDUE` ; ne supprime
  rien ; idempotente ;
- `genererProjection()` — dérive une projection (fragment OpenAPI JSON,
  JSON Schema, interface PHP, documentation) depuis le contenu réel de la
  version ; jamais une génération implicite pendant une requête métier.

**Analyse de compatibilité (`AnalyseurCompatibilite`) :** comparaison
structurelle, jamais une lecture de code PHP ni de route Laravel — elle
compare des enregistrements déjà persistés et déjà figés par la soumission.
Détecté `RUPTURE` : opération supprimée, méthode ou chemin HTTP modifié,
opération devenue non idempotente, champ obligatoire ajouté, champ supprimé,
type modifié, valeur d'énumération retirée, code d'erreur supprimé,
consommateur non redéclaré. Détecté `ADAPTATION_REQUISE` (compatible mais
opérationnellement bloquant) : action d'autorisation modifiée, durée
réduite, erreur devenue non retentable. Toujours `COMPATIBLE` : ajout de
champ facultatif, ajout d'opération, absence de version précédente active.
La convention de représentation d'un schéma `JSON_SCHEMA` retenue par ce
registre — `{"proprietes":{"champ":{"type":...,"requis":...,"enum":[...]}}}`
— est documentée dans `ValidateurContrat` : un sous-ensemble suffisant à
cette analyse, pas la spécification JSON Schema complète.

**Génération et contrôle OpenAPI (`GenerateurOpenApi`) :**
`genererFragmentJson()` projette les opérations et schémas d'une version en
un fragment OpenAPI 3.1 valide en JSON (OpenAPI 3.1 admet une représentation
JSON strictement équivalente à YAML ; aucune dépendance YAML n'est ajoutée
au projet). `extraireOperationsDuFichier()` lit `openapi/core-v1.yaml` selon
sa convention d'indentation déjà en vigueur pour détecter une dérive, sans
dépendance nouvelle. `comparer()` détecte routes manquantes, opérations
fantômes et `operationId` dupliqués.

**Requêtes :** `resoudreContrat()`, `listerContrats()`, `listerVersions()`,
`resoudreVersion()` (avec parties, opérations, schémas, erreurs,
obligations), `resoudreVersionActive()`, `resoudreHistorique()`,
`resoudreCompatibilite()`, `resoudreConformite()`, `resoudreConsommateurs()`,
`diagnostiquerRegistre()` (vérifie l'invariant central : au plus une version
active par contrat à l'instant présent).

**Événements :** `CONTRATS` dans le journal opérationnel —
`CONTRAT_INSCRIT`, `VERSION_CONTRAT_CREEE`, `PARTIE_CONTRAT_DECLAREE`,
`OPERATION_CONTRAT_DECLAREE`, `SCHEMA_CONTRAT_DECLARE`,
`ERREUR_CONTRAT_DECLAREE`, `OBLIGATION_CONTRAT_DECLAREE`,
`VERSION_CONTRAT_SOUMISE`, `COMPATIBILITE_CONTRAT_ANALYSEE`,
`VERSION_CONTRAT_ACTIVEE`, `VERSION_CONTRAT_DEPRECIEE`,
`VERSION_CONTRAT_SUSPENDUE`, `VERSION_CONTRAT_RETIREE`,
`CONFORMITE_CONTRAT_ENREGISTREE`, `PROJECTION_CONTRAT_GENEREE`,
`OPERATION_CONTRAT_REFUSEE`. Aucun secret ni payload métier réel n'y figure.

**Dépendances :** CAP-CORE-001 (identité canonique du propriétaire),
CAP-CORE-004 (décision), CAP-CORE-006 (sources, non validées strictement —
`source_reference` reste libre, comme pour CAP-CORE-006/007/011),
CAP-CORE-007 (politique d'autorisation `POL-CONTRATS-V1`, portée par le
registre des politiques), CAP-CORE-011 (produits consommateurs/producteurs
référencés), CAP-CORE-013 (preuve).

**Autorisations :** `POL-CONTRATS-V1` gouverne ce registre lui-même — y
compris son écran et son API — et n'existait pas avant ce chantier : comme
`POL-POLITIQUES-V1` pour CAP-CORE-007, elle est l'auto-gouvernance dont ce
registre a besoin pour ne pas rester fermé à `403` dès sa première écriture.
Douze actions, une par action réellement soumise à CTR-03 par
`AccesContrats`, chacune réservée à `AUT-GAMAD-001`. Bootstrapée par
`core:contrats:bootstrap`, qui l'inscrit et l'active dans le registre des
politiques (CAP-CORE-007) avant de reprendre l'inventaire des contrats.

**Comportement en panne :** une décision, une preuve ou un registre
indisponible ferme l'opération (`503`) sans écriture partielle. Une commande
sans dossier gouverné (`politique`, `producteur`, `source`, `preuve`) est
refusée avant toute tentative d'écriture. Un contrat, une version ou une
opération inconnue rend `404` ; une référence, une version, une opération ou
une erreur déjà utilisée rend `409` ; un dossier invalide, une version
immuable, une analyse ou une conformité manquante, ou un plan de migration
requis rendent `422`. Une projection indisponible laisse le registre
lisible ; aucune génération n'est implicite pendant une requête métier.

**Sauvegarde et restauration :** le registre vit dans son propre magasin
(`CONTRACT_REGISTRY_URL` / `CONTRACT_REGISTRY_PATH`), distinct des sept
autres. `php artisan core:fondation:migrer` applique sa migration ; la
readiness (`/api/v1/health/ready`) la vérifie comme huitième cible ;
`ops/core-foundation/backup.sh` et `restore-drill.sh` couvrent une huitième
cible (`contrats`) au même titre que les sept autres.
`core:fondation:importer-sqlite --contrats=` importe un magasin SQLite
existant vers une cible PostgreSQL vide.

**Bootstrap :** `php artisan core:contrats:bootstrap` établit d'abord
`POL-CONTRATS-V1` dans le registre des politiques, puis reprend fidèlement,
sans en inventer, treize contrats déjà exploités depuis
`core/registre-contrats/resources/bootstrap-contrats-v1.json` (empreinte
SHA-256 vérifiée) : les six contrats internes prioritaires de la fiche de
codage (`CTR-01` Identity Registry, `CTR-02` Authorities & Mandates, `CTR-03`
Authorization, `CTR-04` Rules/Policies, `CTR-15` Sources, `CTR-16`
Authentication & Access — chacun relié à une méthode publique réelle de sa
classe), et sept contrats HTTP externes reliés aux routes réelles
d'`openapi/core-v1.yaml` (fédération GamaDrive avec ses trois opérations
réelles, identités, produits, sources, politiques, sessions, autorisation).
`PRD-GAMAD-002` (GamaDrive) est déclaré consommateur des contrats HTTP,
seul consommateur externe réel du Core à ce jour. Pour chaque contrat repris :
inscription, création de la version historique, déclaration des parties et
opérations réelles, soumission, analyse (`COMPATIBLE` — première version,
rien à rompre), conformité `CONFORME` (artefact : commit courant du dépôt),
puis activation. Idempotent.

**Code actuel :** `core/registre-contrats/`,
`apps/console-laravel/app/Application/Contrats/AccesContrats.php`,
`apps/console-laravel/app/Http/Controllers/Api/V1/ContratController.php`,
`apps/console-laravel/app/Http/Controllers/ContratConsoleController.php`,
`apps/console-laravel/resources/views/contrats/`, routes
`/api/v1/contrats*` et `/contrats*` (console), commande
`core:contrats:bootstrap`.

**Écran d'administration :** `Contrats` dans la console — liste filtrable
par visibilité (version active, propriétaire, autorité), fiche par contrat
avec ses versions et son historique, fiche par version avec ses parties,
opérations, schémas, erreurs, résultats de compatibilité et de conformité,
et les actions de cycle (soumettre, analyser, enregistrer une conformité,
activer, déprécier, suspendre, retirer). Les actions de gouvernance ne
s'affichent qu'à l'autorité ; la fiche de version n'est accessible qu'à
l'autorité ou au propriétaire. L'écran n'ouvre aucun chemin parallèle : il
appelle le même cas d'usage gouverné (`AccesContrats`) que l'API v1.

**Tests actuels :**

- `core/registre-contrats/tests/contrats_p3.php` — garde de capacité, 51
  épreuves : bootstrap, idempotence, empreinte, référence unique, producteur
  et consommateur obligatoires, source et finalité explicites, cycle
  BROUILLON→immuabilité, schémas valides/invalides/sans secret, opérations et
  erreurs uniques, dix règles de détection de compatibilité (ajout facultatif
  compatible ; ajout obligatoire, suppression de champ, changement de type,
  réduction d'énumérable, changement de méthode ou de chemin, consommateur
  retiré en rupture ; autorisation renforcée et durée réduite signalées),
  activation refusée sans analyse ni conformité, activation atomique,
  invariant une seule version active, plan de migration obligatoire pour une
  rupture, dépréciation datée, suspension opposable, retrait sans
  suppression, référence non réutilisable, dérive OpenAPI (fichier réel lu,
  route manquante, opération fantôme, `operationId` dupliqué détectés),
  fidélité de l'inventaire de bootstrap (six contrats internes et sept
  contrats externes vérifiés contre leurs producteurs réels), rollback de
  transaction, invariant tenu à travers une séquence rapide d'activations,
  continuité d'une copie physique du magasin, absence de secrets,
  reconstruction de la baseline sans perte, contre-épreuve ; raccordée à la
  CI ;
- `apps/console-laravel/tests/Integration/contrats_v1_p1.php` — parcours HTTP
  complet, de l'inscription au retrait, en passant par la version, les
  parties, l'opération, le schéma, la soumission, l'analyse, la conformité et
  l'activation ; raccordé à la CI ;
- `apps/console-laravel/tests/Integration/contrats_console_p1.php` — écran
  d'administration, même parcours depuis la console, raccordé à la CI ;
- `apps/console-laravel/tests/Integration/openapi_contracts_p1.php` — dérive
  entre les routes Laravel réelles de `/api/v1/contrats*` (`Route::getRoutes()`)
  et `openapi/core-v1.yaml`, sur le périmètre livré par ce chantier ;
  raccordé à la CI ;
- `apps/console-laravel/tests/Integration/api_v1_p1.php` — adaptée pour le
  huitième magasin (readiness) ;
- `apps/console-laravel/tests/Integration/migration_config_cache_p1.php` —
  adaptée pour `CONTRACT_REGISTRY_URL` ;
- `apps/console-laravel/tests/Integration/postgresql_p0.sh` /
  `postgresql_p0.php` — huit magasins PostgreSQL réels, sauvegarde et
  restauration.

**État réel :** `GO` — la garde de capacité (51 épreuves), les 25
intégrations HTTP/console de la CI et l'exercice PostgreSQL réel (huit
magasins, sauvegarde et restauration isolée) sont verts en local le 2 août
2026. Voir le rapport de chantier de la PR pour la confirmation CI GitHub.

**Manques non bloquants :**

- l'analyse de compatibilité ne détecte pas une « finalité modifiée » ni une
  suppression de partie hors consommateur (`OPERATEUR`, `VERIFICATEUR`) :
  seuls les champs et règles listés dans la fiche de codage sont couverts ;
- la règle « le producteur seul ne peut pas déclarer conforme tous les
  consommateurs » (section 12 de la fiche de codage) n'est pas appliquée au
  niveau du registre : `enregistrerConformite()` accepte toute conformité
  gouvernée, quel que soit l'acteur ; une distinction plus fine relève d'une
  politique d'autorisation plus riche, pas de ce registre ;
- les schémas d'`openapi/core-v1.yaml` ne sont pas encore comparés au contenu
  réel des schémas déclarés par les contrats repris ; seules les routes et
  méthodes le sont (`openapi_contracts_p1.php`) ;
- `CAP-CORE-021` (Matching) ne consomme pas encore ce registre.

**Prochain chantier :** ne pas commencer avant que CAP-CORE-009 soit
confirmée `GO` par la CI ; ensuite, `CAP-CORE-010` (vocabulaire canonique),
consommateur naturel des références aujourd'hui en texte libre
(`source_reference`, `finalite_reference`).
