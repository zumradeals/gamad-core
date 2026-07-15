# DIRECTIVE-007 — Implémentation de Access Control

**Projet :** GAMAD Core — sous-phase 4/13
**Statut :** Directive architecturale à exécuter
**Orchestrateur :** Zakaria Le SOUFI — DG GAMAD
**Architecture :** Claude
**Exécutant :** Claude Code
**Référence :** GENESIS-013, GENESIS-014, ADR-0011 (à superseder), DIRECTIVE-001 à DIRECTIVE-006

---

## Préambule

Ce document couvre l'implémentation du bounded context **Access Control**, tel que modélisé par GENESIS-013 et GENESIS-014. Il inclut le remplacement définitif du mécanisme bootstrap ADR-0011 et l'amorçage institutionnel des rôles et permissions fondateurs.

**Ordre impératif :** les tâches 1 à 7 (construction du moteur) précèdent impérativement les tâches 8 à 10 (transition et remplacement d'ADR-0011). Ne jamais retirer les tokens statiques avant que le moteur soit validé en conditions réelles.

---

## Partie A — Décisions architecturales

### ADR-0021 — Interface AccessControlGateway dans Shared

**Statut :** Accepté
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** GENESIS-014 §C, ADR-0013

**Contexte**
Les bounded contexts existants (Identity Registry, Persons and Accounts, Organizations and Memberships) doivent pouvoir appeler le moteur Access Control sans prendre une dépendance directe vers `src/AccessControl/` — ce qui violerait ADR-0013.

**Décision**
1. Définir `AccessControlGateway` dans `src/Shared/Contract/AccessControlGateway.php` — une interface minimale à une seule méthode : `can(IdentityId $actor, string $action, IdentityId $context): AccessDecision`.
2. Deux implémentations coexistent temporairement : `PermissiveAccessControlGateway` (retourne `ALLOW` sur tout, utilisée pendant l'implémentation) et `RbacAccessControlGateway` (moteur réel, branchée à la livraison).
3. Le câblage dans `public/index.php` détermine laquelle est active — jamais une variable d'environnement qui basculerait silencieusement entre les deux.
4. `AccessDecision` est un value object sealed à deux états (`ALLOW`, `DENY`) défini dans `src/Shared/Contract/`.

**Conséquences**
- Les trois contextes existants peuvent commencer à appeler l'interface dès la Tâche 2, sans attendre le moteur réel.
- Le remplacement (Tâche 9) ne change rien côté appelant — juste l'implémentation derrière l'interface.

---

### ADR-0022 — Audit des décisions DENY obligatoire

**Statut :** Accepté
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** GENESIS-013 §6 invariant 2

**Contexte**
GENESIS-013 pose que toute décision d'autorisation est auditée, y compris les refus. Les systèmes qui n'auditent que les accès accordés créent des angles morts : une attaque par énumération de permissions ou une tentative d'escalade de privilèges devient invisible.

**Décision**
1. Chaque évaluation du moteur (`RbacAccessControlGateway::can()`) publie un événement `AccessDecisionMade` via l'Outbox, qu'elle retourne `ALLOW` ou `DENY`.
2. L'événement porte : `actor_id`, `action`, `context_id`, `decision` (`ALLOW`/`DENY`), `reason` (nom du rôle ayant accordé, ou `no_matching_role` si DENY), `evaluated_at`.
3. `PermissiveAccessControlGateway` ne publie aucun événement — elle est provisoire et son volume d'appels ne doit pas polluer l'audit.
4. Le volume d'événements `AccessDecisionMade` étant potentiellement élevé, ils sont publiés dans l'Outbox avec une priorité inférieure aux événements de domaine métier — une file dédiée `access_decisions` sera ajoutée à la configuration de l'Outbox pour les isoler.

**Conséquences**
- Toute tentative d'accès non autorisée est tracée dans la chaîne d'audit.
- L'Outbox reste un mécanisme unique, mais avec deux files de priorité distinctes.

---

## Partie B — Tâches dictées à Claude Code

### Tâche 1 — ADR-0021 et ADR-0022
Créer `docs/06-decisions/ADR-0021-...md` et `docs/06-decisions/ADR-0022-...md`.
**Acceptation :** deux fichiers présents, aucune autre modification.

### Tâche 2 — Interface partagée et implémentation permissive
- Créer `src/Shared/Contract/AccessControlGateway.php` (interface).
- Créer `src/Shared/Contract/AccessDecision.php` (value object sealed : `ALLOW`/`DENY` + reason).
- Créer `src/Shared/Infrastructure/AccessControl/PermissiveAccessControlGateway.php` (retourne toujours `ALLOW`, aucun audit, commentaire renvoyant à ADR-0021).
- Câbler `PermissiveAccessControlGateway` dans `public/index.php` comme implémentation active.
- Ajouter les appels `$gateway->can(...)` dans les trois handlers d'écriture les plus sensibles de chaque contexte existant : `RegisterIdentityHandler`, `RegisterPersonHandler`, `CreateOrganizationHandler`, `CreateMembershipHandler` — comme points de contrôle futurs, retournant pour l'instant toujours `ALLOW`.

**Acceptation :** `composer test` passe sans régression ; les quatre handlers appellent bien l'interface ; remplacer `PermissiveAccessControlGateway` par un stub qui retourne toujours `DENY` fait échouer les tests des quatre handlers — preuve que l'appel est réel, pas cosmétique.

### Tâche 3 — Migrations
- `database/migrations/018_create_permissions.sql` — table `permissions` : `id` PK, `name` UNIQUE (format `domaine:action` ou `domaine:objet:action`), `description`, `created_at`.
- `database/migrations/019_create_roles.sql` — table `roles` : `id` PK, `name` UNIQUE, `scope` ENUM (`realm`/`organization`), `status` ENUM (`active`/`deprecated`), `created_at`.
- `database/migrations/020_create_role_permissions.sql` — table `role_permissions` : `role_id` FK, `permission_id` FK, PK composite.
- `database/migrations/021_create_role_assignments.sql` — table `role_assignments` : `id` PK, `role_id` FK, `person_id` (texte, vérifié applicativement, pas de FK structurelle — ADR-0013), `organization_id` (même principe), `status` ENUM (`active`/`revoked`), `assigned_at`, `revoked_at` nullable. Index partiel : `UNIQUE (role_id, person_id, organization_id) WHERE status = 'active'`.
- `database/migrations/022_create_access_decisions_outbox.sql` — file Outbox dédiée `access_decisions` (même structure que `outbox_messages`, isolée conformément à ADR-0022).

**Acceptation :** les cinq migrations s'appliquent proprement dans l'ordre sur une base neuve ; `composer audit:verify` reste vert.

### Tâche 4 — Domaine (`src/AccessControl/Domain/`)
- `Permission` — value object immuable (id, name, description). Pas de machine à états.
- `Role` — agrégat. États : `active → deprecated`. Contient des références à des `PermissionId`. Événements : `RoleCreated`, `PermissionAddedToRole`, `RoleDeprecated`.
- `RoleAssignment` — agrégat. États : `active → revoked`. Événements : `RoleAssigned`, `RoleRevoked`.
- `AccessRequest` — value object transitoire (actor `IdentityId`, action `string`, context `IdentityId`). N'est jamais persisté.
- `AccessDecision` (domaine) — value object sealed `ALLOW`/`DENY` + reason. C'est la même structure que celle de `src/Shared/Contract/` — le domaine en est la source canonique, Shared l'expose via une interface.
- `AccessControlEngine` — service de domaine pur, sans état. Méthode unique : `evaluate(AccessRequest, array $assignments): AccessDecision`. Reçoit les attributions déjà chargées — ne fait aucune requête lui-même.

**Acceptation :** tests unitaires couvrant : ALLOW quand un rôle avec la permission existe, DENY quand aucun rôle ne couvre l'action, DENY quand le rôle existe mais pas dans le bon contexte, ALLOW transversal pour `superadmin` scope `realm` quel que soit le contexte.

### Tâche 5 — Application
- `CreatePermissionHandler` — crée une permission (nom unique, format validé).
- `CreateRoleHandler` — crée un rôle avec son périmètre (`realm`/`organization`).
- `AddPermissionToRoleHandler` — ajoute une permission à un rôle actif.
- `AssignRoleHandler` — vérifie existence de la personne et de l'organisation (applicativement, sans FK), vérifie qu'aucune attribution active n'existe déjà pour ce triplet (index partiel en filet de sécurité), vérifie que l'acteur demandeur possède la permission `role:assign`. Cas spécial : `superadmin` peut s'attribuer à lui-même uniquement lors de l'amorçage (`bin/bootstrap-access-control`) — jamais via un handler HTTP.
- `RevokeRoleHandler`.
- `EvaluateAccessHandler` — point d'entrée applicatif du moteur. Charge les attributions actives de l'acteur, appelle `AccessControlEngine::evaluate()`, publie `AccessDecisionMade` dans la file `access_decisions`, retourne l'`AccessDecision`.

**Acceptation :** tests d'application couvrant : rejet d'une attribution si la personne n'existe pas, rejet d'un doublon d'attribution active, DENY propagé correctement depuis le moteur, publication de l'événement d'audit pour ALLOW et DENY.

### Tâche 6 — Infrastructure
- `PostgreSqlRoleRepository`, `PostgreSqlRoleAssignmentRepository`, `PostgreSqlPermissionRepository`.
- `RbacAccessControlGateway` — implémentation réelle de `AccessControlGateway` : charge les attributions actives de l'acteur via le dépôt, appelle `EvaluateAccessHandler` (ou `AccessControlEngine` directement selon le câblage choisi), retourne l'`AccessDecision`.
- Publier `AccessDecisionMade` dans la file Outbox `access_decisions` (migration 022).
- `PostgreSqlAccessControlLookup` — lecture cross-context des persons et organizations (même patron que les lookups existants, lecture SQL directe sans importer les classes des autres contextes).

**Acceptation :** tests d'intégration Postgres, groupe `integration`, verts en CI. Test spécifique : `RbacAccessControlGateway` retourne `ALLOW` pour un acteur avec le rôle adéquat, `DENY` pour un acteur sans rôle, et `ALLOW` sur n'importe quelle action pour `superadmin` scope `realm`.

### Tâche 7 — Contrat et HTTP
Créer `openapi/access-control-v1.yaml` couvrant :
- `POST /permissions` — créer une permission.
- `GET /permissions` — lister les permissions.
- `POST /roles` — créer un rôle.
- `GET /roles` — lister les rôles.
- `POST /roles/{roleId}/permissions` — ajouter une permission à un rôle.
- `POST /role-assignments` — attribuer un rôle à un acteur dans un contexte.
- `DELETE /role-assignments/{assignmentId}` — révoquer (DELETE sémantique HTTP, mais `status: revoked` en base — jamais de suppression physique).
- `POST /access/evaluate` — évaluer une demande d'accès (usage interne, protégé par session + permission `runtime:health:read`).

Toutes les routes exigent une session personne valide ET la permission correspondante. Contrôleur et routes câblés dans `public/index.php`.
**Acceptation :** tests contractuels OpenAPI et tests bout-en-bout.

### Tâche 8 — Frontière de bounded context et extension des appels gateway
- Étendre `SharedKernelBoundaryTest` pour vérifier qu'aucun fichier sous `src/AccessControl/` n'importe directement un namespace d'un autre bounded context, et qu'aucun autre bounded context n'importe directement `src/AccessControl/`.
- Étendre les appels `$gateway->can(...)` à **tous** les handlers d'écriture et de transition de statut des trois contextes existants (pas seulement les quatre de la Tâche 2) — chaque action sensible doit passer par le moteur.

**Acceptation :** violation injectée délibérément, détectée, retirée. Liste exhaustive des appels `can()` produite et vérifiée contre la liste des permissions de GENESIS-013 §3.

### Tâche 9 — Amorçage (`bin/bootstrap-access-control`)
Outil opérateur CLI, transactionnel, idempotent :
1. Créer toutes les permissions fondatrices de GENESIS-013 §3.
2. Créer les trois rôles fondateurs (`superadmin` scope `realm`, `org_admin` scope `organization`, `member_viewer` scope `organization`) avec leurs permissions.
3. Attribuer le rôle `superadmin` à `GAM-GAT-PER-000001` dans `GAM-GAT-ORG-000001` (GAMAD SAS).
4. Attribuer le rôle `member_viewer` à `GAM-GAT-PER-000001` dans `GAM-GAT-ORG-000002` (GAMAD Technologie) — Zakaria l'élèvera lui-même à `org_admin` via la console pour valider le moteur.
5. Auditer chaque opération.

**Acceptation :** après exécution, `POST /access/evaluate` avec `GAM-GAT-PER-000001` + `identity:read` + `GAM-GAT-ORG-000001` retourne `ALLOW` ; même appel avec une action inexistante retourne `DENY` ; `composer audit:verify` reste vert.

### Tâche 10 — Transition et supersession d'ADR-0011

**Cette tâche ne s'exécute qu'après validation complète de la Tâche 9.**

1. Dans `public/index.php`, remplacer `PermissiveAccessControlGateway` par `RbacAccessControlGateway`.
2. Retirer `GAMAD_ADMIN_TOKENS_JSON` et `GAMAD_ADMIN_PERMISSIONS_JSON` de `docker-compose.yml`, `.env.example`, et toute documentation.
3. Marquer `ScopeAuthorizationMiddleware` et `EnvironmentAuthorizationService` avec `@deprecated` + référence à ADR-0011 superseded.
4. Mettre à jour `docs/06-decisions/ADR-0011-...md` : statut → `Superseded`, ajouter une section `Superseded par` avec référence à DIRECTIVE-007 Tâche 10.
5. Archiver `docs/06-decisions/REGISTRE-BOOTSTRAP.md` : renommer en `REGISTRE-BOOTSTRAP-ARCHIVE.md`, ajouter une note en tête indiquant que toutes les routes listées ont trouvé leur protection définitive avec Access Control.
6. `composer test` passe en intégralité — **aucune régression**.

**Acceptation finale de la sous-phase :** Zakaria se connecte à la console, élève son propre rôle de `member_viewer` à `org_admin` dans GAMAD Technologie, et voit l'action refusée si elle est tentée sans la permission `role:assign` — puis accordée une fois le bon rôle en place. `composer audit:verify` vert. Aucun token statique dans la configuration.

---

## Déclaration finale

Cette Directive ferme la sous-phase 4 et solde ADR-0011 — une dette architecturale ouverte depuis DIRECTIVE-001. À partir de ce moment, chaque action dans le Core est gouvernée par une politique explicite, auditée, et révocable. Aucune sous-phase suivante (Resource Registry) ne démarre tant que les 10 tâches ne sont pas exécutées et vérifiées.
