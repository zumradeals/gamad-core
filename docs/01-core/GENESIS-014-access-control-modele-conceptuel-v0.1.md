# GENESIS-014 — Concepts, agrégats et bounded context de Access Control

## Version 0.1 — Draft architectural

**Projet :** GAMAD Core — sous-phase 4/13
**Statut :** Draft architectural, précède toute implémentation
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD
**Architecture :** Claude — Architecte de GAMAD
**Dépendances :**
- GENESIS-013 — Access Control Charter
- GENESIS-010 — Modèle conceptuel Persons and User Accounts (patron méthodologique)
- GENESIS-012 — Modèle conceptuel Organizations and Memberships
- ADR-0013 — Frontière du noyau Shared
- ADR-0017 — Format d'identité avec realm
- Realm en service : `GAT`

---

## A — Concepts et relations

### Concepts

- **Permission** — la capacité atomique d'effectuer une action sur un type d'objet. Format : `{domaine}:{objet}:{action}` ou `{domaine}:{action}`. Ex : `identity:read`, `membership:status:change`. Non modifiable après création — une permission mal nommée se déprécie et se remplace par une nouvelle, jamais éditée.
- **Role** — un ensemble nommé et versionné de permissions. Un rôle porte un nom (`superadmin`), un périmètre (`realm` ou `organization`), et une liste de permissions. Non supprimable — désactivable uniquement.
- **RoleAssignment** — le lien entre un acteur (`person_id`), un rôle (`role_id`), et un contexte organisationnel (`organization_id`). Porte son propre cycle de vie : `active`, `revoked`.
- **AccessDecision** — la réponse du moteur à une demande : `ALLOW` ou `DENY`, avec la raison. Jamais persistée en base — uniquement auditée.

### Relations

```
Permission ◄──── Role
                  │ 1
                  │ attribué via
                  ▼ 0..N
            RoleAssignment
                  │ lie
          ┌───────┴────────┐
          ▼                ▼
    Person            Organization
(Persons ctx)    (Organizations ctx)
```

### La demande d'accès n'est pas un concept persisté

La demande d'accès (`can(actor, action, context)`) est un **objet de valeur transitoire** — elle entre dans le moteur, produit une `AccessDecision`, et disparaît. Aucune table `access_requests` n'est créée. Seule l'`AccessDecision` est auditée, et uniquement dans la chaîne d'audit existante — pas dans une table dédiée.

---

## B — Agrégats et frontières transactionnelles

| Agrégat | Racine | Contient | Référence (par ID seulement) |
|---|---|---|---|
| **Role** | Role | liste de Permission (références), statut, périmètre | — |
| **RoleAssignment** | RoleAssignment | statut (`active`/`revoked`), dates | `role_id`, `person_id`, `organization_id` |

### Pourquoi Permission n'est pas un agrégat indépendant

Une permission est un concept atomique et immuable — elle n'a ni cycle de vie propre (pas de `suspend`, pas de `reactivate`), ni sous-entités. Elle est référencée par les rôles qui la portent. La traiter comme un agrégat indépendant ajouterait une indirection sans bénéfice. Elle est un **value object nommé**, stocké dans sa propre table pour la lisibilité, mais sans frontière transactionnelle propre.

### Pourquoi RoleAssignment est séparé de Role

Un `Role` change rarement (ajout ou retrait d'une permission). Une `RoleAssignment` change potentiellement souvent (attribution, révocation). Les coupler dans le même agrégat forcerait des verrous inutiles sur le rôle entier à chaque révocation d'attribution. Séparés, les deux peuvent évoluer à leur propre rythme.

### Le moteur d'évaluation n'est pas un agrégat

Le moteur (`AccessControlEngine` ou équivalent) est un **service de domaine** sans état — il lit les rôles et attributions, évalue la demande, retourne une `AccessDecision`. GENESIS-013 §6 invariant 8 le pose explicitement : mêmes entrées → même décision. Aucun état interne, aucun cache côté moteur.

---

## C — Bounded context

Access Control est un bounded context distinct de tous les précédents.

### Ce que ce contexte possède seul

Les mots `Permission`, `Role`, `RoleAssignment`, `AccessDecision` n'existent nulle part ailleurs dans le Core. Aucun autre contexte ne doit jamais prendre une décision d'autorisation de son propre chef — il appelle le moteur Access Control et reçoit `ALLOW` ou `DENY`.

### Sens des dépendances

```
Access Control ──lit──▶ Identity Registry      (existence de l'acteur)
Access Control ──lit──▶ Persons and Accounts   (existence de la personne)
Access Control ──lit──▶ Organizations          (existence du contexte)
```

Access Control **ne modifie jamais** les contextes qu'il consulte. Il lit, il décide, il audite. L'inverse est absolument interdit : aucun des trois contextes amont ne doit interroger Access Control pour fonctionner — ils fonctionnent déjà sans lui (c'est le cas depuis les trois sous-phases précédentes).

### Comment les autres contextes appellent le moteur

Chaque bounded context qui a besoin d'une décision d'autorisation appelle une interface de service définie dans `src/Shared/` — jamais une dépendance directe vers `src/AccessControl/`. L'interface est minimale :

```php
interface AccessControlGateway {
    public function can(
        IdentityId $actor,
        string $action,
        IdentityId $context
    ): AccessDecision;
}
```

L'implémentation réelle (`RbacAccessControlGateway`) vit dans `src/AccessControl/Infrastructure/`. Avant la livraison de cette sous-phase, une implémentation provisoire (`PermissiveAccessControlGateway`) retourne `ALLOW` sur tout — pour que les autres contextes puissent commencer à appeler l'interface sans bloquer sur l'absence du moteur réel.

### Ligne rouge avec Resource Registry (sous-phase 5, à venir)

Les permissions définies en §3 de GENESIS-013 portent sur des **types** d'objet (`identity`, `person`, `organization`...), jamais sur des **instances** spécifiques. « Zakaria peut lire toutes les identités » est une permission de type. « Zakaria peut lire l'identité `GAM-GAT-PER-000042` mais pas les autres » est une permission d'instance — c'est Resource Registry qui introduira cette granularité, pas Access Control. Cette distinction doit rester nette.

### Test de frontière (ADR-0013 étendu)

Le test d'architecture existant sera étendu pour vérifier qu'aucun fichier sous `src/AccessControl/` n'importe directement un namespace sous `src/IdentityRegistry/`, `src/PersonsAndAccounts/`, ou `src/OrganizationsAndMemberships/` — seules les interfaces via `src/Shared/` sont autorisées. Symétriquement, aucun fichier sous les trois contextes précédents n'importe `src/AccessControl/` directement — ils passent tous par `AccessControlGateway` défini dans `src/Shared/`.

---

## D — Projection logique

### Esquisse de schéma logique

```
permissions
  id              (PK)
  name            (UNIQUE, format: domaine:objet:action ou domaine:action)
  description
  created_at

roles
  id              (PK)
  name            (UNIQUE)
  scope           (realm | organization)
  status          (active | deprecated)
  created_at

role_permissions
  role_id         (FK → roles.id)
  permission_id   (FK → permissions.id)
  PRIMARY KEY (role_id, permission_id)

role_assignments
  id              (PK)
  role_id         (FK → roles.id)
  person_id       (format GAM-GAT-PER-xxxxxx — pas de FK structurelle vers persons,
                   vérifié applicativement pour éviter le couplage de schéma)
  organization_id (format GAM-GAT-ORG-xxxxxx — même principe)
  status          (active | revoked)
  assigned_at
  revoked_at      (nullable)
```

### Pourquoi pas de FK structurelle vers les autres contextes

Les tables `persons` et `organizations` vivent dans le même schéma PostgreSQL, mais leur appartenance logique est à un autre bounded context. Une FK structurelle crée un couplage de schéma qui contredit ADR-0013. La vérification de l'existence de la personne et de l'organisation est faite **applicativement** dans le handler, avant l'insertion — exactement comme les contextes précédents vérifient l'existence d'une identité dans l'Identity Registry.

### Contrainte d'unicité sur les attributions actives

Index partiel, même patron qu'ADR-0020 :
`UNIQUE (role_id, person_id, organization_id) WHERE status = 'active'`

Un acteur peut recevoir le même rôle dans la même organisation plusieurs fois dans sa vie (révoqué puis réattribué) — l'historique des attributions révoquées est conservé. Seule l'unicité des attributions **actives** est contrainte.

### Validation contre les invariants de GENESIS-013 §6

| Invariant | Garanti par |
|---|---|
| Décision binaire ALLOW/DENY | `AccessDecision` est un sealed value object à deux états, sans troisième valeur possible |
| Toute décision auditée, y compris les DENY | émission d'un événement de domaine `AccessDecisionMade` à chaque évaluation, publié via Outbox |
| Aucune permission par défaut | absence d'attribution = DENY implicite, sans requête supplémentaire |
| `superadmin` GAMAD SAS = portée realm | `scope: realm` sur le rôle `superadmin`, évaluation spéciale dans le moteur |
| Un rôle ne s'attribue pas lui-même | vérification dans `AssignRoleHandler` que l'acteur demandant et l'acteur recevant sont distincts, sauf `superadmin` qui peut attribuer à n'importe qui |
| Pas de suppression physique | pas de `DELETE` dans aucun handler |
| Moteur pur et sans état | `AccessControlEngine` est un service de domaine instancié sans injection d'état mutable |

---

## E — Coexistence avec ADR-0011 pendant la transition

Pendant l'implémentation de cette sous-phase, `PermissiveAccessControlGateway` (retourne `ALLOW` sur tout) est utilisée par les contextes existants. Cela ne change pas leur comportement actuel — c'est une façade neutre.

À la livraison et validation :
1. `RbacAccessControlGateway` remplace `PermissiveAccessControlGateway` dans le câblage de `public/index.php`.
2. Les tokens statiques admin (`GAMAD_ADMIN_TOKENS_JSON`, `GAMAD_ADMIN_PERMISSIONS_JSON`) sont retirés de la configuration et du `docker-compose.yml`.
3. `ScopeAuthorizationMiddleware` et `EnvironmentAuthorizationService` sont marqués `@deprecated` dans le code, conservés le temps d'un cycle de déploiement, puis supprimés par une tâche dédiée.
4. `ADR-0011` est mis à jour avec le statut `Superseded` et une référence à cette sous-phase.
5. `REGISTRE-BOOTSTRAP.md` est archivé — chaque route y listée a trouvé sa protection définitive.

---

## Déclaration finale

L'Identity Registry sait qu'une entité existe.
Persons and User Accounts sait qui elle est.
Organizations and Memberships sait où elle s'inscrit.
Access Control sait ce qu'elle peut faire.

Quatre contextes. Quatre questions. Quatre réponses nettes, sans chevauchement.

> Le moteur d'Access Control ne sait pas ce qu'est un fichier, une facture, ou un client. Il sait seulement : cet acteur, cette action, ce contexte — oui ou non.
