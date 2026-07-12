# GENESIS-007C — Bounded Contexts et responsabilités de domaine du GAMAD Core

## Version 0.1 — Cartographie des contextes délimités

**Projet :** GAMAD Core  
**Statut :** Draft conceptuel validé pour préparation de l’architecture logique  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-003 — GAMAD Core Charter
- GENESIS-004 — Lexique Canonique du GAMAD Core
- GENESIS-005 — Les Lois du GAMAD Core
- GENESIS-006 — Atlas GAMAD
- GENESIS-007A — Concepts et relations du GAMAD Core
- GENESIS-007B — Agrégats et frontières transactionnelles
- GOVERNANCE-001 — Constitution de l’Écosystème Logiciel GAMAD
- MASTERPLAN-001 — Plan Directeur de l’Écosystème GAMAD
- ADR-0006 — The Core governs relationships before data
- ADR-0007 — Aggregates protect invariants, not object collections
- ADR-0008 — Bounded contexts own language, rules and data responsibility

---

## 1. Objet

GENESIS-007C définit les contextes délimités du GAMAD Core, leurs responsabilités, leurs langages locaux, leurs dépendances autorisées et les contrats qui les relient.

Ce document ne fixe pas encore :

- le découpage physique en dépôts ou services ;
- les bases de données ;
- les endpoints définitifs ;
- les files de messages ;
- les technologies d’intégration ;
- les schémas de déploiement.

Il fixe la séparation conceptuelle nécessaire pour empêcher qu’un même terme, une même règle ou une même donnée possède plusieurs autorités concurrentes.

---

## 2. Principe directeur

Un Bounded Context est une frontière dans laquelle :

- un langage possède un sens précis ;
- des règles sont cohérentes ;
- une équipe ou composante connaît sa responsabilité ;
- une source de vérité est identifiable ;
- les échanges avec l’extérieur passent par des contrats explicites.

Un Bounded Context n’est pas automatiquement :

- un microservice ;
- une base de données ;
- un dépôt Git ;
- un module UI ;
- un namespace technique.

Le GAMAD Core commence comme un monolithe modulaire. Les contextes sont d’abord des frontières logiques.

---

## 3. Règles générales

1. Chaque concept transverse possède un contexte propriétaire.
2. Un contexte peut référencer l’identité d’un objet externe, mais ne copie pas librement son modèle interne.
3. Les modèles locaux ne sont pas partagés comme objets communs entre contextes.
4. Les échanges passent par Command, Query, Event ou Contract explicitement versionnés.
5. Un contexte ne modifie jamais directement la persistance interne d’un autre.
6. Une dépendance n’est autorisée que si elle possède un sens métier clair.
7. Les dépendances circulaires sont interdites.
8. Toute duplication de donnée doit préciser son autorité, sa fraîcheur et sa stratégie de correction.
9. Un terme identique peut avoir un modèle local différent, à condition que sa traduction soit explicite.
10. Aucun contexte ne doit devenir le propriétaire implicite de tout le Core.

---

# PARTIE I — CONTEXTES DÉLIMITÉS DU CORE

## BC-001 — Identity Registry

### Mission

Maintenir la continuité identitaire des entités reconnues par GAMAD.

### Propriétaire de

- Identity ;
- Identity Type ;
- Identity Status ;
- Identity Link ;
- Merge Record ;
- Revocation Record ;
- Replacement Chain.

### Ne possède jamais

- profil métier complet ;
- mot de passe ;
- session ;
- appartenance ;
- permissions ;
- contenu de ressource.

### Langage local

Identity, identity type, identity state, merge, split, replacement, revocation, continuity.

### Agrégats principaux

- AGG-001 Identity Registry.

### Émet notamment

- IdentityRegistered ;
- IdentityActivated ;
- IdentitySuspended ;
- IdentityRevoked ;
- IdentityMerged ;
- IdentityReplaced.

### Dépendances autorisées

Aucune dépendance métier obligatoire vers un autre contexte pour protéger ses invariants propres.

---

## BC-002 — Persons and Accounts

### Mission

Représenter les personnes et les moyens d’accès qui leur sont éventuellement associés.

### Propriétaire de

- Person Profile ;
- User Account ;
- Authentication Factor metadata ;
- Account Status ;
- Recovery State.

### Ne possède jamais

- Identity canonique ;
- Membership ;
- rôle organisationnel ;
- Permission ;
- Session détaillée si celle-ci appartient au contexte d’accès.

### Langage local

Person, account, credential, factor, recovery, account state.

### Agrégats principaux

- AGG-002 Person ;
- AGG-003 User Account.

### Dépendances autorisées

- BC-001 pour référencer l’Identity d’une Person ;
- BC-005 pour déléguer l’établissement de Session ;
- BC-003 pour projeter l’existence de Memberships, sans les posséder.

---

## BC-003 — Organizations and Memberships

### Mission

Gouverner les structures reconnues, leurs unités et les relations d’appartenance.

### Propriétaire de

- Organization ;
- Organizational Unit ;
- Membership ;
- organizational role assignment ;
- membership lifecycle ;
- hierarchy relationships.

### Ne possède jamais

- compte utilisateur ;
- Permission opérationnelle ;
- Tenant technique ;
- abonnement commercial ;
- contenu métier des départements.

### Langage local

Organization, unit, membership, appointment, affiliation, hierarchy, active membership.

### Agrégats principaux

- AGG-004 Organization ;
- AGG-005 Membership.

### Dépendances autorisées

- BC-001 pour les Identity references ;
- BC-002 pour la Person de référence ;
- BC-006 pour exposer des informations utiles aux décisions d’accès ;
- BC-008 pour recevoir des Entitlements organisationnels.

---

## BC-004 — Applications, Services and Devices

### Mission

Enregistrer et gouverner les composants techniques capables d’agir dans l’écosystème.

### Propriétaire de

- Application ;
- Service ;
- Agent ;
- Device ;
- technical identity binding ;
- application status ;
- device trust state ;
- credential rotation metadata.

### Ne possède jamais

- Permission finale ;
- Resource content ;
- Organization ;
- Contract definition complète ;
- Session humaine.

### Langage local

Application, service, agent, device, registration, trust, credential, environment, revocation.

### Agrégats principaux

- AGG-006 Application ;
- AGG-007 Device ;
- AGG-008 Agent Registration.

### Dépendances autorisées

- BC-001 pour Identity ;
- BC-005 pour sessions et authentication techniques ;
- BC-006 pour autorisation ;
- BC-010 pour contrats consommés ou exposés.

---

## BC-005 — Authentication and Sessions

### Mission

Établir, maintenir et révoquer les contextes temporaires d’accès.

### Propriétaire de

- Authentication Attempt ;
- Session ;
- Token Family ;
- MFA Challenge ;
- Session Revocation ;
- Authentication Assurance Level.

### Ne possède jamais

- Identity métier ;
- Permission ;
- Membership ;
- Role ;
- Resource ;
- Policy globale.

### Langage local

Authenticate, challenge, factor, session, refresh, revoke, assurance, device binding.

### Agrégats principaux

- AGG-009 Session ;
- AGG-010 Authentication Flow.

### Dépendances autorisées

- BC-002 pour User Account ;
- BC-004 pour Application et Device ;
- BC-006 pour transmettre le contexte d’accès, sans décider des droits métier.

---

## BC-006 — Access Control

### Mission

Décider si un Actor peut accomplir une Action sur une cible dans un Context donné.

### Propriétaire de

- Permission ;
- Role ;
- Grant ;
- Access Decision ;
- authorization scope ;
- denial reason ;
- delegated authority ;
- access evaluation model.

### Ne possède jamais

- Authentication ;
- Organization complète ;
- Resource content ;
- Capability commerciale ;
- politique métier interne d’un module, sauf sa projection contractuelle.

### Langage local

Actor, authority, action, target, scope, role, grant, decision, permit, deny, condition.

### Agrégats principaux

- AGG-011 Role ;
- AGG-012 Permission Grant ;
- AGG-013 Access Decision Policy Set.

### Dépendances autorisées

- BC-001 pour Identity ;
- BC-003 pour Organization et Membership facts ;
- BC-004 pour Application et Device facts ;
- BC-005 pour Session context ;
- BC-007 pour Resource references ;
- BC-009 pour Policies ;
- BC-008 pour Capabilities et Entitlements.

### Règle critique

BC-006 peut consommer des faits issus de plusieurs contextes, mais reste seul propriétaire de la décision d’accès transversale.

---

## BC-007 — Resource Registry

### Mission

Maintenir le registre transverse des ressources gouvernées sans absorber leur contenu métier.

### Propriétaire de

- Resource ;
- Resource Type ;
- Responsible Party binding ;
- System of Record reference ;
- resource lifecycle ;
- resource relationship metadata ;
- governance classification.

### Ne possède jamais

- contenu de document ;
- fichier binaire ;
- corps de courriel ;
- conflit Sync détaillé ;
- historique métier complet ;
- donnée sectorielle propre au module.

### Langage local

Resource, type, responsible party, system of record, lifecycle, governance, reference.

### Agrégats principaux

- AGG-014 Resource Registry ;
- AGG-015 Resource Type Registry.

### Dépendances autorisées

- BC-001 pour Identity ;
- BC-003 pour Organization references ;
- BC-004 pour System of Record Application ;
- BC-006 pour autorisation ;
- BC-009 pour classification et rétention.

---

## BC-008 — Modules, Capabilities and Entitlements

### Mission

Déclarer les modules, les capacités qu’ils fournissent et les droits de capacité attribués aux organisations.

### Propriétaire de

- Module ;
- Capability ;
- Module Dependency ;
- Entitlement ;
- quota definition ;
- activation state ;
- capability compatibility.

### Ne possède jamais

- Permission utilisateur ;
- Subscription complète si elle relève du domaine commercial ;
- logique métier du module ;
- facturation ;
- données d’usage détaillées non nécessaires à l’Entitlement.

### Langage local

Module, capability, entitlement, dependency, activation, quota, limit, provider.

### Agrégats principaux

- AGG-016 Module Registry ;
- AGG-017 Entitlement.

### Dépendances autorisées

- BC-003 pour Organization ;
- BC-004 pour Application fournisseur ;
- BC-006 pour vérifier les Permissions ;
- BC-010 pour déclarer les Contracts du module.

---

## BC-009 — Policy and Lifecycle Governance

### Mission

Définir les politiques transversales et les règles de cycle de vie applicables aux objets gouvernés.

### Propriétaire de

- Policy ;
- Policy Version ;
- Policy Scope ;
- Lifecycle Definition ;
- State ;
- Transition Rule ;
- retention rule ;
- classification rule.

### Ne possède jamais

- décision d’accès finale ;
- objet métier complet ;
- exécution technique de suppression ;
- workflow propre à un module lorsqu’il n’est pas transversal.

### Langage local

Policy, scope, condition, precedence, lifecycle, state, transition, retention, classification.

### Agrégats principaux

- AGG-018 Policy ;
- AGG-019 Lifecycle Definition.

### Dépendances autorisées

- BC-006 comme consommateur de politiques d’accès ;
- BC-007 comme consommateur de politiques de ressource ;
- BC-008 comme consommateur de politiques de capacité ;
- BC-012 pour audit de changement.

---

## BC-010 — Contracts and API Governance

### Mission

Maintenir les contrats d’interopérabilité, leurs versions et les règles de compatibilité.

### Propriétaire de

- Contract ;
- Contract Version ;
- API Definition metadata ;
- Producer Registration ;
- Consumer Registration ;
- compatibility rule ;
- deprecation notice ;
- error contract ;
- idempotency rule.

### Ne possède jamais

- implémentation des endpoints ;
- donnée métier échangée au-delà du schéma contractuel ;
- Permission d’accès ;
- journal technique complet.

### Langage local

Contract, producer, consumer, version, compatibility, deprecation, schema, error, idempotency.

### Agrégats principaux

- AGG-020 Contract Registry.

### Dépendances autorisées

- BC-004 pour producteurs et consommateurs ;
- BC-008 pour modules fournisseurs ;
- BC-011 pour Event Contracts ;
- BC-012 pour audit des publications et dépréciations.

---

## BC-011 — Commands, Events and Process Coordination

### Mission

Gouverner les messages d’intention, les faits publiés et la coordination explicite entre contextes.

### Propriétaire de

- Command Envelope ;
- Event Envelope ;
- correlation metadata ;
- causation metadata ;
- process state ;
- retry policy metadata ;
- dead-letter classification ;
- process manager definition.

### Ne possède jamais

- vérité métier des agrégats producteurs ;
- contenu fonctionnel complet des modules ;
- décision d’accès ;
- audit métier final.

### Langage local

Command, Event, correlation, causation, process, retry, compensation, delivery, idempotency.

### Agrégats principaux

- Process Manager Aggregate ;
- Delivery Tracking Aggregate.

### Dépendances autorisées

- BC-010 pour Contracts ;
- tous les contextes comme producteurs ou consommateurs, via contrats versionnés ;
- BC-012 pour corrélation d’audit.

### Règle critique

BC-011 coordonne ; il ne devient jamais propriétaire des invariants métier des autres contextes.

---

## BC-012 — Audit and Evidence

### Mission

Maintenir la preuve durable des actions, décisions et changements significatifs.

### Propriétaire de

- Audit Record ;
- Audit Classification ;
- Evidence Chain ;
- retention metadata ;
- integrity seal metadata ;
- actor and authority references ;
- correlation reference.

### Ne possède jamais

- logs techniques complets ;
- données secrètes inutiles ;
- contenu métier complet ;
- décision d’accès elle-même ;
- Event métier comme source de vérité unique.

### Langage local

Audit, evidence, actor, authority, action, target, result, integrity, retention, correlation.

### Agrégats principaux

- Audit Stream ;
- Evidence Seal.

### Dépendances autorisées

Tous les contextes peuvent produire des faits audités via contrat ; aucun contexte ne peut modifier directement les Audit Records.

---

# PARTIE II — CONTEXTES EXTERNES AU CORE

## 4. Contextes produits

Les produits GAMAD restent des contextes distincts du Core.

Exemples :

- GAMAD Drive Context ;
- GAMAD Share Context ;
- GAMAD Sync Context ;
- GAMAD Mail Context ;
- GAMAD Docs Context ;
- GAMAD Copilote Context ;
- GAMAD Hub Context.

Ils peuvent consommer :

- Identity ;
- Organization ;
- Access Decision ;
- Resource Registry ;
- Capability ;
- Contract ;
- Audit.

Ils ne peuvent pas :

- redéfinir l’identité transverse ;
- créer leurs propres permissions concurrentes sans traduction vers le Core ;
- modifier directement les tables du Core ;
- se déclarer source de vérité d’un concept appartenant au Core.

---

## 5. Contextes institutionnels

Le mouvement GAMAD, sa gouvernance religieuse, sociale et institutionnelle ne sont pas réductibles au GAMAD Core.

Les contextes numériques peuvent représenter certaines structures, fonctions et appartenances par contrat, mais ne deviennent jamais l’autorité spirituelle, doctrinale ou institutionnelle de GAMAD.

---

# PARTIE III — CARTOGRAPHIE DES RELATIONS ENTRE CONTEXTES

## 6. Types de relation autorisés

### Customer–Supplier

Un contexte fournisseur publie un contrat stable ; un contexte consommateur exprime ses besoins sans accéder à l’interne.

### Conformist

Un contexte consommateur adopte le contrat du fournisseur lorsque le langage est suffisamment stable.

### Anti-Corruption Layer

Un contexte traduit un modèle externe afin de protéger son propre langage.

### Published Language

Un langage contractuel versionné est partagé sans partager les modèles internes.

### Open Host Service contrôlé

Un contexte expose une surface officielle à plusieurs consommateurs, sous gouvernance API.

### Separate Ways

Deux contextes restent indépendants lorsque l’intégration coûterait plus de cohérence qu’elle n’en apporterait.

---

## 7. Context Map synthétique

```text
BC-001 Identity Registry
   ├── fournit Identity refs à BC-002, BC-003, BC-004, BC-007
   └── ne dépend d’aucun contexte métier pour ses invariants

BC-002 Persons and Accounts
   ├── fournit Person/Account facts à BC-003 et BC-005
   └── consomme Identity refs de BC-001

BC-003 Organizations and Memberships
   ├── fournit Organization/Membership facts à BC-006 et BC-008
   └── consomme Identity/Person refs

BC-004 Applications, Services and Devices
   ├── fournit technical actor facts à BC-005, BC-006, BC-010
   └── consomme Identity refs

BC-005 Authentication and Sessions
   ├── fournit Session context à BC-006
   └── consomme Account/Application/Device facts

BC-006 Access Control
   ├── fournit Access Decision aux produits et contextes Core
   └── consomme faits, Policies, Resource refs et Entitlements

BC-007 Resource Registry
   ├── fournit Resource refs et governance metadata
   └── consomme Identity, Organization, Application et Policy refs

BC-008 Modules, Capabilities and Entitlements
   ├── fournit Capability/Entitlement facts à BC-006 et produits
   └── consomme Organization/Application refs

BC-009 Policy and Lifecycle Governance
   ├── fournit Policies à BC-006, BC-007, BC-008
   └── ne prend pas les décisions finales des contextes consommateurs

BC-010 Contracts and API Governance
   ├── fournit Published Language et versioning
   └── consomme producteurs, consommateurs et modules déclarés

BC-011 Commands, Events and Process Coordination
   ├── coordonne les workflows inter-contextes
   └── ne possède aucun invariant métier externe

BC-012 Audit and Evidence
   ├── reçoit des faits audités de tous les contextes
   └── interdit toute mutation externe directe
```

---

# PARTIE IV — MATRICE DE RESPONSABILITÉ

## 8. Source de vérité par concept

| Concept | Contexte propriétaire |
|---|---|
| Identity | BC-001 |
| Person Profile | BC-002 |
| User Account | BC-002 |
| Organization | BC-003 |
| Membership | BC-003 |
| Application | BC-004 |
| Service | BC-004 |
| Agent | BC-004 |
| Device | BC-004 |
| Session | BC-005 |
| Permission | BC-006 |
| Role | BC-006 |
| Access Decision | BC-006 |
| Resource | BC-007 |
| Resource Type | BC-007 |
| Module | BC-008 |
| Capability | BC-008 |
| Entitlement | BC-008 |
| Policy | BC-009 |
| Lifecycle Definition | BC-009 |
| Contract | BC-010 |
| API Governance metadata | BC-010 |
| Command/Event envelope | BC-011 |
| Process coordination state | BC-011 |
| Audit Record | BC-012 |

---

## 9. Interdictions explicites

- BC-002 ne décide pas des Permissions.
- BC-003 ne crée pas de Session.
- BC-004 ne s’auto-autorise pas.
- BC-005 ne déduit pas les droits métier.
- BC-006 ne stocke pas le contenu des Resources.
- BC-007 ne devient pas un stockage universel.
- BC-008 ne transforme pas Entitlement en Permission.
- BC-009 ne remplace pas la logique métier des modules.
- BC-010 ne contient pas l’implémentation des API.
- BC-011 ne devient pas propriétaire des agrégats coordonnés.
- BC-012 ne devient pas un entrepôt général de données.

---

# PARTIE V — COHÉRENCE ET INTÉGRATION

## 10. Cohérence immédiate

La cohérence immédiate reste interne à un agrégat appartenant à un seul contexte.

Exemples :

- une Identity ne peut être activée et révoquée simultanément ;
- un Membership ne peut être actif hors de sa période ;
- une Session révoquée ne peut rester valide ;
- une Resource doit conserver un System of Record ;
- une Capability doit avoir un Module fournisseur.

---

## 11. Cohérence éventuelle

La cohérence éventuelle s’applique aux projections inter-contextes.

Exemples :

- révocation d’un Membership puis recalcul des Access Decisions ;
- suspension d’une Application puis révocation progressive de ses Sessions ;
- retrait d’un Entitlement puis désactivation contrôlée d’une Capability ;
- archivage d’une Resource puis mise à jour des Workspaces ;
- dépréciation d’un Contract puis notification des consommateurs.

Ces processus exigent :

- événement versionné ;
- idempotence ;
- reprise ;
- visibilité d’état ;
- compensation explicite lorsque nécessaire ;
- audit.

---

## 12. Anti-Corruption Layers

Une Anti-Corruption Layer est obligatoire lorsque :

- un système externe utilise un vocabulaire incompatible ;
- un ancien module emploie des identifiants ou statuts non canoniques ;
- GAMAD Drive actuel est intégré progressivement au Core ;
- une API partenaire impose son propre modèle ;
- une branche métier possède des concepts plus riches que le Core.

L’ACL traduit ; elle ne modifie pas le Canon pour s’adapter à une intégration particulière.

---

# PARTIE VI — FRONTIÈRES D’ÉQUIPE ET DE CODE

## 13. Ownership

Chaque Bounded Context doit posséder :

- un responsable fonctionnel ;
- un responsable technique ;
- une documentation ;
- un catalogue de contrats ;
- une matrice de permissions ;
- un catalogue d’événements ;
- des tests d’invariants ;
- une politique de version ;
- un plan de migration.

---

## 14. Organisation initiale du monolithe modulaire

La structure logique recommandée est :

```text
src/
  IdentityRegistry/
  PersonsAccounts/
  OrganizationsMemberships/
  ApplicationsDevices/
  AuthenticationSessions/
  AccessControl/
  ResourceRegistry/
  ModulesCapabilities/
  PolicyLifecycle/
  ContractsApiGovernance/
  MessagingCoordination/
  AuditEvidence/
```

Cette structure est illustrative. Elle ne constitue pas encore une décision d’implémentation définitive.

---

## 15. Règles de dépendance dans le code

- les domaines internes n’importent pas les modèles persistants d’un autre contexte ;
- les dépendances passent par interfaces ou contrats ;
- les DTO contractuels ne deviennent pas automatiquement des entités de domaine ;
- les adaptateurs traduisent entre modèle externe et modèle interne ;
- les migrations de données respectent les propriétaires de concepts ;
- les tests d’architecture doivent détecter les imports interdits.

---

# PARTIE VII — VALIDATION DU DÉCOUPAGE

## 16. Test de légitimité d’un contexte

Un contexte est légitime s’il peut répondre clairement :

1. Quel langage possède-t-il ?
2. Quelle vérité protège-t-il ?
3. Quels concepts possède-t-il ?
4. Quels concepts ne possède-t-il pas ?
5. Quels contrats expose-t-il ?
6. Quels contrats consomme-t-il ?
7. Quels invariants sont internes ?
8. Quels workflows exigent une coordination ?
9. Quelle équipe est responsable ?
10. Peut-il évoluer sans imposer son modèle interne aux autres ?

---

## 17. Signaux d’un mauvais découpage

- contexte défini uniquement par une table ;
- même concept possédé par plusieurs contextes ;
- dépendances circulaires ;
- transactions traversant plusieurs contextes ;
- modèle partagé importé partout ;
- contexte « Common » sans responsabilité claire ;
- contexte créé seulement pour correspondre à une équipe temporaire ;
- contexte incapable d’expliquer son langage local ;
- duplication de données sans System of Record.

---

# PARTIE VIII — SUITE DU CHANTIER

## 18. GENESIS-007D — Projection logique et validation finale

GENESIS-007D devra :

- consolider Concepts, Agrégats et Bounded Contexts ;
- produire le modèle logique complet ;
- définir les identifiants conceptuels et références ;
- préciser les objets de valeur ;
- formaliser les scénarios transversaux ;
- vérifier les invariants par cas d’usage ;
- préparer l’architecture de référence et les spécifications techniques.

---

## 19. Déclaration finale

Le GAMAD Core n’est pas un ensemble de fonctions rassemblées dans une même application.

Il est une fédération cohérente de contextes délimités, chacun responsable de son langage, de ses invariants et de ses données.

Les contextes coopèrent par contrats.

Ils ne se confondent jamais.
