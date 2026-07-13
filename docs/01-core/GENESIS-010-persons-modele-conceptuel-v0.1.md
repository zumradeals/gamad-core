# GENESIS-010 — Concepts, agrégats et bounded context de Persons and User Accounts

## Version 0.1 — Draft architectural

**Projet :** GAMAD Core — sous-phase 2/13
**Statut :** Draft architectural, précède toute implémentation
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD
**Architecture :** Claude — Architecte de GAMAD
**Dépendances :**
- GENESIS-009 — Persons and User Accounts Charter
- GENESIS-007A-D — Concepts et relations du GAMAD Core (patron méthodologique repris ici)
- ADR-0013 — Frontière du noyau `Shared`
- Realm en service : `GAT`

---

## Préambule

Ce document transforme GENESIS-009 en modèle exploitable, avant tout schéma SQL — conformément à ADR-0005 (« aucun schéma SQL définitif ne précède cette phase », MASTERPLAN-001 Phase 1).

Il suit la même structure en quatre parties que GENESIS-007A-D pour l'Identity Registry : concepts et relations, agrégats et frontières transactionnelles, bounded context, projection logique.

---

## A — Concepts et relations

### Concepts

- **Person** — une personne reconnue, référençant une identité `GAM-GAT-PER-{NUMERO}` du realm en service, avec un nom déclaré, un ou plusieurs moyens de contact, un statut.
- **UserAccount** — un compte permettant l'accès aux services GAMAD, rattaché à une Person.
- **AuthenticationMethod** — un moyen concret de prouver la légitimité d'un accès à un UserAccount.
- **Session** — une fenêtre d'usage authentifié, née d'un événement d'authentification réussi.

### Relations

```
Identity (Identity Registry)
      │ 1
      │ référencée par
      ▼ 1
   Person
      │ 1
      │ possède
      ▼ 0..1
 UserAccount
      │ 1
      │ configure
      ▼ 0..N
AuthenticationMethod
      │ 1
      │ produit
      ▼ 0..N
   Session
```

### Cardinalité volontairement restreinte : 1 Person → 0..1 UserAccount

Une personne ne possède, dans cette version, **jamais plus d'un compte utilisateur au niveau du Core**. C'est la conséquence directe de la raison d'être du Core : un compte unique par personne, reconnu à travers tout l'écosystème fédéré, au lieu d'un compte par produit.

La possibilité de plusieurs comptes par personne n'est pas exclue par principe, mais elle n'est pas ouverte tant qu'un besoin réel ne l'exige pas — conformément à MASTERPLAN-001 §5 (« une fonctionnalité séduisante ne justifie jamais une dette architecturale dissimulée »).

---

## B — Agrégats et frontières transactionnelles

| Agrégat | Racine | Contient | Référence (par ID seulement) |
|---|---|---|---|
| **Person** | Person | nom déclaré, moyens de contact, statut | `identity_id` (Identity Registry) |
| **UserAccount** | UserAccount | statut du compte, date de création | `person_id` |
| — AuthenticationMethod | *(sous-entité de UserAccount)* | type, empreinte du secret, date d'ajout | — |
| **Session** | Session | jeton, émission, expiration, révocation | `user_account_id`, `authentication_method_id` |

**AuthenticationMethod n'est pas un agrégat indépendant.** Un moyen d'authentification n'a aucun sens hors du compte qui le porte — il vit et meurt avec son UserAccount, dans la même frontière transactionnelle. C'est le même raisonnement que celui déjà appliqué à `Identity` : l'agrégat protège un invariant (« un compte ne peut pas avoir un moyen d'authentification orphelin »), il n'est pas une simple collection d'objets.

**Session reste un agrégat séparé**, volontairement, pour deux raisons :
1. Volume d'écriture bien plus élevé et cycle de vie bien plus court que UserAccount — les coupler forcerait des verrous inutiles sur le compte à chaque connexion.
2. Une session doit pouvoir être révoquée indépendamment, y compris en masse (ex. « déconnecter tous les appareils »), sans jamais toucher à la configuration du compte lui-même.

**Person et UserAccount restent deux agrégats distincts**, jamais fusionnés, précisément pour préserver l'invariant GENESIS-003 §6.3 : une personne peut exister sans compte, donc l'un ne peut pas être une propriété interne de l'autre.

---

## C — Bounded context

Persons and User Accounts est un bounded context à part entière, distinct de l'Identity Registry.

### Ce que ce contexte possède, seul

Le vocabulaire *Person*, *UserAccount*, *AuthenticationMethod*, *Session* n'existe nulle part ailleurs dans le Core. L'Identity Registry ne connaît que le mot *Identity* ; il ne doit jamais apprendre ces nouveaux termes, conformément à ADR-0013.

### Sens du flux de dépendance

```
Persons and User Accounts ──lit──▶ Identity Registry
```

Persons and User Accounts consulte l'Identity Registry (existence, statut, realm d'une identité). L'inverse n'existe jamais : l'Identity Registry ne doit jamais interroger Persons and User Accounts pour fonctionner — il doit rester exploitable seul, comme il l'est aujourd'hui.

### Frontière avec Access Control (sous-phase 6, à venir)

Point de vigilance explicite pour la suite : **authentification ≠ autorisation**. Ce contexte répond à « qui es-tu, et est-ce bien toi ? ». Access Control répondra à « que peux-tu faire ? ». Une Session prouvée valide par ce contexte ne donne, par elle-même, aucun droit — c'est Access Control qui décidera, plus tard, ce qu'un acteur authentifié peut faire. Aucune règle de permission ne doit être codée dans Persons and User Accounts, même temporairement, même « pour aller vite ».

---

## D — Projection logique et validation

### Esquisse de schéma logique (pas encore un schéma SQL définitif)

```
persons
  identity_id      (PK, FK → identity registry, format GAM-GAT-PER-xxxxxx)
  declared_name
  status            (active | inactive | deceased)
  registered_at

user_accounts
  id                (PK)
  person_id         (FK unique → persons.identity_id)
  status            (active | suspended | disabled)
  created_at

authentication_methods
  id                (PK)
  user_account_id   (FK → user_accounts.id)
  method_type       (password | oidc_external | ...)
  credential_ref     (jamais le secret en clair)
  added_at

sessions
  id                (PK)
  user_account_id        (FK → user_accounts.id)
  authentication_method_id (FK → authentication_methods.id)
  issued_at
  expires_at
  revoked_at        (nullable)
```

### Validation contre les invariants de GENESIS-009 §6

| Invariant | Garanti par |
|---|---|
| Toute Person référence une identité existante et active | `identity_id` en clé primaire/étrangère vers l'Identity Registry du même realm — pas de Person orpheline possible |
| Une Person peut exister sans UserAccount | `person_id` est une FK optionnelle côté `user_accounts`, jamais l'inverse |
| Un UserAccount référence exactement une Person | FK unique et non nulle sur `user_accounts.person_id` |
| Suppression physique exceptionnelle | statuts `inactive`/`disabled` disponibles à chaque niveau, pas de `DELETE` en chemin normal |
| Session non révocable sans authentification valide préalable | FK obligatoire `authentication_method_id`, jamais nulle |
| Toute action auditée | même mécanisme d'audit en chaîne déjà en place pour l'Identity Registry, étendu à ce contexte |

### Décision technique différée, volontairement

Ce document ne tranche pas **comment** l'authentification sera réellement effectuée (mot de passe local géré par le Core, ou délégation systématique à un fournisseur OIDC externe — l'infrastructure `OidcRs256TokenVerifier` existe déjà côté `Shared` pour l'API d'administration). C'est une décision d'implémentation, pas de modèle conceptuel, et elle mérite sa propre ADR-0018 avant tout code sur `AuthenticationMethod`.

---

## Déclaration finale

Ce modèle ne fait rien d'autre que rendre explicite ce que GENESIS-009 énonçait déjà en prose : quatre concepts, quatre frontières, un seul sens de dépendance vers l'Identity Registry, et une ligne rouge déjà posée avec Access Control avant même qu'il n'existe.

Rien ici n'est encore du code. C'est la carte avant le territoire.
