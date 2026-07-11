# GENESIS-007A — Concepts et relations du GAMAD Core

## Version 0.1 — Modèle conceptuel initial

**Projet :** GAMAD Core  
**Statut :** Draft conceptuel validé pour construction progressive  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-003 — GAMAD Core Charter
- GENESIS-004 — Lexique Canonique du GAMAD Core
- GENESIS-005 — Les Lois du GAMAD Core
- GENESIS-006 — Atlas GAMAD
- GOVERNANCE-001 — Constitution de l’Écosystème Logiciel GAMAD
- MASTERPLAN-001 — Plan Directeur de l’Écosystème GAMAD
- ADR-0001 — Entity is an abstract concept, not a universal domain model
- ADR-0006 — The Core governs relationships before data

---

## 1. Objet du document

GENESIS-007A définit les concepts structurants du GAMAD Core et les relations canoniques qui les unissent.

Ce document ne décrit :

- ni des tables SQL ;
- ni des classes ;
- ni des contrôleurs ;
- ni un framework ;
- ni un schéma de base de données ;
- ni une API finale.

Il établit le langage conceptuel à partir duquel seront conçus les agrégats, contextes, contrats, modèles logiques et projections physiques du Core.

---

## 2. Principe directeur

GAMAD Core ne cherche pas à posséder toutes les données de l’écosystème.

Il gouverne principalement :

- les identités ;
- les responsabilités ;
- les appartenances ;
- les relations entre acteurs, organisations et ressources ;
- les autorisations ;
- les capacités ;
- les contrats ;
- les cycles de vie ;
- la traçabilité transversale.

Le Core gouverne les relations nécessaires à la cohérence commune, tandis que chaque module demeure propriétaire de ses données métier.

---

## 3. Convention d’identification conceptuelle

Chaque concept du modèle reçoit un identifiant stable de la forme `CM-XXX`.

Cet identifiant :

- sert aux ADR ;
- sert aux contrats ;
- sert aux revues d’architecture ;
- ne constitue pas un identifiant technique d’exécution ;
- reste stable même si le nom d’affichage évolue.

Chaque relation reçoit un identifiant stable de la forme `REL-XXX`.

---

# PARTIE I — CONCEPTS CANONIQUES

## CM-001 — Entity

Concept abstrait désignant toute réalité reconnue par GAMAD et susceptible de recevoir une identité persistante.

**Nature :** Abstraite.  
**Propriété :** Aucun domaine opérationnel universel n’en dérive automatiquement.

---

## CM-002 — Identity

Représentation persistante permettant au Core de reconnaître une entité dans le temps.

**Responsabilités conceptuelles :**

- continuité ;
- référence stable ;
- état identitaire ;
- fusion contrôlée ;
- révocation ;
- remplacement ;
- archivage.

---

## CM-003 — Person

Être humain reconnu indépendamment de ses comptes, rôles, sessions et appartenances.

---

## CM-004 — User Account

Compte permettant l’accès à une application ou à une surface autorisée.

Un User Account peut représenter une Person, mais ne la remplace jamais.

---

## CM-005 — Authentication Factor

Moyen permettant de vérifier le contrôle d’un compte ou d’une identité technique.

Exemples : mot de passe, clé, certificat, second facteur.

---

## CM-006 — Session

Contexte temporaire d’accès établi après authentification.

---

## CM-007 — Organization

Structure reconnue disposant d’une continuité, d’une gouvernance et d’une responsabilité propres.

---

## CM-008 — Organizational Unit

Sous-structure gouvernée appartenant à une Organization.

Elle peut représenter un département, une direction, une coordination ou une unité opérationnelle, sans devenir une Organization autonome par défaut.

---

## CM-009 — Membership

Relation gouvernée entre une Person et une Organization.

Elle porte notamment : statut, période, unité, fonction, source d’autorité et restrictions.

---

## CM-010 — Actor

Identité autorisée à accomplir une action dans un contexte déterminé.

Un Actor peut être humain ou technique.

---

## CM-011 — Authority

Identité humaine, organisationnelle ou système sous laquelle un Actor est autorisé à agir.

L’Authority répond à la question :

> Au nom de qui cette action est-elle accomplie ?

---

## CM-012 — Application

Système exécutable reconnu consommant ou exposant des contrats GAMAD.

---

## CM-013 — Service

Composant exécutable spécialisé fournissant une fonction délimitée par contrat.

---

## CM-014 — Agent

Application ou service installé dans un environnement contrôlé afin d’exécuter localement des capacités au nom de GAMAD.

---

## CM-015 — Device

Équipement ou instance matérielle reconnue et gouvernée.

---

## CM-016 — Resource

Unité gouvernée reconnue par le Core, possédant une identité, un responsable, un contexte de gouvernance et un système métier responsable.

---

## CM-017 — Resource Type

Classification contractuelle décrivant la nature gouvernée d’une Resource.

---

## CM-018 — Responsible Party

Identity ou Organization responsable de la gouvernance d’une Resource.

---

## CM-019 — System of Record

Application ou module officiellement responsable de la vérité métier d’un concept ou d’une Resource.

---

## CM-020 — Workspace

Contexte d’usage organisé regroupant des Actors, Resources et Capabilities.

---

## CM-021 — Module

Ensemble cohérent de capacités partageant une responsabilité fonctionnelle clairement délimitée.

---

## CM-022 — Capability

Fonction déclarée qu’un Module ou une Application sait fournir.

---

## CM-023 — Entitlement

Attribution d’une Capability à une Organization ou à un contexte gouverné.

---

## CM-024 — Permission

Autorisation explicite d’effectuer une Action sur une cible dans un contexte donné.

---

## CM-025 — Role

Ensemble nommé de responsabilités ou Permissions attribuables dans un contexte.

---

## CM-026 — Policy

Règle gouvernée influençant une décision, une contrainte ou un cycle de vie.

---

## CM-027 — Action

Opération canonique qu’un Actor peut demander ou accomplir sur une cible.

Exemples : lire, créer, modifier, partager, révoquer, approuver.

---

## CM-028 — Context

Ensemble de circonstances donnant un sens à une action ou une relation.

Un Context peut notamment inclure Organization, Workspace, Application, Session, Device, localisation logique, période et niveau de confiance.

---

## CM-029 — Access Decision

Résultat explicite d’une évaluation d’autorisation.

États conceptuels minimaux : autorisé, refusé, indéterminé, soumis à condition.

---

## CM-030 — Contract

Définition versionnée d’un échange autorisé entre producteurs et consommateurs.

---

## CM-031 — API

Surface versionnée exposant des opérations conformes à des Contracts.

---

## CM-032 — Command

Demande explicite d’exécuter une action future.

---

## CM-033 — Event

Fait immuable déclaré comme ayant eu lieu dans le passé.

---

## CM-034 — Response

Résultat contractuel d’une requête ou d’une Command.

---

## CM-035 — Audit Record

Preuve durable d’une action, décision ou modification significative.

---

## CM-036 — Lifecycle

Ensemble des états et transitions autorisés d’un objet gouverné.

---

## CM-037 — State

Condition reconnue d’un objet à un instant donné.

---

## CM-038 — Transition

Passage gouverné d’un State à un autre.

---

## CM-039 — Tenant

Partition technique d’isolation utilisée par une Application.

Un Tenant n’est jamais synonyme d’Organization.

---

## CM-040 — Subscription

Relation commerciale ou institutionnelle donnant origine à certains Entitlements.

La Subscription appartient au domaine commercial ; elle ne définit pas directement les Permissions opérationnelles.

---

# PARTIE II — RELATIONS CANONIQUES

## REL-001 — Entity receives Identity

```text
Entity ── reçoit ──> Identity
```

Toute entité reconnue possède au moins une référence identitaire persistante valide dans son contexte.

---

## REL-002 — Identity specializes into domain

```text
Identity ── est décrite par ──> Domain Profile
```

L’Identity Registry maintient la continuité ; le domaine spécialisé maintient les attributs métier.

---

## REL-003 — Person owns User Accounts

```text
Person 1 ── possède ──> 0..N User Accounts
```

Une Person peut exister sans User Account.

---

## REL-004 — User Account uses Authentication Factors

```text
User Account 1 ── utilise ──> 1..N Authentication Factors
```

Un moyen d’authentification compromis peut être révoqué sans supprimer la Person.

---

## REL-005 — Authentication creates Session

```text
Authentication réussie ── établit ──> Session
```

Une Session reste temporaire, limitée et révocable.

---

## REL-006 — Person joins Organization through Membership

```text
Person 1 ── Membership ──> 1 Organization
```

Une Person peut posséder plusieurs Memberships simultanés ou historiques.

---

## REL-007 — Organization contains Organizational Units

```text
Organization 1 ── contient ──> 0..N Organizational Units
```

Une Organizational Unit ne devient pas automatiquement une Organization.

---

## REL-008 — Actor acts under Authority

```text
Actor 1 ── agit sous ──> 1 Authority
```

Une action critique sans Authority explicite est invalide.

---

## REL-009 — Actor uses Application

```text
Actor ── agit via ──> Application
```

L’audit doit distinguer Actor, Authority et Application.

---

## REL-010 — Application runs on Device

```text
Application 0..N ── s’exécute sur ──> 0..N Devices
```

Cette relation dépend du type d’Application.

---

## REL-011 — Device hosts Agent

```text
Device 1 ── héberge ──> 0..N Agents
```

Un Agent doit posséder une identité technique distincte du Device.

---

## REL-012 — Resource has Resource Type

```text
Resource N ── est classée par ──> 1 Resource Type
```

Le Resource Type doit être versionné.

---

## REL-013 — Resource has Responsible Party

```text
Resource 1 ── est gouvernée par ──> 1..N Responsible Parties
```

Une responsabilité principale doit être identifiable lorsque plusieurs responsables existent.

---

## REL-014 — Resource has System of Record

```text
Resource 1 ── a pour source métier ──> 1 System of Record
```

Le Core conserve la référence et la gouvernance ; le System of Record conserve la vérité métier.

---

## REL-015 — Workspace groups Resources

```text
Workspace 1 ── regroupe ──> 0..N Resources
```

Une Resource peut apparaître dans plusieurs Workspaces si les politiques l’autorisent.

---

## REL-016 — Workspace admits Actors

```text
Workspace 1 ── autorise ──> 0..N Actors
```

L’admission ne constitue pas automatiquement une Permission sur toutes les Resources.

---

## REL-017 — Module provides Capabilities

```text
Module 1 ── fournit ──> 1..N Capabilities
```

Une Capability possède un fournisseur officiel.

---

## REL-018 — Organization receives Entitlements

```text
Organization 1 ── reçoit ──> 0..N Entitlements
```

Un Entitlement rend une Capability disponible, sans accorder toutes les Permissions aux utilisateurs.

---

## REL-019 — Subscription may originate Entitlement

```text
Subscription 0..1 ── génère ──> 0..N Entitlements
```

Certains Entitlements peuvent être institutionnels et ne dépendre d’aucune Subscription commerciale.

---

## REL-020 — Role groups Permissions

```text
Role 1 ── regroupe ──> 0..N Permissions
```

Une Permission peut également être accordée directement par une Policy ou un Grant futur.

---

## REL-021 — Actor receives Role in Context

```text
Actor ── reçoit Role ── dans ──> Context
```

Aucun Role n’est global par défaut.

---

## REL-022 — Permission authorizes Action on Target

```text
Permission ── autorise ──> Action + Target + Context
```

Une Permission sans Action, cible ou portée explicite est invalide.

---

## REL-023 — Policy constrains Access Decision

```text
Policy ── influence ──> Access Decision
```

Les politiques peuvent autoriser, refuser, conditionner ou restreindre.

---

## REL-024 — Access Decision evaluates Actor

```text
Access Decision = f(Actor, Authority, Action, Target, Context, Permissions, Policies)
```

Le résultat doit être traçable pour toute opération significative.

---

## REL-025 — Application consumes Contract

```text
Application ── consomme ──> Contract
```

La dépendance porte sur le Contract, non sur l’implémentation interne du fournisseur.

---

## REL-026 — API exposes Contract

```text
API ── expose ──> Contract
```

Une API sans Contract versionné n’est pas une surface officielle GAMAD.

---

## REL-027 — Command targets Responsible System

```text
Command ── est adressée à ──> System of Record ou Service responsable
```

Une Command doit posséder un destinataire responsable identifiable.

---

## REL-028 — Command may produce Response

```text
Command 1 ── produit ──> 0..1 Response
```

Une exécution asynchrone peut répondre par acceptation puis produire des Events ultérieurs.

---

## REL-029 — Command may produce Events

```text
Command 1 ── peut produire ──> 0..N Events
```

L’absence d’Event est acceptable uniquement lorsque le contrat le prévoit.

---

## REL-030 — Event refers to Actor and Context

```text
Event ── référence ──> Actor, Authority, Context et objet concerné
```

Un Event critique anonyme est interdit.

---

## REL-031 — Significant action creates Audit Record

```text
Action significative ── génère ──> Audit Record
```

Le niveau de détail dépend de la classification de l’action.

---

## REL-032 — Governed object follows Lifecycle

```text
Objet gouverné ── suit ──> Lifecycle
```

Identity, Organization, Membership, Application, Device, Resource, Module et Entitlement possèdent un cycle de vie explicite.

---

## REL-033 — Transition changes State

```text
State A ── Transition autorisée ──> State B
```

Toute Transition critique doit valider préconditions, Authority et audit.

---

## REL-034 — Tenant isolates application data

```text
Application ── isole via ──> Tenant
```

La relation entre Tenant et Organization doit être déclarée par contrat ; elle ne peut être supposée universelle.

---

# PARTIE III — CARDINALITÉS ET CONTRAINTES MAJEURES

## 4. Cardinalités conceptuelles

| Relation | Cardinalité conceptuelle |
|---|---|
| Person → User Account | 1 vers 0..N |
| Person → Membership | 1 vers 0..N |
| Organization → Membership | 1 vers 0..N |
| Organization → Organizational Unit | 1 vers 0..N |
| Device → Agent | 1 vers 0..N |
| Module → Capability | 1 vers 1..N |
| Organization → Entitlement | 1 vers 0..N |
| Role → Permission | 1 vers 0..N |
| Workspace → Resource | N vers N |
| Workspace → Actor | N vers N |
| Resource → Resource Type | N vers 1 |
| Resource → System of Record | N vers 1 |
| Governed Object → Lifecycle | N vers 1 version applicable |

Les cardinalités définitives pourront être raffinées dans GENESIS-007B et GENESIS-007C, mais toute modification devra préserver les Lois du Core.

---

## 5. Contraintes non négociables

1. Une Identity ne doit pas contenir toutes les données métier de l’Entity.
2. Une Person ne doit pas être remplacée par un User Account.
3. Un Actor doit agir sous une Authority explicite.
4. Une Resource doit posséder un Responsible Party identifiable.
5. Une Resource doit référencer un System of Record.
6. Une Permission doit préciser Action, cible et Context.
7. Une Capability ne doit pas être confondue avec une Permission.
8. Un Entitlement ne donne pas automatiquement des droits opérationnels.
9. Une Organization ne doit pas être confondue avec un Tenant.
10. Un Event ne doit pas représenter une intention future.
11. Une Command ne doit pas être diffusée comme un fait accompli.
12. Une API officielle doit exposer un Contract versionné.
13. Une action significative doit produire un Audit Record.
14. Une Transition critique doit être autorisée et auditée.
15. Aucun module ne devient propriétaire des concepts transversaux du Core.

---

# PARTIE IV — VUES CONCEPTUELLES

## 6. Vue identité et accès

```text
Person
  │
  ├── possède ──> Identity
  ├── possède ──> User Account
  │                  │
  │                  ├── utilise ──> Authentication Factor
  │                  └── établit ──> Session
  │
  └── rejoint ──> Membership ──> Organization

Actor ── agit sous ──> Authority
Actor ── agit via ──> Application
Actor ── est évalué par ──> Access Decision
```

---

## 7. Vue ressources

```text
Resource
  ├── possède ──> Identity
  ├── est classée par ──> Resource Type
  ├── est gouvernée par ──> Responsible Party
  ├── a pour vérité métier ──> System of Record
  ├── suit ──> Lifecycle
  └── peut appartenir à ──> Workspace
```

---

## 8. Vue modules et capacités

```text
Module
  └── fournit ──> Capability
                    │
Organization ── reçoit ──> Entitlement
                    │
Actor ── reçoit ──> Permission
                    │
Access Decision ── vérifie ──> Action sur Resource dans Context
```

---

## 9. Vue contrats et événements

```text
Application ── consomme ──> Contract
API ── expose ──> Contract
Command ── cible ──> Système responsable
Command ── produit ──> Response
Command ── peut produire ──> Event
Action significative ── produit ──> Audit Record
```

---

# PARTIE V — FRONTIÈRES DE MODÉLISATION

## 10. Ce que GENESIS-007A fixe

- les concepts canoniques initiaux ;
- leur identité documentaire stable ;
- les relations principales ;
- les cardinalités initiales ;
- les contraintes conceptuelles ;
- les vues transversales du Core.

## 11. Ce que GENESIS-007A ne fixe pas encore

- les agrégats ;
- les racines d’agrégat ;
- les bounded contexts ;
- les transactions ;
- les schémas de persistance ;
- les endpoints ;
- les formats d’identifiants ;
- les événements complets ;
- les règles de cohérence éventuelle ;
- les mécanismes de réplication.

Ces éléments seront traités dans les prochaines étapes.

---

# PARTIE VI — SUITE DU CHANTIER

## 12. GENESIS-007B

**Agrégats et frontières transactionnelles**

Objectifs :

- identifier les agrégats ;
- définir leurs racines ;
- protéger leurs invariants ;
- distinguer cohérence immédiate et cohérence éventuelle.

## 13. GENESIS-007C

**Bounded Contexts et responsabilités de domaine**

Objectifs :

- définir les contextes du Core ;
- préciser leurs frontières ;
- formaliser leurs contrats ;
- empêcher le couplage direct.

## 14. GENESIS-007D

**Projection logique et validation finale**

Objectifs :

- produire le modèle logique indépendant de la technologie ;
- vérifier les scénarios de référence ;
- préparer les spécifications de persistance et d’API.

---

## 15. Déclaration finale

GAMAD Core reconnaît des identités, gouverne des responsabilités et protège les relations qui permettent à l’écosystème de rester cohérent.

Les modules possèdent les données métier.

Le Core maintient les liens, les droits, les contrats et les preuves nécessaires à leur coopération durable.
