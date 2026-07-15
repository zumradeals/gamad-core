# GENESIS-011 — Organizations and Memberships Charter

## Version 0.1 — Draft architectural

**Projet :** GAMAD Core — sous-phase 3/13 (MASTERPLAN-001 Phase 2)
**Statut :** Draft architectural, à valider avant implémentation
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD
**Architecture :** Claude — Architecte de GAMAD
**Dépendances :**
- GENESIS-003 — GAMAD Core Charter §6.4 (Organizations), §6.5 (Memberships)
- GENESIS-008 — Fédération des Cores souverains
- GENESIS-009 — Persons and User Accounts Charter
- IDENTITY-001 — Charte symbolique et identitaire fondatrice de GAMAD
- ADR-0017 — Format d'identité avec realm
- Realm en service : `GAT`

---

## Préambule

L'Identity Registry sait qu'une entité existe. Persons and User Accounts sait qui elle est. Organizations and Memberships répond à une troisième question, la plus institutionnelle : **dans quelle structure une personne s'inscrit-elle, et à quel titre ?**

C'est ici que GAMAD SAS, GAMAD Technologie, et toute structure future reconnue par GAMAD prendront existence dans le Core numérique — pas comme de simples entrées de base de données, mais comme des organisations souveraines, hiérarchisées, avec leurs membres, leurs départements, et leurs catégories d'appartenance.

---

## 1. Mission

Organizations and Memberships fournit la source de vérité pour :

- les organisations reconnues par un realm GAMAD, leur hiérarchie, leur statut ;
- les départements internes à une organisation ;
- les memberships : la relation formelle entre une personne et une organisation, avec son type d'appartenance et son cycle de vie.

Il ne fournit **pas** de source de vérité sur les permissions (Access Control, sous-phase 4/13), ni sur les ressources gérées (Resource Registry, sous-phase 5/13).

---

## 2. Modèle d'organisation

### 2.1 Structure arborescente

Le modèle d'organisation est **hiérarchique**. Une organisation peut avoir une organisation parente. Il n'existe qu'une seule organisation sans parent : **GAMAD SAS**, racine de l'arbre institutionnel du realm `GAT`.

```
GAMAD SAS (racine — pas de parent)
└── GAMAD Technologie
    └── (futures structures créées par GAMAD Technologie)
```

Toute organisation du Core est soit GAMAD SAS, soit un descendant direct ou indirect de GAMAD SAS. Aucune organisation orpheline ne peut exister dans ce realm — une structure ne peut être créée sans désigner son organisation parente (sauf GAMAD SAS, créée à l'amorçage).

### 2.2 Format d'identité

Toute organisation reçoit une identité au format ADR-0017 :

```
GAM-GAT-ORG-{NUMERO}
```

GAMAD SAS : `GAM-GAT-ORG-000001`
GAMAD Technologie : `GAM-GAT-ORG-000002`

### 2.3 Département

Un département est une subdivision interne d'une organisation — il n'a pas d'identité propre au sens de l'Identity Registry (ce n'est pas une entité souveraine), mais il est référençable dans un membership. Exemples : Direction Générale, Direction Technique, Direction Commerciale.

---

## 3. Taxonomie des memberships

### 3.1 Trois types d'appartenance institutionnelle

| Code | Libellé institutionnel | Sens |
|---|---|---|
| `GAMAD_CITIZEN` | JE SUIS GAMAD | Personne dont l'appartenance est idéologique et spirituelle au sens fondateur de GAMAD — le niveau d'engagement le plus profond |
| `ORDINARY_CITIZEN` | JE TRAVAILLE POUR GAMAD | Personne qui travaille au service de GAMAD, partage ses valeurs fondamentales, sans nécessairement appartenir à la même tradition spirituelle fondatrice |
| `PARTNER` | JE TRAVAILLE AVEC GAMAD | Personne ou institution qui collabore avec GAMAD sans remettre en cause son idéologie, dans un cadre de partenariat |

Ces trois types sont des **types de membership institutionnel**, décidés par la gouvernance de GAMAD, jamais auto-déclarés par une personne sur un formulaire.

### 3.2 Ce que le Core ne stocke pas sur un membership

Les critères confessionnels et spirituels qui fondent chacune de ces catégories (appartenance à la Tarîqa Tijâniyya Hamawiyya, confession religieuse, etc.) restent dans la gouvernance institutionnelle de GAMAD — dans IDENTITY-001 et les textes du Mouvement. Le Core stocke le **type de membership attribué**, jamais les détails confessionnels qui ont conduit à ce type. Cette séparation protège les personnes et préserve la souveraineté des données sensibles.

### 3.3 Quatrième situation : l'utilisateur sans membership

Une personne peut exister dans le Core (Persons and User Accounts) sans aucun membership actif. C'est le cas des utilisateurs quotidiens de l'écosystème GAMAD qui interagissent avec les produits (GamaDrive, futur ERP...) sans appartenir institutionnellement à GAMAD. Ils sont des personnes reconnues, pas des membres. Cette distinction est structurelle, pas secondaire.

### 3.4 Cycle de vie d'un membership

Un membership a un statut : `active`, `suspended`, `ended`. Il a une date de début, une date de fin optionnelle. Sa suspension ou sa fin est un événement audité, jamais une suppression physique.

---

## 4. Invariants

1. GAMAD SAS est la seule organisation sans parent dans le realm `GAT`. Elle est créée à l'amorçage, jamais supprimée, jamais déplacée sous un autre parent.
2. Toute organisation référence une identité `GAM-GAT-ORG-{NUMERO}` existante et active dans l'Identity Registry.
3. Une organisation peut avoir zéro ou plusieurs organisations filles, mais exactement zéro ou un parent.
4. Un membership lie exactement une personne (`GAM-GAT-PER-{NUMERO}`) à exactement une organisation (`GAM-GAT-ORG-{NUMERO}`), avec un type d'appartenance parmi les trois définis.
5. Une personne peut avoir plusieurs memberships actifs simultanément dans des organisations différentes.
6. Une personne peut avoir au plus un membership actif par organisation à un instant donné.
7. Le type de membership est attribué par un opérateur habilité, jamais auto-déclaré.
8. La suppression physique d'une organisation ou d'un membership est interdite — statuts `inactive`/`ended` et audit obligatoires.
9. La suspension d'une organisation doit entraîner la suspension de tous ses memberships actifs — règle d'intégration explicite, pas une conséquence automatique du modèle relationnel.
10. Toute création, modification de statut ou fin de membership est auditée selon le même modèle de chaîne d'audit déjà en place.

---

## 5. Frontières avec les sous-phases voisines

| | Persons and User Accounts | Organizations and Memberships | Access Control (futur) |
|---|---|---|---|
| Question | Qui est cette personne ? | Dans quelle structure s'inscrit-elle, et à quel titre ? | Que peut-elle faire ? |
| Stocke | Nom, contact, compte | Organisation, département, type d'appartenance | Rôles, permissions, politiques |
| Ne stocke pas | Membership | Permissions | Membership |

**Ligne rouge explicite** : aucune règle de permission ou de droit d'accès ne doit être codée dans ce contexte, même provisoirement, même pour « aller plus vite ». Le type de membership (`GAMAD_CITIZEN`, etc.) n'est pas un rôle d'accès — c'est une catégorie institutionnelle. La confusion entre les deux est la principale dette architecturale à éviter ici.

---

## 6. Amorçage institutionnel

À la livraison de cette sous-phase, deux opérations d'amorçage seront effectuées via `bin/` (jamais via une route HTTP publique) :

1. **Créer GAMAD SAS** (`GAM-GAT-ORG-000001`) comme organisation racine, sans parent.
2. **Créer GAMAD Technologie** (`GAM-GAT-ORG-000002`) comme organisation fille de GAMAD SAS.
3. **Rattacher Zakaria Le SOUFI** (`GAM-GAT-PER-000001`) à GAMAD SAS et GAMAD Technologie avec le type `GAMAD_CITIZEN` — premier membership du Core, audité comme tel.

---

## 7. Prochaine étape de gouvernance

Ce document sera suivi, sur validation, du modèle conceptuel (GENESIS-012) avant toute implémentation — concepts et relations, agrégats et frontières transactionnelles, projection logique, conformément au patron établi par GENESIS-007/010.

---

## 8. Déclaration finale

Organizations and Memberships est l'endroit où GAMAD cesse d'être une idée et devient une structure reconnaissable dans son propre système. GAMAD SAS y existe comme racine — pas comme une entrée parmi d'autres, mais comme le point de départ de tout ce qui viendra après.

> Une organisation sans parent est une fondation. Un membership sans ambiguïté est une dignité.
