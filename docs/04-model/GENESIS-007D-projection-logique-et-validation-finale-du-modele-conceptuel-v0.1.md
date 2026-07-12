# GENESIS-007D — Projection logique et validation finale du modèle conceptuel du GAMAD Core

## Version 0.1 — Validation de métamodèle

**Projet :** GAMAD Core  
**Statut :** Draft logique validé pour passage à l’architecture de référence  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-003 — GAMAD Core Charter
- GENESIS-004 — Lexique Canonique du GAMAD Core
- GENESIS-005 — Les Lois du GAMAD Core
- GENESIS-006 — Atlas GAMAD
- GENESIS-007A — Concepts et relations
- GENESIS-007B — Agrégats et frontières transactionnelles
- GENESIS-007C — Bounded Contexts et responsabilités de domaine
- GOVERNANCE-001 — Constitution de l’Écosystème Logiciel GAMAD
- MASTERPLAN-001 — Plan Directeur de l’Écosystème GAMAD
- ADR-0009 — Logical model precedes physical model

---

## 1. Objet

GENESIS-007D consolide le modèle conceptuel du GAMAD Core et démontre qu’il peut être projeté en architecture logique sans dépendre d’une technologie particulière.

Il relie :

- concepts ;
- relations ;
- agrégats ;
- bounded contexts ;
- responsabilités ;
- commandes ;
- événements ;
- contrats ;
- scénarios de validation.

Ce document n’autorise encore aucune projection physique définitive. Les schémas SQL, classes, endpoints, files de messages et composants d’infrastructure seront dérivés ultérieurement dans la collection ARCHITECTURE.

---

## 2. Principe de validation

Le modèle est considéré valide seulement si :

1. chaque concept possède un propriétaire logique unique ;
2. chaque invariant possède un agrégat responsable ;
3. chaque bounded context possède une responsabilité explicite ;
4. chaque dépendance traverse une frontière par contrat ;
5. aucune relation ne crée de dépendance circulaire non maîtrisée ;
6. les scénarios de référence peuvent être exécutés sans violer les Lois du Core ;
7. les données métier restent dans leur System of Record ;
8. les opérations critiques restent auditables ;
9. le modèle demeure indépendant du framework et de la base de données.

---

# PARTIE I — PROJECTION LOGIQUE DU CORE

## 3. Vue générale

```text
                           GAMAD CORE

 ┌───────────────────────────────────────────────────────────┐
 │ Identity Registry                                        │
 │ Persons & Accounts                                       │
 │ Organizations & Memberships                              │
 │ Applications, Services & Devices                         │
 │ Authentication & Sessions                                │
 │ Access Control                                           │
 │ Resource Registry                                        │
 │ Modules, Capabilities & Entitlements                     │
 │ Policy & Lifecycle Governance                            │
 │ Contracts & API Governance                               │
 │ Commands, Events & Process Coordination                  │
 │ Audit & Evidence                                         │
 └───────────────────────────────────────────────────────────┘
                              │
                              ▼
                 Produits et modules GAMAD
          Drive · Share · Sync · Docs · Mail · Hub · Copilote
```

Le Core gouverne les identités, responsabilités, droits, ressources enregistrées, contrats et preuves transversales. Les produits restent propriétaires de leur logique et de leurs données métier.

---

## 4. Matrice de propriété conceptuelle

| Concept | Bounded Context propriétaire | Agrégat principal |
|---|---|---|
| Identity | Identity Registry | Identity Aggregate |
| Person | Persons and Accounts | Person Aggregate |
| User Account | Persons and Accounts | User Account Aggregate |
| Authentication Factor | Authentication and Sessions | Credential Aggregate |
| Session | Authentication and Sessions | Session Aggregate |
| Organization | Organizations and Memberships | Organization Aggregate |
| Organizational Unit | Organizations and Memberships | Organization Aggregate |
| Membership | Organizations and Memberships | Membership Aggregate |
| Application | Applications, Services and Devices | Application Aggregate |
| Service | Applications, Services and Devices | Application Aggregate |
| Agent | Applications, Services and Devices | Agent Aggregate |
| Device | Applications, Services and Devices | Device Aggregate |
| Actor | Access Control | Actor Context Projection |
| Authority | Access Control | Authority Assignment Aggregate |
| Resource | Resource Registry | Resource Aggregate |
| Resource Type | Resource Registry | Resource Type Aggregate |
| Responsible Party | Resource Registry | Resource Aggregate |
| System of Record | Resource Registry | Resource Aggregate |
| Workspace | Resource Registry ou produit responsable selon contrat | Workspace Aggregate |
| Module | Modules, Capabilities and Entitlements | Module Aggregate |
| Capability | Modules, Capabilities and Entitlements | Module Aggregate |
| Entitlement | Modules, Capabilities and Entitlements | Entitlement Aggregate |
| Permission | Access Control | Permission Grant Aggregate |
| Role | Access Control | Role Aggregate |
| Policy | Policy and Lifecycle Governance | Policy Aggregate |
| Action | Access Control | Canon de permissions |
| Context | Access Control | Valeur contextuelle composée |
| Access Decision | Access Control | Decision Record |
| Contract | Contracts and API Governance | Contract Aggregate |
| API | Contracts and API Governance | API Surface Aggregate |
| Command | Commands, Events and Process Coordination | Command Envelope |
| Event | Commands, Events and Process Coordination | Event Envelope |
| Response | Contracts and API Governance | Response Contract |
| Audit Record | Audit and Evidence | Audit Aggregate |
| Lifecycle | Policy and Lifecycle Governance | Lifecycle Aggregate |
| State | Policy and Lifecycle Governance | Lifecycle Aggregate |
| Transition | Policy and Lifecycle Governance | Lifecycle Aggregate |
| Tenant | Application propriétaire | Projection technique hors Canon métier |
| Subscription | Domaine commercial futur | Hors Core, source possible d’Entitlement |

### Règle

Un concept transversal possède un seul propriétaire logique. Les autres contextes n’en conservent que des références, projections ou vues dérivées.

---

## 5. Matrice de dépendances entre contextes

| Contexte consommateur | Peut dépendre de | Ne doit jamais dépendre directement de |
|---|---|---|
| Identity Registry | Audit, Lifecycle | Données métier des produits |
| Persons and Accounts | Identity Registry, Authentication | Resource Registry interne |
| Organizations and Memberships | Identity Registry, Audit | Tables de produits |
| Applications, Services and Devices | Identity Registry, Audit | Sessions humaines internes |
| Authentication and Sessions | Persons and Accounts, Applications & Devices, Audit | Permissions métier internes des produits |
| Access Control | Identity, Organizations, Applications, Resources, Policies | Contenu métier des ressources |
| Resource Registry | Identity, Organizations, Applications, Lifecycle, Audit | Contenu documentaire, messages, sauvegardes |
| Modules, Capabilities and Entitlements | Organizations, Contracts, Audit | Données commerciales détaillées |
| Policy and Lifecycle Governance | Concepts de référence, Audit | Implémentations internes des modules |
| Contracts and API Governance | Identity des applications, Audit | Bases internes des consommateurs |
| Commands, Events and Process Coordination | Contracts, Audit | Mutations directes d’agrégats étrangers |
| Audit and Evidence | Références stables de tous les contextes | Modification des états métier |

### Direction générale

```text
Identité et registres fondamentaux
            ↓
Responsabilités, ressources et capacités
            ↓
Décisions, contrats et coordination
            ↓
Audit et preuves
```

L’Audit observe sans gouverner les états métier. Les contextes ne doivent pas dépendre de leurs propres projections d’audit pour prendre une décision métier courante.

---

# PARTIE II — CATALOGUE LOGIQUE INITIAL

## 6. Familles de commandes

### Identité

- `RegisterIdentity`
- `ActivateIdentity`
- `SuspendIdentity`
- `RevokeIdentity`
- `MergeIdentities`
- `RetireIdentity`

### Personnes et comptes

- `RegisterPerson`
- `CreateUserAccount`
- `SuspendUserAccount`
- `AttachAuthenticationFactor`
- `RevokeAuthenticationFactor`

### Organisations et appartenances

- `CreateOrganization`
- `CreateOrganizationalUnit`
- `AddMembership`
- `SuspendMembership`
- `RevokeMembership`

### Applications et appareils

- `RegisterApplication`
- `RegisterDevice`
- `RegisterAgent`
- `AuthorizeApplicationScope`
- `RevokeApplication`
- `RevokeDevice`

### Ressources

- `RegisterResource`
- `AssignResponsibleParty`
- `ChangeSystemOfRecord`
- `TransitionResourceState`
- `ArchiveResource`

### Accès

- `CreateRole`
- `GrantPermission`
- `RevokePermission`
- `AssignRole`
- `EvaluateAccess`

### Modules et capacités

- `RegisterModule`
- `DeclareCapability`
- `GrantEntitlement`
- `SuspendEntitlement`
- `RetireModule`

### Contrats et gouvernance API

- `RegisterContract`
- `PublishContractVersion`
- `DeprecateContractVersion`
- `RegisterApiSurface`

### Politiques et cycles de vie

- `PublishPolicy`
- `RetirePolicy`
- `RegisterLifecycle`
- `AuthorizeTransition`

---

## 7. Familles d’événements

### Identity.*

- `IdentityRegistered`
- `IdentityActivated`
- `IdentitySuspended`
- `IdentityRevoked`
- `IdentitiesMerged`
- `IdentityRetired`

### Person.* et Account.*

- `PersonRegistered`
- `UserAccountCreated`
- `UserAccountSuspended`
- `AuthenticationFactorAttached`
- `AuthenticationFactorRevoked`

### Organization.* et Membership.*

- `OrganizationCreated`
- `OrganizationalUnitCreated`
- `MembershipAdded`
- `MembershipSuspended`
- `MembershipRevoked`

### Application.* Device.* Agent.*

- `ApplicationRegistered`
- `ApplicationRevoked`
- `DeviceRegistered`
- `DeviceRevoked`
- `AgentRegistered`
- `AgentRevoked`

### Resource.*

- `ResourceRegistered`
- `ResponsiblePartyAssigned`
- `SystemOfRecordChanged`
- `ResourceStateChanged`
- `ResourceArchived`

### Access.*

- `RoleCreated`
- `RoleAssigned`
- `PermissionGranted`
- `PermissionRevoked`
- `AccessDecisionIssued`

### Module.* Capability.* Entitlement.*

- `ModuleRegistered`
- `CapabilityDeclared`
- `EntitlementGranted`
- `EntitlementSuspended`
- `ModuleRetired`

### Contract.* Policy.* Lifecycle.*

- `ContractVersionPublished`
- `ContractVersionDeprecated`
- `PolicyPublished`
- `PolicyRetired`
- `LifecycleRegistered`

### Audit.*

- `AuditRecordSealed`
- `AuditIntegrityViolationDetected`

---

## 8. Familles de contrats

- Identity Registry Contracts
- Person and Account Contracts
- Organization and Membership Contracts
- Application and Device Contracts
- Authentication and Session Contracts
- Access Decision Contracts
- Resource Registry Contracts
- Module and Capability Contracts
- Entitlement Contracts
- Policy Evaluation Contracts
- Lifecycle Transition Contracts
- API Governance Contracts
- Command Envelope Contracts
- Event Envelope Contracts
- Audit Evidence Contracts

Chaque famille devra recevoir des schémas versionnés dans la phase ARCHITECTURE.

---

# PARTIE III — VALIDATION PAR SCÉNARIOS

## 9. Scénario A — Création d’une organisation et d’un administrateur

### Objectif

Créer une organisation, enregistrer une personne, lui créer un compte et établir son appartenance administrative.

### Parcours logique

1. `RegisterIdentity` crée l’identité de l’organisation.
2. `CreateOrganization` crée l’agrégat Organization.
3. `RegisterIdentity` crée l’identité de la personne.
4. `RegisterPerson` crée le profil Person.
5. `CreateUserAccount` crée un compte distinct.
6. `AddMembership` relie la Person à l’Organization.
7. `AssignRole` attribue un rôle dans le contexte de l’Organization.
8. Chaque étape produit un Audit Record.

### Validation

- Person n’est pas confondue avec User Account.
- Membership porte la relation organisationnelle.
- Role reste contextuel.
- Aucun agrégat étranger n’est modifié directement.

**Résultat :** Conforme.

---

## 10. Scénario B — Association d’un Agent GAMAD Drive

### Objectif

Associer un Agent installé sur un serveur local à une organisation.

### Parcours logique

1. L’organisation existe déjà.
2. `RegisterDevice` enregistre le serveur.
3. `RegisterApplication` ou une identité applicative reconnue existe pour GAMAD Drive Agent.
4. `RegisterAgent` crée l’Agent lié au Device.
5. Une Authority organisationnelle autorise l’association.
6. `AuthorizeApplicationScope` accorde uniquement les scopes requis.
7. Un Event informe GAMAD Drive de l’association réussie.
8. L’audit enregistre Device, Agent, Actor, Authority et Application.

### Validation

- Device, Agent et Application restent distincts.
- L’Agent ne devient pas administrateur global.
- Les scopes sont explicites et révocables.

**Résultat :** Conforme.

---

## 11. Scénario C — Publication d’une ressource locale

### Objectif

Publier une ressource locale depuis l’Agent sans transférer son contenu dans le Core.

### Parcours logique

1. L’Agent identifie un dossier local dans GAMAD Drive.
2. GAMAD Drive demeure System of Record du contenu et des détails techniques.
3. `RegisterResource` inscrit la ressource dans Resource Registry.
4. La Resource reçoit un Resource Type versionné.
5. L’Organization devient Responsible Party principal.
6. GAMAD Drive est déclaré System of Record.
7. Access Control définit les droits initiaux.
8. `ResourceRegistered` est publié.
9. L’Audit enregistre l’acteur et l’autorité.

### Validation

- Le Core ne stocke pas le contenu du dossier.
- La ressource possède identité, responsable et System of Record.
- Les droits sont distincts de la disponibilité technique.

**Résultat :** Conforme.

---

## 12. Scénario D — Attribution d’une ressource à Franck

### Objectif

Autoriser Franck à consulter une ressource dans son organisation.

### Parcours logique

1. L’Access Control vérifie l’Identity de Franck.
2. Le Membership actif confirme le contexte organisationnel.
3. L’Entitlement confirme que la Capability nécessaire est disponible pour l’Organization.
4. Une Permission ou un Role autorise `resource.read` sur la cible.
5. Les Policies ajoutent les restrictions éventuelles.
6. `EvaluateAccess` produit une Access Decision.
7. La décision est auditée si l’action est significative.

### Validation

- Entitlement ≠ Permission.
- Membership ≠ Role.
- Authentification ≠ autorisation.
- La décision reste explicable.

**Résultat :** Conforme.

---

## 13. Scénario E — Partage externe d’une ressource

### Objectif

Créer un partage externe limité sans créer obligatoirement un compte GAMAD complet au destinataire.

### Parcours logique

1. GAMAD Share vérifie la Capability `share.external`.
2. Access Control vérifie l’autorité de l’Actor.
3. Le produit Share crée son objet métier de partage dans son propre System of Record.
4. Le Core conserve uniquement les références transversales nécessaires.
5. Une Policy impose durée, restrictions et niveau de vérification.
6. `ShareCreated` est publié par le produit Share selon contrat.
7. L’Audit conserve la preuve de création et de révocation éventuelle.

### Validation

- Le Core ne devient pas propriétaire du Share Link métier.
- Le destinataire externe ne devient pas automatiquement membre de l’organisation.
- La responsabilité de la ressource reste inchangée.

**Résultat :** Conforme.

---

## 14. Scénario F — Synchronisation et conflit documentaire

### Objectif

Synchroniser une ressource et traiter un conflit sans transférer la logique Sync dans le Core.

### Parcours logique

1. GAMAD Sync vérifie l’accès de l’Actor et du Device.
2. Le Core fournit les références de Resource, Actor, Device et Policies.
3. GAMAD Sync gère les versions, blocs, conflits et résolutions dans son propre domaine.
4. Les événements significatifs sont publiés : `SyncConflictDetected`, `SyncConflictResolved`.
5. L’Audit enregistre les décisions humaines ou automatiques critiques.

### Validation

- Les données de synchronisation restent hors Core.
- Le Core gouverne identité, accès et preuve.
- Copilote ou automatisation agit sous Authority explicite.

**Résultat :** Conforme.

---

## 15. Scénario G — Révocation d’un membre

### Objectif

Révoquer l’appartenance d’une personne sans supprimer son identité.

### Parcours logique

1. `RevokeMembership` modifie uniquement l’agrégat Membership.
2. `MembershipRevoked` est publié.
3. Access Control invalide ou recalcule les droits dépendants.
4. Les sessions sensibles peuvent être révoquées selon Policy.
5. Les produits consommateurs adaptent leurs accès en cohérence éventuelle.
6. Person et Identity demeurent intactes.

### Validation

- Révocation d’appartenance ≠ suppression de personne.
- Les effets inter-contextes passent par événements.
- Aucun produit ne modifie directement Membership.

**Résultat :** Conforme.

---

## 16. Scénario H — Révocation d’une application compromise

### Objectif

Couper l’accès d’une application sans interrompre les identités humaines.

### Parcours logique

1. `RevokeApplication` change l’état de l’Application Aggregate.
2. Les credentials techniques sont révoqués.
3. Les sessions applicatives sont invalidées.
4. Access Control refuse les nouvelles actions.
5. `ApplicationRevoked` est publié.
6. L’Audit scelle l’action et son Authority.

### Validation

- Application, Actor technique et Person restent distingués.
- La révocation est ciblée et traçable.

**Résultat :** Conforme.

---

# PARTIE IV — TESTS DE COHÉRENCE

## 17. Test des concepts orphelins

Aucun concept transversal majeur n’est sans propriétaire logique.

**Statut :** Réussi.

## 18. Test des invariants sans agrégat

Les invariants identifiés possèdent un agrégat responsable ou une règle de gouvernance transversale.

**Statut :** Réussi sous réserve de raffinement dans ARCH-001.

## 19. Test des dépendances circulaires

Aucune dépendance circulaire obligatoire n’est admise entre bounded contexts. Les retours d’information passent par événements, réponses ou projections.

**Statut :** Réussi.

## 20. Test de séparation Core / métier

Les contenus de Drive, Docs, Mail, Hub, Share, Sync et Copilote restent hors Core.

**Statut :** Réussi.

## 21. Test d’indépendance technologique

Aucun concept du modèle n’exige PHP, Laravel, PostgreSQL, Redis, Kafka ou une autre technologie.

**Statut :** Réussi.

## 22. Test d’auditabilité

Les scénarios critiques identifient Actor, Authority, Application, Context, cible et résultat.

**Statut :** Réussi.

## 23. Test de remplacement d’un produit

Un produit peut être remplacé tant que son System of Record, ses Contracts et ses Events restent compatibles.

**Statut :** Réussi conceptuellement.

---

# PARTIE V — CRITÈRES DE PASSAGE À ARCHITECTURE

## 24. Critères obligatoires

Le passage vers ARCHITECTURE-001 est autorisé lorsque :

- GENESIS-007A, 007B, 007C et 007D sont versionnés ;
- les ADR 0006 à 0009 sont acceptées ;
- les concepts canoniques sont référencés ;
- les bounded contexts sont identifiés ;
- les agrégats critiques sont reconnus ;
- les scénarios de référence ne révèlent aucune contradiction majeure ;
- les décisions restantes sont explicitement reportées à ARCHITECTURE.

## 25. Décisions reportées

Les sujets suivants ne sont pas tranchés dans GENESIS-007D :

- structure physique du monolithe modulaire ;
- choix de persistance ;
- schéma PostgreSQL ;
- structure Laravel ou autre runtime ;
- mécanisme d’authentification concret ;
- stratégie d’event bus ;
- outbox physique ;
- format exact des identifiants ;
- partitionnement multi-tenant ;
- stratégie de cache ;
- déploiement ;
- observabilité technique.

Ils devront être décidés par ARCHITECTURE et ADR dédiées.

---

# PARTIE VI — VERDICT FINAL

## 26. Verdict

Le métamodèle du GAMAD Core est suffisamment cohérent, complet et technologiquement neutre pour permettre la conception de son architecture de référence.

Il démontre que :

- les identités peuvent être reconnues sans absorber les données métier ;
- les organisations et appartenances peuvent être gouvernées sans être confondues avec les tenants ;
- les ressources peuvent garder une identité stable indépendamment de leur contenu ;
- les modules et capacités peuvent être activés sans confondre Entitlement et Permission ;
- les acteurs humains et techniques peuvent être audités sous une Authority explicite ;
- les produits peuvent coopérer par contrats sans partager leurs bases internes.

## 27. Déclaration de clôture

GENESIS-007A à GENESIS-007D forment ensemble le **Modèle Conceptuel du GAMAD Core**.

Ce modèle devient la référence normative pour toute architecture logique et physique future.

> La technologie implémente le modèle. Elle ne le définit jamais.

La phase de métamodélisation est déclarée achevée sous réserve des évolutions contrôlées par ADR.

Le prochain chantier officiel est :

> **ARCHITECTURE-001 — Architecture de Référence du GAMAD Core.**
