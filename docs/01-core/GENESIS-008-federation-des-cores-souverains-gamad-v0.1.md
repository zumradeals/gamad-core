# GENESIS-008 — Fédération des Cores souverains de GAMAD

## Version 0.1 — Draft architectural validé

**Projet :** Écosystème GAMAD
**Statut :** Draft architectural validé pour dépôt
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD
**Architecture :** Claude — Architecte de GAMAD
**Dépendances :**
- IDENTITY-001 — Charte symbolique et identitaire fondatrice de GAMAD
- GENESIS-003 — GAMAD Core Charter
- MASTERPLAN-001 — Plan Directeur de l'Écosystème GAMAD, Phase 9 (Infrastructure physique et souveraine)
- ADR-0002 — Canonical vocabulary changes require architectural decision
- ADR-0004 — Governance precedes implementation

---

## Préambule

GENESIS-003 a défini GAMAD Core comme le noyau souverain de l'écosystème numérique GAMAD, sans trancher une question qu'il posait implicitement : souverain *par rapport à qui*, et *combien de noyaux existent* ?

Ce document tranche cette question. Il ne remplace pas GENESIS-003 — il l'étend, à l'endroit précis où l'écosystème peut cesser d'être un point unique pour devenir une fédération de points souverains, chacun légitime, aucun supérieur aux autres.

Cette décision est fondatrice. Elle précède toute construction ultérieure des sous-phases du Core (Persons, Organizations, et suivantes), parce qu'elle détermine la forme même de l'identité que ces sous-phases manipuleront.

---

## 1. Principe fondateur

> **GAMAD est Tout. Mais tout n'est pas GAMAD.** (IDENTITY-001 §4)

Ce principe s'applique aussi au numérique. Il ne doit jamais exister **un seul GAMAD Core mondial** qui centraliserait techniquement l'identité de toutes les structures, personnes et ressources reconnues par GAMAD, où qu'elles soient.

GAMAD Core est un **modèle**, pas un **serveur unique**. Chaque structure légitime peut faire tourner sa propre instance souveraine de ce modèle. Ces instances ne fusionnent jamais leurs données. Elles peuvent, sous conditions strictes, se reconnaître mutuellement.

---

## 2. Vocabulaire canonique

Ce vocabulaire complète le Lexique Canonique (GENESIS-004) et lui est subordonné en cas de conflit futur.

### 2.1 Realm

Un **realm** est l'espace d'identité souverain d'une instance du GAMAD Core. Chaque realm possède ses propres identités, sa propre chaîne d'audit, sa propre base de persistance, et n'est jamais administré à distance par un autre realm.

### 2.2 Structure porteuse

La **structure porteuse** d'un realm est l'organisation légitime, identifiable et responsable qui opère techniquement et institutionnellement ce Core. Une structure porteuse peut être une entreprise, une association affiliée à GAMAD, une institution, ou une branche GAMAD elle-même.

Un realm n'est **jamais** un territoire, un pays ou une zone géographique en tant que tels. Un pays n'est pas un opérateur technique et GAMAD n'a reçu d'aucune nation l'autorité de représenter son identité numérique.

### 2.3 Juridiction

La **juridiction** d'un realm est l'ensemble des lois et régulations auxquelles sa structure porteuse est soumise du fait de son lieu d'opération. La juridiction est un attribut du realm, renseigné séparément — elle ne fait jamais partie de l'identifiant d'une identité.

Une structure porteuse peut être soumise à plusieurs juridictions. Une juridiction peut couvrir plusieurs structures porteuses distinctes.

### 2.4 Identité fédérée

Une identité est dite **fédérée** lorsqu'un realm reconnaît, sous contrat, une identité émise par un autre realm sans en devenir le propriétaire.

### 2.5 Core d'origine

Le **Core d'origine** d'une identité est le realm qui l'a émise. Il reste, en toute circonstance, la seule source de vérité pour cette identité — sa modification, sa suspension, sa révocation.

---

## 3. Invariants de fédération

Ces invariants s'ajoutent à ceux de GENESIS-003 §9 et bénéficient du même niveau de stabilité (Niveau 1).

1. Un realm ne modifie jamais une identité dont il n'est pas le Core d'origine.
2. Un realm ne stocke jamais la copie complète des données d'une identité étrangère — seulement les références et les droits nécessaires à sa propre gouvernance locale.
3. La reconnaissance d'une identité étrangère est toujours explicite, contractuelle et révocable — jamais automatique ou implicite.
4. Chaque realm conserve sa propre chaîne d'audit, intègre et vérifiable indépendamment des autres realms.
5. Aucun realm n'a d'autorité hiérarchique sur un autre realm. La fédération est une relation entre pairs, jamais une relation d'administration.
6. La création d'un nouveau realm est un acte de gouvernance institutionnelle, jamais une décision purement technique.
7. Un realm peut cesser de faire confiance à un autre realm sans que cela n'invalide les identités déjà émises par ce dernier dans son propre espace.
8. Aucune identité ne change de Core d'origine. Une identité peut être révoquée par son realm d'origine ; elle ne peut jamais être réémise ailleurs sous le même identifiant.

---

## 4. Ce que la fédération n'est pas

Pour éviter toute dérive future, il est explicitement rappelé que la fédération de Cores souverains :

- n'est pas une réplication de base de données entre realms ;
- n'est pas un annuaire central listant toutes les identités de tous les realms ;
- n'est pas une hiérarchie où un realm « GAMAD Monde » superviserait les autres ;
- n'est pas un mécanisme de contrôle politique ou doctrinal — la fédération est une question d'interopérabilité technique et de gouvernance opérationnelle, jamais une extension de l'autorité institutionnelle ou spirituelle de GAMAD, laquelle reste régie exclusivement par IDENTITY-001 et les statuts du Mouvement ;
- n'oblige aucune structure porteuse à fédérer son realm avec un autre. La fédération est une capacité, jamais une obligation.

---

## 5. Gouvernance de la fédération

### 5.1 Création d'un realm

La création d'un nouveau realm doit répondre, avant toute mise en service technique, aux questions suivantes :

1. Quelle structure porteuse en est responsable ?
2. Sous quelle(s) juridiction(s) opère-t-elle ?
3. Cette structure est-elle légitime au regard d'IDENTITY-001 §15-16 ?
4. Le realm sera-t-il, à terme, candidat à une fédération avec d'autres realms GAMAD, ou restera-t-il isolé ?
5. Qui, au sein de la structure porteuse, assume la responsabilité de gouvernance de ce Core ?

Une structure qui ne peut répondre clairement à ces questions n'est pas prête à opérer un realm.

### 5.2 Établissement d'une fédération entre deux realms

L'établissement d'une relation de confiance entre deux realms exige :

- un contrat de fédération écrit, versionné, précisant les types d'identités reconnus, les garanties d'audit exigées et les conditions de révocation ;
- une validation par l'autorité de gouvernance de chacune des deux structures porteuses ;
- une capacité technique de vérification indépendante — un realm doit pouvoir prouver qu'une identité qu'il reconnaît existe réellement dans le Core d'origine, sans avoir à lui faire une confiance aveugle.

Ce contrat relève, par nature, des « Contracts and API Governance » de GENESIS-003 §6.10, étendus au cas où le consommateur du contrat est un autre Core et non une application.

### 5.3 Aucun changement silencieux

Toute évolution du modèle de fédération — ajout d'un nouveau realm, modification des règles de reconnaissance, retrait d'un realm — suit les mêmes exigences que IDENTITY-001 §19 : autorité proposante, raison, éléments concernés, conséquences, stratégie de migration, date d'entrée en vigueur.

---

## 6. Architecture logique

```text
        Realm A (structure porteuse A)         Realm B (structure porteuse B)
        Core souverain — juridiction A          Core souverain — juridiction B
        ┌─────────────────────────┐             ┌─────────────────────────┐
        │  Identités  · Audit     │             │  Identités  · Audit     │
        │  Chaîne propre          │             │  Chaîne propre          │
        └────────────┬────────────┘             └────────────┬────────────┘
                      │                                       │
                      └──────────── Contrat de fédération ────┘
                                  (reconnaissance mutuelle,
                                   révocable, non hiérarchique)
```

Il n'existe pas de nœud central au-dessus des realms. La fédération est un maillage de relations bilatérales explicites, pas une étoile autour d'un Core suprême.

---

## 7. Conséquences immédiates sur GAMAD Core

Cette charte a une conséquence technique directe et non différable : l'identifiant d'identité actuel (`GAM-{TYPE}-{NUMERO}`) ne porte aucune information de realm et devient ambigu dès qu'un second realm existe.

Cette conséquence est traitée par ADR-0017, adopté conjointement à ce document.

---

## 8. Déclaration finale

GAMAD ne construit pas un empire numérique unique. Il construit un modèle que chaque structure légitime peut porter chez elle, souverainement, dans le respect des lois de son territoire, sans jamais perdre son lien avec les autres realms qui partagent la même origine et la même idéologie fondatrice.

Le Core est reproductible. La souveraineté de chaque instance est réelle. La fédération est un choix, jamais une fusion.

> **Un realm par structure porteuse. Une identité par Core d'origine. Une fédération par confiance explicite, jamais par défaut.**
