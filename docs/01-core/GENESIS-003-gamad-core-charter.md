# GENESIS-003 — GAMAD Core Charter

## Version 0.1 — Draft architectural validé

**Projet :** GAMAD Core  
**Statut :** Draft architectural validé pour dépôt  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-001 — Livre Blanc de GAMAD
- GENESIS-002 — Charte Fondatrice de GAMAD
- ADR-0001 — Entity is an abstract concept, not a universal domain model

---

## Préambule

GAMAD Core est le noyau souverain de l’écosystème GAMAD.

Il n’est ni un produit, ni une application, ni une interface utilisateur. Il constitue la fondation commune sur laquelle peuvent être construits tous les produits, modules et services présents ou futurs de l’écosystème GAMAD.

Sa mission première est de préserver la cohérence, la continuité, l’intégrité et l’interopérabilité de l’ensemble.

Les applications créent les usages. Le Core maintient les invariants.

---

## 1. Mission

GAMAD Core fournit l’autorité commune à tous les systèmes reconnus dans l’écosystème GAMAD.

Il constitue la source officielle de vérité pour :

- les identités reconnues ;
- les organisations et leurs relations ;
- les appartenances ;
- les acteurs ;
- les ressources enregistrées ;
- les autorisations ;
- les modules et capacités ;
- les applications et services reconnus ;
- les politiques communes ;
- les contrats d’interopérabilité ;
- la gouvernance des API ;
- la traçabilité transversale.

Chaque module demeure source de vérité pour ses propres données métier.

---

## 2. Vision

GAMAD Core est conçu pour survivre aux technologies qui l’implémentent.

Son identité ne dépend :

- ni d’un langage ;
- ni d’une base de données ;
- ni d’un framework ;
- ni d’un protocole particulier ;
- ni d’un système d’exploitation ;
- ni d’une interface utilisateur.

Les concepts et contrats doivent pouvoir traverser plusieurs générations technologiques sans perdre leur cohérence.

---

## 3. Rôle du Core

Le Core gouverne les fondations communes de l’écosystème.

Il répond notamment aux questions suivantes :

- Qu’est-ce qui est reconnu dans GAMAD ?
- Quelle identité persistante lui est attribuée ?
- Dans quel contexte organisationnel existe-t-il ?
- Qui agit ?
- Sur quelle ressource ?
- Avec quelle autorisation ?
- Selon quelle politique ?
- Par quel contrat ?
- Comment l’action est-elle tracée ?

Le Core ne répond pas aux questions métier propres aux modules.

---

## 4. Principes ontologiques

### 4.1 L’entité

Une entité est toute réalité humaine, organisationnelle, numérique ou technique reconnue par GAMAD et susceptible de recevoir une identité persistante.

`Entity` est un concept canonique abstrait. Il ne constitue pas un domaine métier universel, une table générale obligatoire ni un objet parent chargé de contenir tous les objets du système.

### 4.2 L’identité

L’identité est la représentation persistante permettant au Core de reconnaître une entité dans le temps.

L’identité ne doit pas être confondue avec :

- un compte utilisateur ;
- une authentification ;
- une session ;
- un acteur ;
- un profil métier.

### 4.3 Le domaine spécialisé

Chaque catégorie significative conserve son propre domaine spécialisé.

Exemples :

- Persons ;
- Organizations ;
- Applications ;
- Resources ;
- Devices ;
- Services.

L’Identity Registry maintient la continuité identitaire. Les domaines spécialisés donnent leur sens aux entités et restent propriétaires de leurs données métier.

---

## 5. Composantes fondamentales

GAMAD Core comprend les composantes logiques suivantes :

1. Identity Registry
2. Organizations
3. Persons and Users
4. Memberships
5. Applications and Actors
6. Resources
7. Access Control
8. Modules and Capabilities
9. Policies
10. Contracts and API Governance
11. Events
12. Audit
13. Lifecycle Management

Cette liste décrit des responsabilités logiques. Elle n’impose pas une distribution physique en services séparés.

---

## 6. Responsabilités du Core

### 6.1 Identity Registry

Maintenir l’identité persistante des entités reconnues, leur catégorie, leur état identitaire et leur continuité.

Il peut gérer notamment :

- création d’une identité ;
- activation ;
- suspension ;
- révocation ;
- fusion contrôlée ;
- remplacement ;
- archivage ;
- relations identitaires essentielles.

Il ne contient pas toute la vie métier de l’entité.

### 6.2 Organizations

Maintenir les organisations reconnues, leurs statuts, leurs relations structurelles et leurs contextes de gouvernance.

Une organisation ne doit pas être confondue avec un tenant technique, un département ou un abonnement.

### 6.3 Persons and Users

Distinguer clairement :

- la personne réelle reconnue ;
- le compte utilisateur ;
- les moyens d’authentification ;
- les sessions.

Une personne peut exister sans compte utilisateur actif.

### 6.4 Memberships

Maintenir les relations entre une personne et une organisation, avec leur statut, durée, rôle organisationnel et contexte.

### 6.5 Applications and Actors

Enregistrer les applications, services, agents et automatisations reconnus.

Une identité devient acteur lorsqu’elle est autorisée à accomplir une action dans un contexte donné.

L’audit doit pouvoir distinguer :

- l’acteur technique ;
- l’autorité humaine ou organisationnelle ;
- l’application utilisée ;
- le contexte d’exécution.

### 6.6 Resources

Maintenir l’identité, la responsabilité, le système propriétaire, l’état et le contexte de gouvernance des ressources.

Toute ressource possède :

- un responsable clairement identifiable ;
- un contexte de gouvernance explicite ;
- un système métier responsable ;
- un cycle de vie ;
- une politique d’accès.

Le Core ne stocke pas nécessairement le contenu métier de la ressource.

### 6.7 Access Control

Fournir un langage commun d’autorisation fondé sur les acteurs, actions, objets, contextes, rôles, permissions, scopes et politiques.

Le moteur doit pouvoir répondre :

> Cet acteur peut-il effectuer cette action sur cet objet dans ce contexte ?

### 6.8 Modules and Capabilities

Déclarer les modules et capacités disponibles, leurs dépendances, leurs états d’activation, leurs limites et leurs autorisations par organisation.

### 6.9 Policies

Définir les règles transversales applicables à différents niveaux : global, organisation, unité, module, ressource, utilisateur, appareil ou application.

### 6.10 Contracts and API Governance

Maintenir les contrats d’interopérabilité et les règles de gouvernance API :

- versions ;
- schémas ;
- scopes ;
- préconditions ;
- postconditions ;
- erreurs ;
- compatibilité ;
- dépréciation ;
- révocation ;
- idempotence ;
- exigences d’audit.

### 6.11 Events

Maintenir le catalogue des types d’événements, leurs contrats, producteurs autorisés, règles de publication et exigences d’audit.

Un événement décrit un fait passé. Il ne constitue pas une commande déguisée.

Le Core ne doit pas devenir le stockage obligatoire de tous les événements métier détaillés produits par les modules.

### 6.12 Audit

Fournir une traçabilité transversale durable permettant de savoir :

- qui ou quoi a agi ;
- sous quelle autorité ;
- par quelle application ;
- dans quelle organisation ;
- sur quelle ressource ;
- avec quelle décision d’accès ;
- à quel moment ;
- avec quel résultat.

Un log technique n’est pas un audit métier.

### 6.13 Lifecycle Management

Définir les cycles de vie communs et les transitions autorisées.

La suppression physique doit rester exceptionnelle. Le Core privilégie la désactivation, la suspension, la révocation, l’archivage, la retraite et l’anonymisation contrôlée.

---

## 7. Ce que le Core ne doit jamais devenir

GAMAD Core ne doit jamais absorber la logique métier des produits et modules.

Il ne devient pas :

- un Drive ;
- un ERP ;
- un CRM ;
- une messagerie ;
- un éditeur documentaire ;
- un moteur de synchronisation ;
- un moteur de sauvegarde ;
- un réseau social ;
- un moteur IA ;
- un outil de santé ;
- une plateforme de transport ;
- une interface universelle.

Ces responsabilités appartiennent aux systèmes spécialisés.

---

## 8. Frontières de responsabilité

Le Core connaît les références nécessaires à la gouvernance. Les modules connaissent leurs données métier.

| Domaine | Le Core connaît | Le module responsable connaît |
|---|---|---|
| Document | identité, ressource, droits, état | contenu, format, édition, versions métier |
| Courriel | référence, responsable, liens | corps, pièces jointes, conversation |
| Backup | politique, état, événements transversaux | archives, chiffrement, restauration |
| Sync | appareils, autorisations, état global | files, blocs, conflits détaillés |
| Copilote | droit d’usage, quota, audit | contexte, génération, conversations |
| Hub | identité du système et autorisations | publications, interactions, vie communautaire |

Aucun module ne doit lire directement les tables internes d’un autre module.

---

## 9. Invariants

GAMAD Core garantit les invariants suivants :

1. Toute entité reconnue possède une identité persistante.
2. Toute action importante possède un acteur identifiable.
3. Toute ressource possède un responsable et un contexte de gouvernance explicites.
4. Toute autorisation est explicite et vérifiable.
5. Aucun module ne contourne les contrats communs.
6. Les données métier restent dans leur domaine responsable.
7. Les contrats sont versionnés.
8. Les actions importantes sont auditables.
9. Une désactivation ne signifie jamais une suppression implicite.
10. Les implémentations peuvent changer sans modifier les concepts canoniques.

---

## 10. Règles de conception

Toute nouvelle composante ou capacité candidate doit répondre avant implémentation à ces questions :

1. Pourquoi existe-t-elle ?
2. Quelle responsabilité possède-t-elle ?
3. Que ne doit-elle jamais posséder ?
4. Quelles données gouverne-t-elle ?
5. Quels contrats expose-t-elle ?
6. Quels contrats consomme-t-elle ?
7. Comment est-elle autorisée ?
8. Comment est-elle auditée ?
9. Quel est son cycle de vie ?
10. Peut-elle être remplacée sans casser l’écosystème ?

Une capacité dont les frontières restent ambiguës n’est pas prête à être développée.

---

## 11. Architecture logique

```text
                    CLIENTS ET MODULES
        Web · Agent · Mobile · Drive · Hub · Mail · Docs
                            │
                            ▼
                     API GAMAD CORE
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
  Identity & Actors   Access & Policies   Resources & Modules
        │                   │                   │
        └───────────────────┼───────────────────┘
                            │
                 Contracts · Events · Audit
                            │
                            ▼
                    PERSISTENCE CORE
```

Le Core doit commencer comme un monolithe modulaire bien structuré. La distribution physique en plusieurs services ne sera admise que lorsqu’un besoin démontré l’exigera.

---

## 12. Neutralité technologique

Les choix techniques sont des décisions d’implémentation documentées par ADR.

Aucun langage, framework, moteur de base de données ou protocole n’est constitutif de l’identité du Core.

Les technologies sont remplaçables. Les contrats et concepts canoniques demeurent.

---

## 13. Gouvernance du Core

Toute évolution du Core doit :

- respecter le Livre Blanc de GAMAD ;
- respecter la Charte Fondatrice ;
- respecter les ADR applicables ;
- préserver les invariants ;
- maintenir des frontières explicites ;
- documenter les changements de contrat ;
- éviter les dépendances cachées ;
- maintenir une voie de migration ou de compatibilité.

Aucune fonctionnalité séduisante ne justifie la violation des fondations.

---

## 14. Niveaux de stabilité

Le Core distingue trois niveaux :

### Niveau 1 — Invariants

Ils changent exceptionnellement.

### Niveau 2 — Contrats

Ils évoluent de manière versionnée et compatible.

### Niveau 3 — Implémentations

Elles peuvent être remplacées tant que les contrats et invariants restent respectés.

---

## 15. Déclaration finale

GAMAD Core est l’autorité souveraine qui maintient les identités, organisations, ressources, droits, capacités, politiques, contrats et traces communes de l’écosystème GAMAD.

Les modules construisent les usages.

Le Core maintient la cohérence.

L’entité existe.

L’identité permet à GAMAD de la reconnaître.

Le domaine spécialisé permet à GAMAD de comprendre sa fonction.

La technologie évolue.

Le Core demeure.
