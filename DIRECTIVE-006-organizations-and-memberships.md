# DIRECTIVE-006 — Implémentation de Organizations and Memberships

**Projet :** GAMAD Core — sous-phase 3/13
**Statut :** Directive architecturale à exécuter
**Orchestrateur :** Zakaria Le SOUFI — DG GAMAD
**Architecture :** Claude
**Exécutant :** Claude Code
**Référence :** GENESIS-011, GENESIS-012, DIRECTIVE-001, DIRECTIVE-003

---

## Préambule

Ce document couvre l'implémentation du bounded context **Organizations and Memberships**, tel que modélisé par GENESIS-011 et GENESIS-012. Il ne touche à aucun autre contexte. Aucune règle de permission ou de droit d'accès ne doit être introduite ici — Access Control reste hors de portée (sous-phase 4/13).

---

## Partie A — Décision architecturale

### ADR-0020 — Contrainte d'unicité de membership actif via index partiel PostgreSQL

**Statut :** Accepté
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** GENESIS-011 §4 invariant 6, GENESIS-012 §D

**Contexte**
L'invariant « au plus un membership actif par personne par organisation » doit être garanti structurellement, pas seulement applicativement. Une contrainte UNIQUE classique sur `(person_id, organization_id)` interdirait les memberships historiques (`ended`) pour une même paire — ce qui contredirait GENESIS-011 §4 invariant 8 (pas de suppression physique, les memberships terminés doivent subsister).

**Décision**
Utiliser un index partiel PostgreSQL : `UNIQUE (person_id, organization_id) WHERE status = 'active'`. Cette contrainte garantit l'unicité uniquement sur les memberships actifs, sans affecter les memberships terminés ou suspendus.

**Conséquences**
- La contrainte est structurelle — elle ne repose pas uniquement sur la logique applicative.
- Un même couple `(person_id, organization_id)` peut avoir un membership `ended` et un membership `active` simultanément, ce qui couvre le cas d'une personne qui quitte puis revient.
- Cette approche est déjà éprouvée dans le Core (patron de l'index partiel utilisé pour d'autres contraintes conditionnelles).

---

## Partie B — Tâches dictées à Claude Code

Chaque tâche s'arrête pour vérification avant la suivante. Aucune tâche ne dépasse le périmètre de Organizations and Memberships.

### Tâche 1 — ADR-0020
Créer `docs/06-decisions/ADR-0020-...md` avec le contenu de la Partie A, format identique aux ADR précédents.
**Acceptation :** fichier présent, aucune autre modification.

### Tâche 2 — Migrations
Créer dans l'ordre :
- `database/migrations/015_create_organizations.sql` — table `organizations` avec `identity_id` PK, `parent_id` FK nullable auto-référente, `name`, `status`, `founded_at`. Contrainte CHECK : `parent_id IS NOT NULL OR identity_id = 'GAM-GAT-ORG-000001'` (seule GAMAD SAS peut ne pas avoir de parent).
- `database/migrations/016_create_departments.sql` — table `departments` avec `id` PK, `organization_id` FK, `name`, `status`.
- `database/migrations/017_create_memberships.sql` — table `memberships` avec `id` PK, `person_id` FK, `organization_id` FK, `department_id` FK nullable, `membership_type` ENUM, `status`, `started_at`, `ended_at` nullable. Index partiel `UNIQUE (person_id, organization_id) WHERE status = 'active'` (ADR-0020).

**Acceptation :** les trois migrations s'appliquent proprement dans l'ordre sur une base neuve sans erreur de contrainte ; `composer audit:verify` reste vert.

### Tâche 3 — Domaine (`src/OrganizationsAndMemberships/Domain/`)
- `Organization` — agrégat. Construit uniquement à partir d'un `IdentityId` de type `organization` existant et actif. Contient ses `Department` comme sous-entités. Machine à états : `active → inactive`. Événements : `OrganizationCreated`, `OrganizationSuspended`, `OrganizationReactivated`.
- `Department` — sous-entité de `Organization`, jamais accessible indépendamment. États : `active`, `inactive`.
- `Membership` — agrégat séparé. États : `active → suspended → ended` (et `suspended → active`). Événements : `MembershipCreated`, `MembershipSuspended`, `MembershipResumed`, `MembershipEnded`.
- `MembershipType` — value object énuméré : `GAMAD_CITIZEN`, `ORDINARY_CITIZEN`, `PARTNER`.

**Acceptation :** tests unitaires de domaine couvrant chaque transition valide et invalide, sur le patron de `IdentityTest.php` et `PersonTest.php`.

### Tâche 4 — Application
- `CreateOrganizationHandler` — vérifie que l'identité référencée existe, appartient au realm `GAT`, a le type `organization` et le statut `active`. Si un `parent_id` est fourni, vérifie que l'organisation parente existe et est active.
- `CreateDepartmentHandler` — rattache un département à une organisation active.
- `CreateMembershipHandler` — vérifie l'existence de la personne (`GAM-GAT-PER-{NUMERO}`) et de l'organisation (`GAM-GAT-ORG-{NUMERO}`). Vérifie qu'aucun membership `active` n't existe déjà pour cette paire (double filet : applicatif + index partiel). Le `membership_type` est fourni explicitement par l'opérateur, jamais inféré.
- `SuspendOrganizationHandler` — suspend l'organisation, puis émet `OrganizationSuspended`. Ce même contexte consomme cet événement pour suspendre tous les memberships actifs de cette organisation (règle d'intégration GENESIS-011 §4 invariant 9) — jamais par lecture directe d'une autre table, toujours via l'événement.
- `EndMembershipHandler`, `SuspendMembershipHandler`, `ResumeMembershipHandler`.

**Acceptation :** tests d'application couvrant : rejet d'une identité de mauvais type, rejet d'un double membership actif, cascade de suspension organisation → memberships.

### Tâche 5 — Infrastructure
Implémentations PostgreSQL de chaque dépôt, suivant le patron transactionnel déjà en place (`PdoTransactionManager`, publication Outbox des événements de domaine). `PostgreSqlOrganizationRepository`, `PostgreSqlMembershipRepository`.

Un point spécifique : `PostgreSqlOrganizationLookup` doit pouvoir résoudre l'arbre de parenté (parent d'une organisation, enfants directs) en lecture — une requête récursive SQL (`WITH RECURSIVE`) est autorisée pour cet usage, documentée comme décision d'implémentation dans un commentaire de code renvoyant à GENESIS-011 §2.1.

**Acceptation :** tests d'intégration Postgres, groupe `integration`, verts en CI. Le test de l'arbre récursif doit couvrir au moins deux niveaux (GAMAD SAS → GAMAD Technologie → une troisième organisation fictive).

### Tâche 6 — Contrat et HTTP
Créer `openapi/organizations-and-memberships-v1.yaml` couvrant :
- `POST /organizations` — créer une organisation (opérateur avec session valide).
- `GET /organizations/{orgId}` — détail d'une organisation.
- `GET /organizations/{orgId}/children` — organisations filles directes.
- `POST /organizations/{orgId}/departments` — créer un département.
- `POST /organizations/{orgId}/memberships` — créer un membership (type fourni explicitement).
- `GET /organizations/{orgId}/memberships` — lister les memberships actifs.
- `POST /memberships/{membershipId}/suspend`, `/resume`, `/end` — transitions de cycle de vie.

Contrôleur et routes câblés dans `public/index.php` aux côtés des contextes existants. Toutes les routes exigent une session personne valide (même mécanisme que Persons and User Accounts) — aucune n'est publique.

**Acceptation :** tests contractuels OpenAPI et tests bout-en-bout sur le patron existant.

### Tâche 7 — Frontière de bounded context (ADR-0013 étendu)
Étendre `SharedKernelBoundaryTest` pour vérifier qu'aucun fichier sous `src/OrganizationsAndMemberships/` n'importe directement un namespace sous `src/IdentityRegistry/` ou `src/PersonsAndAccounts/` — seuls les passages via `src/Shared/` sont autorisés.

**Acceptation :** violation injectée délibérément, détectée, puis retirée — même procédure que les tâches équivalentes des directives précédentes.

### Tâche 8 — Intégration avec le cycle de vie de l'identité
Lorsqu'une identité est suspendue ou révoquée dans l'Identity Registry, toute organisation ou membership actif rattaché à cette identité doit être suspendu. Implémenter cette réaction via l'événement `IdentityStatusChanged` déjà publié, consommé dans `bin/outbox-worker` — jamais par lecture directe des tables de l'Identity Registry.

**Acceptation :** test d'intégration démontrant qu'une suspension d'identité `organization` invalide les memberships actifs de cette organisation.

### Tâche 9 — Amorçage institutionnel (`bin/bootstrap-organizations`)
Outil opérateur CLI (jamais HTTP) qui, en une seule session interactive :
1. Crée GAMAD SAS (`GAM-GAT-ORG-000001`) comme organisation racine — identité `type=organization` à créer dans l'Identity Registry si elle n'existe pas encore, puis organisation dans ce contexte.
2. Crée GAMAD Technologie (`GAM-GAT-ORG-000002`) comme fille de GAMAD SAS.
3. Crée le membership de `GAM-GAT-PER-000001` (Zakaria Le SOUFI) dans GAMAD SAS avec le type `GAMAD_CITIZEN`.
4. Crée le membership de `GAM-GAT-PER-000001` dans GAMAD Technologie avec le type `GAMAD_CITIZEN`.
5. Audite chaque opération.

**Acceptation :** après exécution, `GET /organizations/GAM-GAT-ORG-000001` retourne GAMAD SAS avec statut `active` ; `GET /organizations/GAM-GAT-ORG-000001/memberships` liste le membership de Zakaria ; `composer audit:verify` reste vert.

### Tâche 10 — Extension de la console
Ajouter dans `console/` les écrans suivants, accessibles avec une session opérateur (`callAsPerson`) :
- **Organisations** : liste des organisations (racine + enfants directs), détail d'une organisation, créer une organisation.
- **Membres** : liste des memberships actifs d'une organisation, ajouter un membre (sélection de la personne par `person_id`, choix du type de membership), terminer un membership.

**Acceptation :** Zakaria peut, depuis la console, voir GAMAD SAS, GAMAD Technologie, ses deux memberships, et créer un nouveau membership pour une autre personne existante dans le Core.

---

## Déclaration finale

Cette Directive ferme la sous-phase 3. Organizations and Memberships ne démarre pas tant que DIRECTIVE-005 (console) est terminée et déployée — les deux partagent la même infrastructure de session personne, et les écrans de la Tâche 10 en dépendent directement. Aucune sous-phase suivante (Access Control) ne démarre tant que les 10 tâches ne sont pas exécutées et vérifiées.
