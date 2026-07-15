# GENESIS-013 — Access Control Charter

## Version 0.1 — Draft architectural

**Projet :** GAMAD Core — sous-phase 4/13 (MASTERPLAN-001 Phase 2)
**Statut :** Draft architectural, à valider avant implémentation
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD
**Architecture :** Claude — Architecte de GAMAD
**Dépendances :**
- GENESIS-003 — GAMAD Core Charter §6.7 (Access Control)
- GENESIS-008 — Fédération des Cores souverains
- GENESIS-009 — Persons and User Accounts Charter
- GENESIS-011 — Organizations and Memberships Charter
- IDENTITY-001 — Charte symbolique et identitaire fondatrice de GAMAD
- ADR-0011 — Mécanisme bootstrap administratif (à remplacer par cette sous-phase)
- ADR-0017 — Format d'identité avec realm
- Realm en service : `GAT`

---

## Préambule

L'Identity Registry sait qu'une entité existe.
Persons and User Accounts sait qui elle est.
Organizations and Memberships sait où elle s'inscrit et à quel titre.

Access Control répond à la quatrième question, la plus opérationnelle :
**cet acteur peut-il effectuer cette action sur cet objet dans ce contexte ?**

C'est ici que le mécanisme bootstrap provisoire d'ADR-0011 est officiellement remplacé. C'est ici que Zakaria Le SOUFI reçoit son premier rôle réel. C'est ici que le Core devient gouvernable sans tokens statiques.

---

## 1. Mission

Access Control fournit la source de vérité pour :

- les rôles définis dans le système, et les permissions qu'ils portent ;
- l'attribution de rôles à des acteurs dans un contexte organisationnel ;
- l'évaluation d'une demande d'accès : **autoriser** ou **refuser**, jamais rien d'autre ;
- l'audit de chaque décision d'autorisation.

Il ne fournit **pas** de source de vérité sur les identités (sous-phase 1), les personnes (sous-phase 2), les organisations (sous-phase 3), ni les ressources (sous-phase 5 — Resource Registry, à venir). Il les consulte, il ne les remplace pas.

---

## 2. Modèle conceptuel fondateur : RBAC contextuel

Le modèle retenu est un **RBAC (Role-Based Access Control) contextuel** — ni trop simple, ni prématurément complexe.

### 2.1 Les quatre concepts fondamentaux

| Concept | Définition |
|---|---|
| **Permission** | La capacité d'effectuer une action précise sur un type d'objet. Ex : `identity:read`, `person:create`, `membership:suspend`. |
| **Rôle** | Un ensemble nommé de permissions. Ex : `superadmin`, `org_admin`, `member_viewer`. |
| **Attribution de rôle** | Le lien entre un acteur, un rôle, et un contexte organisationnel. Ex : Zakaria a le rôle `superadmin` dans GAMAD SAS. |
| **Politique** | La règle qui gouverne une décision : « cet acteur, dans ce contexte, avec ce rôle, peut-il effectuer cette action ? » |

### 2.2 Le contexte organisationnel

Un rôle est toujours attribué **dans un contexte**. Ce contexte est une organisation (`GAM-GAT-ORG-{NUMERO}`). Un acteur peut avoir des rôles différents dans des organisations différentes — son rôle dans GAMAD SAS n'est pas automatiquement transféré à GAMAD Technologie, sauf décision explicite.

Exception unique : le rôle `superadmin` dans GAMAD SAS (`GAM-GAT-ORG-000001`) confère une autorité transversale sur tout le realm `GAT` — c'est le seul cas où le contexte organisationnel s'élève au niveau du realm entier. Ce privilège est non délégable et non héritable.

### 2.3 Héritage organisationnel (limité)

Une organisation fille hérite des permissions de lecture de son organisation parente, jamais des permissions d'écriture ou de suppression. Cet héritage est unidirectionnel (parent → enfant) et s'arrête à un niveau — une organisation petite-fille n'hérite pas automatiquement de sa grand-mère. Tout héritage au-delà doit être une attribution explicite.

---

## 3. Permissions fondatrices

Les permissions suivantes sont définies à l'initialisation du realm. Elles couvrent les actions sur les contextes déjà construits (sous-phases 1 à 3) et les actions sur Access Control lui-même. Elles seront étendues à chaque nouvelle sous-phase.

### 3.1 Sur l'Identity Registry

| Permission | Action |
|---|---|
| `identity:create` | Enregistrer une nouvelle identité |
| `identity:read` | Lire une identité |
| `identity:status:change` | Changer le statut d'une identité |

### 3.2 Sur Persons and User Accounts

| Permission | Action |
|---|---|
| `person:create` | Enregistrer une personne |
| `person:read` | Lire une personne |
| `account:create` | Créer un compte utilisateur |
| `account:status:change` | Changer le statut d'un compte |
| `session:revoke` | Révoquer une session |

### 3.3 Sur Organizations and Memberships

| Permission | Action |
|---|---|
| `organization:create` | Créer une organisation |
| `organization:read` | Lire une organisation |
| `organization:status:change` | Changer le statut d'une organisation |
| `department:create` | Créer un département |
| `membership:create` | Créer un membership |
| `membership:read` | Lire les memberships d'une organisation |
| `membership:status:change` | Changer le statut d'un membership |

### 3.4 Sur Access Control lui-même

| Permission | Action |
|---|---|
| `role:create` | Créer un rôle |
| `role:read` | Lire les rôles disponibles |
| `permission:assign` | Attribuer des permissions à un rôle |
| `role:assign` | Attribuer un rôle à un acteur dans un contexte |
| `role:revoke` | Révoquer une attribution de rôle |

### 3.5 Sur le runtime et l'audit

| Permission | Action |
|---|---|
| `runtime:health:read` | Lire la santé du système |
| `audit:verify` | Vérifier l'intégrité de la chaîne d'audit |
| `outbox:read` | Lire l'état de l'outbox |
| `dead-letter:replay` | Rejouer un message en dead-letter |

---

## 4. Rôles fondateurs

Trois rôles sont définis à l'initialisation. Ils peuvent être étendus, jamais réduits sans ADR.

### 4.1 `superadmin`

Toutes les permissions listées en §3, sans exception. Attribution unique : Zakaria Le SOUFI (`GAM-GAT-PER-000001`) dans le contexte GAMAD SAS (`GAM-GAT-ORG-000001`). Ce rôle ne peut être attribué à d'autres acteurs que par le `superadmin` lui-même, après une procédure de gouvernance documentée.

### 4.2 `org_admin`

Permissions d'administration dans le périmètre d'une organisation et de ses filles directes :
`organization:read`, `department:create`, `membership:create`, `membership:read`, `membership:status:change`, `person:read`, `identity:read`, `runtime:health:read`.

Ce rôle est délégable par un `superadmin` ou par un autre `org_admin` de l'organisation parente.

### 4.3 `member_viewer`

Permissions de lecture uniquement :
`organization:read`, `membership:read`, `person:read`, `identity:read`.

Ce rôle est le minimum accordé à toute personne authentifiée dans une organisation, automatiquement à la création d'un membership actif.

---

## 5. Remplacement du bootstrap ADR-0011

ADR-0011 a déclaré le mécanisme de tokens statiques comme provisoire dès sa rédaction. Cette sous-phase le remplace définitivement.

Le remplacement est en deux temps, pour ne pas casser la console et l'API d'un seul coup :

1. **Pendant l'implémentation** : les deux mécanismes coexistent. Les routes admin existantes restent protégées par ADR-0011 en parallèle.
2. **À la livraison et validation** : les tokens statiques admin sont retirés. Toutes les routes sont désormais protégées par le moteur Access Control. ADR-0011 est marqué `Superseded` avec référence à cette sous-phase.

Le registre `docs/06-decisions/REGISTRE-BOOTSTRAP.md` (créé par DIRECTIVE-001 Tâche 3) sera soldé à cette occasion : chaque route listée aura trouvé sa protection définitive.

---

## 6. Invariants

1. Toute décision d'autorisation est binaire : **autoriser** ou **refuser**. Aucune réponse ambiguë, aucun « peut-être selon le contexte » retourné à l'appelant.
2. Toute décision d'autorisation est auditée — y compris les refus. L'audit des refus est aussi important que celui des accès accordés.
3. Aucune permission n'est accordée par défaut. L'absence d'attribution explicite est un refus.
4. Le rôle `superadmin` dans GAMAD SAS est le seul à portée de realm. Tout autre rôle est strictement limité à son contexte organisationnel déclaré.
5. Un rôle ne peut jamais s'attribuer lui-même. L'attribution d'un rôle exige un acteur distinct portant la permission `role:assign`.
6. La suppression physique d'un rôle ou d'une attribution est interdite. Les rôles se `désactivent`, les attributions se `révoquent` — jamais une suppression silencieuse.
7. Aucune règle de permission n'est codée en dur dans le code source des autres bounded contexts — ils appellent le moteur Access Control pour chaque décision. L'inverse (Access Control qui connaît la logique interne d'un autre contexte) est également interdit.
8. Le moteur d'évaluation est pur et sans état : mêmes entrées → même décision, toujours. Aucun résultat mis en cache côté moteur (le cache éventuel est la responsabilité de l'appelant, pas du moteur).

---

## 7. Ce que ce domaine ne doit jamais devenir

- Un moteur de règles métier (ex : « un fichier ne peut être supprimé que s'il a été archivé 30 jours » — c'est une règle de Resource Registry, pas d'Access Control).
- Un système d'authentification — il ne vérifie jamais si une session est valide, il reçoit une identité d'acteur déjà authentifiée et décide si elle est autorisée.
- Un proxy HTTP — il répond à une question (`can(actor, action, context)`), il ne filtre pas les requêtes réseau.
- Un système hiérarchique illimité — l'héritage d'une permission s'arrête à un niveau de profondeur (§2.3). La complexité illimitée de l'héritage est une source classique de failles.

---

## 8. Amorçage institutionnel

À la livraison de cette sous-phase, via `bin/` (jamais HTTP) :

1. Créer les permissions fondatrices (§3).
2. Créer les rôles fondateurs (§4).
3. Attribuer le rôle `superadmin` à `GAM-GAT-PER-000001` dans le contexte `GAM-GAT-ORG-000001`.
4. Attribuer le rôle `member_viewer` à `GAM-GAT-PER-000001` dans `GAM-GAT-ORG-000002` (GAMAD Technologie) — ce rôle minimal sera immédiatement élevé à `org_admin` par Zakaria lui-même via la console, pour valider que le moteur fonctionne.
5. Retirer les tokens statiques ADR-0011 et marquer l'ADR comme `Superseded`.

---

## 9. Prochaine étape de gouvernance

Ce document sera suivi, sur validation, du modèle conceptuel (GENESIS-014) avant toute implémentation.

---

## 10. Déclaration finale

Trois sous-phases ont posé l'identité, la personne, et la structure. Access Control est la quatrième — celle qui donne du sens à toutes les autres en décidant qui peut agir, et sur quoi.

Sans Access Control, le Core sait *qui est là*. Avec Access Control, il sait *ce qui est permis*.

> L'autorisation n'est pas une fonctionnalité. C'est la condition sans laquelle tout ce qui précède n'est qu'une base de données ouverte.
