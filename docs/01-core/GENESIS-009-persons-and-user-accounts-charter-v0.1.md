# GENESIS-009 — Persons and User Accounts Charter

## Version 0.1 — Draft architectural

**Projet :** GAMAD Core — sous-phase 2/13 (MASTERPLAN-001 Phase 2)
**Statut :** Draft architectural, à valider avant implémentation
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD
**Architecture :** Claude — Architecte de GAMAD
**Dépendances :**
- GENESIS-003 — GAMAD Core Charter, §4.2 (identité), §6.1 (Identity Registry), §6.3 (Persons and Users)
- GENESIS-008 — Fédération des Cores souverains de GAMAD
- ADR-0017 — Format d'identité avec realm
- Realm en service : `GAT` (GAMAD Technologie)

---

## Préambule

L'Identity Registry sait qu'une entité existe, qu'elle a un type, un statut, un realm d'origine. Il ne sait rien d'autre — c'est voulu.

Persons and User Accounts est le premier domaine spécialisé qui donne un sens humain à une identité de type `person` : un nom, un moyen de contact, un compte, une façon de s'authentifier. Il ne remplace pas l'Identity Registry, il s'appuie dessus.

Ce document formalise ce domaine avant tout code, conformément à ADR-0004 (Governance precedes implementation) et ADR-0005 (Planning precedes conceptual modeling).

---

## 1. Mission

Persons and User Accounts fournit la source de vérité pour :

- les personnes reconnues par un realm GAMAD ;
- leurs moyens de contact déclarés ;
- les comptes utilisateurs qui leur sont associés ;
- les moyens d'authentification rattachés à ces comptes ;
- les sessions actives d'un compte.

Il ne fournit **pas** de source de vérité sur l'appartenance organisationnelle (Organizations, sous-phase 3), les rôles ou permissions (Access Control, sous-phase 6), ni les usages métier propres à chaque produit GAMAD (Drive, ERP...).

---

## 2. Principe fondateur : quatre notions, jamais confondues

GENESIS-003 §6.3 pose déjà cette exigence. Ce document la rend opérationnelle :

| Notion | Ce qu'elle est | Ce qu'elle n'est pas |
|---|---|---|
| **Personne** | Un être humain reconnu, référencé par une identité `GAM-{REALM}-PER-{NUMERO}` | Un compte, un email, un mot de passe |
| **Compte utilisateur** | Un moyen d'accéder aux services GAMAD, rattaché à une personne | La personne elle-même |
| **Authentification** | Le mécanisme prouvant qu'un compte est actionné légitimement (mot de passe, clé, OIDC externe...) | Un compte ou une session |
| **Session** | Une fenêtre temporelle d'usage authentifié d'un compte | Un compte ou une authentification |

**Une personne peut exister sans compte utilisateur actif** — par exemple une personne simplement référencée par une organisation avant qu'elle n'utilise elle-même un produit GAMAD. C'est un invariant hérité de GENESIS-003 §6.3, non négociable.

**Un compte peut avoir plusieurs moyens d'authentification** (mot de passe local, clé OIDC d'un produit fédéré, clé matérielle future) sans que cela ne multiplie les comptes ni les personnes.

---

## 3. Format d'identité et fédération

Toute personne enregistrée dans le realm `GAT` reçoit une identité au format défini par ADR-0017 :

```
GAM-GAT-PER-{NUMERO}
```

Persons and User Accounts ne stocke jamais son propre numéro d'identité indépendant — il référence systématiquement l'identité émise par l'Identity Registry du même realm. Une personne fédérée depuis un autre realm (une fois qu'une fédération existe, GENESIS-008 §5.2) conserve son identifiant d'origine tel quel ; ce domaine ne le réémet jamais sous le realm `GAT`.

---

## 4. Composantes fondamentales

1. **Person** — l'enregistrement minimal d'une personne reconnue : référence à son identité, nom déclaré, moyen(s) de contact, statut (actif, décédé, retiré...).
2. **UserAccount** — le compte permettant l'accès aux services, rattaché à une Person, avec son propre cycle de vie (créé, actif, suspendu, désactivé).
3. **AuthenticationMethod** — un moyen d'authentification rattaché à un UserAccount (mot de passe, fédération OIDC externe, futur : clé matérielle).
4. **Session** — une fenêtre d'usage authentifié, avec sa durée de vie, sa révocabilité, son traçage.

Cette liste décrit des responsabilités logiques, pas nécessairement quatre tables distinctes dès le premier jet — l'agrégat Person peut rester le point d'entrée transactionnel, à trancher en phase de modélisation conceptuelle (GENESIS-007 étendu à ce contexte).

---

## 5. Ce que ce domaine ne doit jamais devenir

- Un annuaire RH ou un CRM — il ne stocke pas l'historique professionnel, les évaluations, ou toute donnée métier propre à un produit.
- Un fournisseur d'identité universel (IdP) au sens technique complet — il peut déléguer l'authentification à un OIDC externe plutôt que réinventer un système de mots de passe robuste si ce n'est pas justifié dès cette sous-phase.
- Le propriétaire des rôles et permissions — ceux-ci relèvent d'Access Control (sous-phase 6) et de Memberships (sous-phase 4) pour le contexte organisationnel.

---

## 6. Invariants

1. Toute Person référence une identité existante et active dans l'Identity Registry du même realm.
2. Une Person peut exister sans UserAccount.
3. Un UserAccount référence exactement une Person, jamais zéro, jamais plusieurs.
4. La suppression physique d'une Person ou d'un UserAccount reste exceptionnelle — désactivation, suspension et anonymisation contrôlée sont privilégiées, conformément à GENESIS-003 §6.13.
5. Une Session ne peut exister sans authentification valide préalable et est toujours révocable indépendamment du compte lui-même.
6. Toute création, modification de statut ou révocation dans ce domaine est auditée, selon le même modèle de chaîne d'audit déjà en place pour l'Identity Registry.

---

## 7. Frontières avec l'Identity Registry existant

| | Identity Registry connaît | Persons and User Accounts connaît |
|---|---|---|
| Existence | qu'une identité `person` existe, son statut, son realm | qui est cette personne, comment la contacter |
| Accès | rien | comment cette personne s'authentifie, ses sessions |
| Cycle de vie | actif/suspendu/archivé/révoqué au niveau identité | actif/suspendu/désactivé au niveau compte, indépendamment |

Une identité suspendue par l'Identity Registry doit entraîner l'invalidation de toute session active du compte associé — c'est une règle d'intégration à implémenter explicitement, pas une conséquence automatique du modèle relationnel.

---

## 8. Prochaine étape de gouvernance

Avant tout code, conformément à ADR-0005, ce document doit être suivi d'un modèle conceptuel équivalent à GENESIS-007A-D mais scopé à ce contexte (concepts et relations, agrégats et frontières transactionnelles, bounded context, projection logique). Je le rédigerai sur validation de cette charte.

---

## 9. Déclaration finale

L'Identity Registry sait qu'une entité existe. Persons and User Accounts sait qui elle est, sans jamais savoir ce qu'elle fait dans GAMAD — ça, c'est le rôle des sous-phases suivantes.

La personne existe indépendamment du compte. Le compte existe indépendamment de l'authentification. L'authentification existe indépendamment de la session.

Chaque couche peut changer sans casser les autres.
