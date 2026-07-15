# GENESIS-012 — Concepts, agrégats et bounded context de Organizations and Memberships

## Version 0.1 — Draft architectural

**Projet :** GAMAD Core — sous-phase 3/13
**Statut :** Draft architectural, précède toute implémentation
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD
**Architecture :** Claude — Architecte de GAMAD
**Dépendances :**
- GENESIS-011 — Organizations and Memberships Charter
- GENESIS-010 — Modèle conceptuel Persons and User Accounts (patron méthodologique)
- ADR-0013 — Frontière du noyau Shared
- ADR-0017 — Format d'identité avec realm
- Realm en service : `GAT`

---

## A — Concepts et relations

### Concepts

- **Organization** — une structure reconnue par le realm, référençant une identité `GAM-GAT-ORG-{NUMERO}`, avec un nom, un statut, et un lien optionnel vers son organisation parente.
- **Department** — une subdivision interne d'une organisation, nommée, sans identité propre dans l'Identity Registry.
- **Membership** — la relation formelle entre une `Person` et une `Organization`, portant un type d'appartenance (`GAMAD_CITIZEN`, `ORDINARY_CITIZEN`, `PARTNER`), un département optionnel, et un cycle de vie.

### Relations

```
Identity (Identity Registry)
      │ 1
      │ référencée par
      ▼ 1
Organization ──────────────────── Organization
      │ parente (0..1)              (fille 0..N)
      │
      │ 1 possède
      ▼ 0..N
Department
      │
      │ optionnellement rattaché à
      ▼
Membership ◄──── Person (Persons and User Accounts)
  type: GAMAD_CITIZEN
       | ORDINARY_CITIZEN
       | PARTNER
  statut: active | suspended | ended
```

### Cardinalités importantes

- Une `Organization` a zéro ou un parent, zéro ou N filles.
- Une `Organization` a zéro ou N `Department`.
- Une `Person` peut avoir N `Membership` actifs dans des organisations **différentes**.
- Une `Person` a au plus un `Membership` actif par `Organization` à un instant donné.
- Un `Membership` référence exactement une `Person` et exactement une `Organization`.
- Un `Membership` peut référencer zéro ou un `Department` de cette même organisation.

---

## B — Agrégats et frontières transactionnelles

| Agrégat | Racine | Contient | Référence (par ID seulement) |
|---|---|---|---|
| **Organization** | Organization | nom, statut, liste de Department | `identity_id` (Identity Registry), `parent_id` (Organization) |
| **Membership** | Membership | type, statut, dates, department_id | `organization_id`, `person_id` |

### Pourquoi Department n'est pas un agrégat indépendant

Un département n'a aucune existence hors de son organisation — il naît avec elle, peut être renommé ou désactivé avec elle, et un membership qui le référence ne peut jamais désigner un département d'une autre organisation. C'est la même logique qu'`AuthenticationMethod` dans `UserAccount` : la frontière transactionnelle protège un invariant (`Membership.department_id` ne peut référencer qu'un département de `Membership.organization_id`), et cet invariant ne tient que si `Department` vit à l'intérieur de l'agrégat `Organization`.

### Pourquoi Membership est un agrégat séparé

Un membership a son propre cycle de vie (création, suspension, fin), ses propres événements de domaine, et il est au cœur de l'audit institutionnel. Le coupler à `Organization` forcerait des verrous inutiles sur toute la structure à chaque changement de membership — et des requêtes de lecture sur une organisation chargées de N memberships sont une dette de performance évidente. Séparé, il reste léger et rapide.

---

## C — Bounded context

Organizations and Memberships est un bounded context distinct, ni de l'Identity Registry, ni de Persons and User Accounts.

### Ce que ce contexte possède seul

Les mots `Organization`, `Department`, `Membership` n'existent nulle part ailleurs dans le Core. L'Identity Registry connaît `Identity`. Persons and User Accounts connaît `Person`, `UserAccount`. Ces vocabulaires ne se mélangent jamais côté code.

### Sens des dépendances

```
Organizations and Memberships ──lit──▶ Identity Registry
Organizations and Memberships ──lit──▶ Persons and User Accounts
```

Ce contexte consulte les deux précédents (existence et statut d'une identité, existence d'une personne) mais ne les modifie jamais. L'inverse n'existe pas : ni l'Identity Registry ni Persons and User Accounts ne doivent jamais interroger Organizations and Memberships pour fonctionner.

### Ligne rouge avec Access Control (sous-phase 4, à venir)

Le type de membership (`GAMAD_CITIZEN`, etc.) est une **catégorie institutionnelle**, jamais un rôle d'accès. Access Control décidera, plus tard, si le fait d'être `GAMAD_CITIZEN` dans telle organisation confère tel droit sur telle ressource. Ce contexte ne prend aucune décision de permission — il dit seulement « cette personne appartient à cette organisation, à ce titre ». Aucune règle de permission ne doit être codée ici, même provisoirement.

### Test de frontière (ADR-0013 étendu)

Le test d'architecture existant (`SharedKernelBoundaryTest`) sera étendu pour vérifier qu'aucun fichier sous `src/OrganizationsAndMemberships/` n'importe un namespace propre à `src/IdentityRegistry/` ou `src/PersonsAndAccounts/` — seules les interfaces de dépôt et les value objects partagés via `src/Shared/` sont autorisés.

---

## D — Projection logique

### Esquisse de schéma logique

```
organizations
  identity_id       (PK, FK → identity registry, format GAM-GAT-ORG-xxxxxx)
  parent_id         (FK nullable → organizations.identity_id)
  name
  status            (active | inactive)
  founded_at

departments
  id                (PK)
  organization_id   (FK → organizations.identity_id)
  name
  status            (active | inactive)

memberships
  id                (PK)
  person_id         (FK → persons.identity_id, format GAM-GAT-PER-xxxxxx)
  organization_id   (FK → organizations.identity_id)
  department_id     (FK nullable → departments.id)
  membership_type   (GAMAD_CITIZEN | ORDINARY_CITIZEN | PARTNER)
  status            (active | suspended | ended)
  started_at
  ended_at          (nullable)
```

### Validation contre les invariants de GENESIS-011 §4

| Invariant | Garanti par |
|---|---|
| GAMAD SAS seule racine | `parent_id` NULL uniquement pour `GAM-GAT-ORG-000001`, contrainte CHECK en base |
| Toute organization référence une identité active | vérification applicative dans `CreateOrganizationHandler` avant insertion |
| Au plus un membership actif par personne par organisation | contrainte UNIQUE sur `(person_id, organization_id)` WHERE `status = 'active'` (index partiel PostgreSQL) |
| Type de membership attribué par opérateur, jamais auto-déclaré | `membership_type` non exposé en auto-saisie dans aucune route publique |
| Suppression physique interdite | pas de `DELETE` dans aucun handler ; statuts `inactive`/`ended` uniquement |
| Suspension d'organisation → suspension des memberships | règle d'intégration dans `SuspendOrganizationHandler`, via événement `OrganizationSuspended` consommé par ce même contexte |
| Toute action auditée | même mécanisme de chaîne d'audit déjà en place, étendu à ce contexte |

### Décision technique différée, volontairement

Ce document ne tranche pas la profondeur maximale de l'arbre d'organisations ni les règles de déplacement d'une organisation vers un autre parent. Ces questions ne se posent pas à l'amorçage (GAMAD SAS et GAMAD Technologie sont deux niveaux fixes) et méritent leur propre ADR le jour où un cas réel les exige — pas une règle inventée à l'avance.

---

## Déclaration finale

L'Identity Registry sait qu'une entité existe. Persons and User Accounts sait qui elle est. Organizations and Memberships sait où elle s'inscrit et à quel titre.

Trois questions. Trois contextes. Aucun ne déborde sur les autres.

> GAMAD SAS est la racine. Tout ce qui vient après en descend. Rien ne lui est parallèle dans ce realm.
