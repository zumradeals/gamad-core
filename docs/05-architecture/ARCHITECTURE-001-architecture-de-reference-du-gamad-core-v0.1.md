# ARCHITECTURE-001 — Architecture de Référence du GAMAD Core

## Version 0.1 — Référence initiale

**Projet :** GAMAD Core  
**Statut :** Candidate architecturale  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances normatives :**
- GENESIS-003 — GAMAD Core Charter
- GENESIS-004 — Lexique Canonique du GAMAD Core
- GENESIS-005 — Les Lois du GAMAD Core
- GENESIS-006 — Atlas GAMAD
- GENESIS-007A à GENESIS-007D — Modèle Conceptuel du GAMAD Core
- GOVERNANCE-001 — Constitution de l’Écosystème Logiciel GAMAD
- MASTERPLAN-001 — Plan Directeur de l’Écosystème GAMAD
- ADR-0006 — The Core governs relationships before data
- ADR-0007 — Aggregates protect invariants, not object collections
- ADR-0008 — Bounded contexts own language, rules and data responsibility
- ADR-0009 — Logical model precedes physical model
- ADR-0010 — Modular monolith first

---

## 1. Objet

Le présent document définit l’architecture de référence du GAMAD Core.

Il constitue le pont entre :

- le modèle conceptuel validé ;
- les décisions architecturales ;
- les contrats ;
- l’implémentation future.

Il fixe :

- la forme générale du système ;
- les couches autorisées ;
- les responsabilités techniques ;
- les règles de dépendance ;
- les mécanismes de communication ;
- les exigences de sécurité, d’audit et de résilience ;
- la stratégie de déploiement initiale ;
- les critères permettant d’extraire ultérieurement un composant.

Il ne constitue pas encore :

- un schéma SQL final ;
- un catalogue complet d’endpoints ;
- un plan de classes ;
- un guide d’interface utilisateur ;
- une architecture des produits Drive, Hub, Docs ou Mail.

---

# PARTIE I — PRINCIPES DE RÉFÉRENCE

## 2. Forme architecturale

GAMAD Core commence comme un **monolithe modulaire headless**, organisé par bounded contexts, exposant des contrats versionnés et utilisant des événements internes fiables.

```text
Clients et systèmes GAMAD
        │
        ▼
Interfaces d’entrée
REST API · Console · Workers · Event Consumers
        │
        ▼
Application Layer
Command Handlers · Query Handlers · Process Managers
        │
        ▼
Domain Layer
Aggregates · Policies · Domain Services · Domain Events
        │
        ▼
Ports
Repositories · Event Publisher · Clock · Identity Provider
        │
        ▼
Infrastructure Adapters
PostgreSQL · Queue · Cache · Mail/Notification · Observability
```

Le système est déployé initialement comme une unité cohérente, mais ses frontières internes doivent permettre une extraction future sans réécriture du domaine.

---

## 3. Principes obligatoires

1. Le domaine ne dépend d’aucun framework.
2. Les bounded contexts sont des frontières logiques réelles.
3. Une transaction métier reste dans un seul agrégat.
4. Les échanges entre contextes passent par des contrats explicites.
5. Les modules externes ne lisent jamais la base interne du Core.
6. Toute action significative produit une preuve d’audit.
7. Toute publication d’événement liée à une transaction utilise un mécanisme fiable.
8. Les API sont versionnées.
9. Les identités techniques sont distinctes des identités humaines.
10. Le Core reste headless ; les interfaces riches appartiennent aux applications clientes.
11. Les choix technologiques sont remplaçables derrière des ports.
12. Une dépendance circulaire entre contextes est interdite.

---

# PARTIE II — TOPOLOGIE DU SYSTÈME

## 4. Composants déployables initiaux

### 4.1 Core API

Responsabilités :

- exposer les opérations synchrones ;
- authentifier les clients ;
- valider les contrats ;
- exécuter commandes et requêtes ;
- retourner des réponses normalisées ;
- produire l’audit et les événements nécessaires.

### 4.2 Core Worker

Responsabilités :

- consommer les événements ;
- exécuter les traitements asynchrones ;
- orchestrer les process managers ;
- publier les événements sortants ;
- gérer retries, backoff et dead-letter handling.

### 4.3 Core Scheduler

Responsabilités :

- expiration de sessions ;
- révocation planifiée ;
- transitions de cycle de vie ;
- contrôles d’intégrité ;
- tâches de maintenance ;
- scellement périodique de preuves d’audit.

### 4.4 Core Admin Surface

Interface administrative minimale et séparée, destinée uniquement à :

- administrer les identités et applications ;
- inspecter les contrats ;
- gérer les politiques autorisées ;
- consulter les preuves ;
- opérer les procédures exceptionnelles documentées.

Elle ne devient pas le portail GAMAD, le Hub ou une interface métier universelle.

---

## 5. Topologie initiale

```text
                    ┌──────────────────────────┐
                    │ Applications GAMAD       │
                    │ Drive · Hub · Docs · Mail│
                    │ Agents · Mobile · Admin  │
                    └─────────────┬────────────┘
                                  │ HTTPS / Events
                                  ▼
                    ┌──────────────────────────┐
                    │ API Gateway logique      │
                    │ AuthN · Rate Limit       │
                    │ Correlation · Versioning │
                    └─────────────┬────────────┘
                                  │
                                  ▼
                    ┌──────────────────────────┐
                    │ GAMAD Core API           │
                    │ Monolithe modulaire      │
                    └─────────────┬────────────┘
                                  │
              ┌───────────────────┼───────────────────┐
              ▼                   ▼                   ▼
       Core Worker          Core Scheduler      Admin Surface
              │                   │                   │
              └───────────────────┼───────────────────┘
                                  ▼
                PostgreSQL · Queue · Cache · Audit Store
```

L’API Gateway peut être un composant d’infrastructure externe ou une capacité intégrée au déploiement initial. Il ne possède aucune logique métier.

---

# PARTIE III — STRUCTURE INTERNE

## 6. Organisation par bounded context

Chaque bounded context suit la structure de référence :

```text
ContextName/
├── Domain/
│   ├── Aggregates/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Policies/
│   ├── Services/
│   ├── Events/
│   └── Exceptions/
├── Application/
│   ├── Commands/
│   ├── Queries/
│   ├── Handlers/
│   ├── DTOs/
│   └── ProcessManagers/
├── Contracts/
│   ├── Inbound/
│   ├── Outbound/
│   └── Events/
├── Infrastructure/
│   ├── Persistence/
│   ├── Messaging/
│   └── Adapters/
└── Interfaces/
    ├── Http/
    ├── Console/
    └── Consumers/
```

Cette arborescence est indicative. La règle normative est la séparation des responsabilités, non le nom exact des dossiers.

---

## 7. Couches

### 7.1 Domain Layer

Contient :

- agrégats ;
- entités de domaine ;
- value objects ;
- invariants ;
- policies de domaine ;
- services de domaine ;
- événements de domaine.

Ne contient jamais :

- framework HTTP ;
- ORM ;
- SQL ;
- accès réseau ;
- sérialisation externe ;
- appels à un fournisseur tiers.

### 7.2 Application Layer

Contient :

- cas d’usage ;
- command handlers ;
- query handlers ;
- orchestration ;
- transactions ;
- process managers ;
- contrôle des ports ;
- transformations entre contrats et domaine.

Elle ne contient pas les règles métier fondamentales des agrégats.

### 7.3 Contracts Layer

Contient :

- schémas d’entrée et de sortie ;
- erreurs publiques ;
- événements d’intégration ;
- versions ;
- règles de compatibilité.

Les contrats ne réutilisent pas directement les objets internes du domaine.

### 7.4 Infrastructure Layer

Contient :

- ORM et mapping ;
- repositories concrets ;
- messaging ;
- cache ;
- cryptographie appliquée ;
- services externes ;
- observabilité ;
- stockage.

### 7.5 Interfaces Layer

Contient :

- contrôleurs HTTP ;
- consommateurs d’événements ;
- commandes console ;
- adaptateurs d’administration.

Elle traduit les protocoles, mais ne décide pas du métier.

---

## 8. Règle de dépendance

```text
Interfaces ──> Application ──> Domain
Infrastructure ──> Application / Domain Ports
Contracts <──> Application
Domain ──> rien d’extérieur
```

Le Domain Layer est au centre et ne dépend que du langage standard et de ses propres abstractions.

---

# PARTIE IV — CARTOGRAPHIE DES CONTEXTES

## 9. Contextes principaux

| Code | Bounded Context | Responsabilité principale |
|---|---|---|
| BC-001 | Identity Registry | Continuité et état identitaire |
| BC-002 | Persons and Accounts | Personnes et comptes utilisateurs |
| BC-003 | Organizations and Memberships | Organisations, unités et appartenances |
| BC-004 | Applications, Services and Devices | Identités techniques et confiance des systèmes |
| BC-005 | Authentication and Sessions | Authentification et sessions |
| BC-006 | Access Control | Autorisation et décisions d’accès |
| BC-007 | Resource Registry | Gouvernance transversale des ressources |
| BC-008 | Modules, Capabilities and Entitlements | Capacités disponibles et attribuées |
| BC-009 | Policy and Lifecycle Governance | Politiques et transitions communes |
| BC-010 | Contracts and API Governance | Contrats, versions et compatibilité |
| BC-011 | Commands, Events and Process Coordination | Coordination asynchrone fiable |
| BC-012 | Audit and Evidence | Preuves durables et vérifiables |

---

## 10. Dépendances logiques

### Dépendances autorisées majeures

```text
Persons and Accounts ──> Identity Registry
Organizations and Memberships ──> Identity Registry
Applications, Services and Devices ──> Identity Registry
Authentication and Sessions ──> Persons and Accounts
Authentication and Sessions ──> Applications, Services and Devices
Access Control ──> Organizations and Memberships
Access Control ──> Applications, Services and Devices
Access Control ──> Policy and Lifecycle Governance
Resource Registry ──> Identity Registry
Resource Registry ──> Organizations and Memberships
Modules and Entitlements ──> Organizations and Memberships
All contexts ──> Contracts and API Governance
All significant actions ──> Audit and Evidence
```

### Dépendances interdites

- Audit and Evidence ne modifie aucun contexte métier.
- Identity Registry ne dépend pas des profils métier complets.
- Access Control ne devient pas propriétaire des Memberships.
- Resource Registry ne stocke pas le contenu métier des produits.
- Contracts and API Governance ne contient pas la logique des contextes.
- Aucun contexte ne dépend d’une table interne d’un autre contexte.

---

# PARTIE V — MODÈLES DE COMMUNICATION

## 11. Communication synchrone

La communication synchrone est utilisée lorsque :

- une réponse immédiate est nécessaire ;
- une décision d’accès doit être rendue ;
- une commande courte peut être finalisée ;
- le contrat exige une confirmation immédiate.

Mécanismes autorisés :

- appel de service applicatif interne par port public ;
- API REST versionnée pour les clients externes ;
- requête de lecture contractuelle.

Un contexte n’appelle jamais directement un repository d’un autre contexte.

---

## 12. Communication asynchrone

Elle est utilisée pour :

- propagation inter-contextes ;
- notifications ;
- projections ;
- opérations longues ;
- coordinations multi-agrégats ;
- intégrations avec les produits.

### Exigences

- événement versionné ;
- identifiant unique ;
- horodatage ;
- producteur identifié ;
- correlation ID ;
- causation ID ;
- idempotence côté consommateur ;
- retry contrôlé ;
- dead-letter policy ;
- observabilité.

---

## 13. Domain Events et Integration Events

### Domain Event

Fait interne au bounded context, exprimé dans son langage local.

### Integration Event

Contrat public stable destiné aux autres contextes ou systèmes.

Un Domain Event ne devient Integration Event qu’après traduction explicite.

Cette séparation protège le modèle interne contre les dépendances externes.

---

## 14. Publication transactionnelle fiable

Lorsqu’une modification métier et un événement doivent rester cohérents :

```text
Transaction locale
├── modification de l’agrégat
└── écriture d’un message dans l’Outbox

Worker
├── lit l’Outbox
├── publie l’événement
└── marque la livraison
```

L’Outbox transactionnelle est la stratégie de référence initiale.

Les consommateurs utilisent une Inbox ou un mécanisme équivalent pour garantir l’idempotence.

---

# PARTIE VI — DONNÉES ET PERSISTANCE

## 15. Moteur de persistance de référence

Le moteur de référence initial est **PostgreSQL**.

Ce choix est une projection technique, non une définition du Core.

Il doit supporter :

- transactions fortes locales ;
- contraintes explicites ;
- isolation inter-contexte ;
- indexation ;
- données structurées ;
- JSON lorsque justifié ;
- audit append-only ;
- sauvegarde et restauration vérifiables.

---

## 16. Propriété des données

Chaque bounded context possède :

- ses modèles persistés ;
- ses migrations ;
- ses repositories ;
- ses contraintes ;
- ses projections.

Dans le monolithe initial, une même instance PostgreSQL peut être utilisée, mais les frontières doivent rester explicites par :

- schémas séparés ou conventions strictes ;
- permissions de base limitées ;
- migrations par contexte ;
- interdiction des jointures métier directes entre contextes.

Une vue de lecture transversale doit être construite par projection ou composition, jamais par appropriation silencieuse des données.

---

## 17. Lecture et écriture

Le Core adopte une séparation pragmatique :

- les écritures passent par les agrégats ;
- les lectures peuvent utiliser des projections dédiées ;
- CQRS complet n’est pas obligatoire ;
- la séparation commande/requête reste obligatoire au niveau des responsabilités.

---

## 18. Identifiants

Les identifiants techniques doivent être :

- opaques ;
- non réutilisables ;
- indépendants des emails et numéros de téléphone ;
- générables sans collision ;
- stables dans le temps.

Les identifiants lisibles GAMAD sont des références métier distinctes des clés internes si nécessaire.

Le format exact fera l’objet d’une spécification dédiée.

---

# PARTIE VII — API ET CONTRATS

## 19. Style d’API initial

Le style de référence est **REST + JSON + OpenAPI**.

Règles minimales :

- version explicite ;
- ressources et actions nommées selon le Canon ;
- erreurs normalisées ;
- idempotency key pour les commandes sensibles ;
- pagination contractuelle ;
- correlation ID ;
- authentification standardisée ;
- contrôle de scope ;
- dépréciation documentée.

GraphQL, gRPC ou d’autres protocoles ne sont admis que par besoin démontré et ADR.

---

## 20. Classes d’API

### Core Administration API

Réservée à l’administration souveraine.

### Organization API

Destinée aux opérations autorisées d’une organisation.

### Application Integration API

Destinée aux applications et services enregistrés.

### Public Verification API

Surface minimale, strictement contrôlée, pour certaines vérifications non sensibles si elles sont autorisées.

Aucune API n’expose directement les structures internes de persistance.

---

## 21. Contrat d’erreur

Toute erreur publique doit inclure au minimum :

- code stable ;
- message lisible ;
- correlation ID ;
- catégorie ;
- détails sûrs ;
- caractère retryable ou non.

Les traces internes, secrets et données sensibles ne sont jamais retournés.

---

# PARTIE VIII — IDENTITÉ, AUTHENTIFICATION ET AUTORISATION

## 22. Principes

- Identity ≠ User Account.
- Authentication ≠ Authorization.
- Actor ≠ Authority.
- Session ≠ Permission.
- Application identity ≠ Device identity.

---

## 23. Standards de référence

Le Core s’appuie sur des standards éprouvés :

- OAuth 2.x selon le profil retenu ;
- OpenID Connect lorsque l’identité utilisateur doit être propagée ;
- jetons signés avec rotation des clés ;
- certificats ou credentials dédiés pour les services et Agents ;
- MFA pour les rôles sensibles.

Aucun protocole cryptographique propriétaire ne doit être inventé.

---

## 24. Décision d’accès

L’Access Control évalue :

```text
Actor
+ Authority
+ Action
+ Target
+ Context
+ Permissions
+ Roles
+ Policies
+ Entitlements applicables
= Access Decision
```

La présence d’un Entitlement rend une capacité disponible, mais n’accorde pas automatiquement une Permission à l’Actor.

---

# PARTIE IX — AUDIT, OBSERVABILITÉ ET PREUVES

## 25. Audit

Les Audit Records critiques doivent être :

- append-only ;
- horodatés ;
- corrélés ;
- attribués à un Actor et une Authority ;
- associés à l’Application et au Context ;
- protégés contre modification ordinaire ;
- conservés selon une politique explicite.

---

## 26. Observabilité

Le système produit séparément :

- logs techniques ;
- métriques ;
- traces distribuées ;
- événements métier ;
- preuves d’audit.

Chaque requête ou traitement doit pouvoir être suivi par correlation ID.

L’observabilité ne remplace jamais l’audit.

---

# PARTIE X — SÉCURITÉ

## 27. Modèle de confiance

Le Core applique le principe :

> Aucun humain, appareil, Agent, service ou application n’est implicitement digne de confiance.

Toute interaction exige :

- identité ;
- authentification ;
- autorisation ;
- contexte ;
- journalisation adaptée.

---

## 28. Classification minimale des données

- Public
- Interne
- Confidentiel
- Sensible
- Souverain

Les règles de stockage, d’accès, de chiffrement et d’audit dépendent de la classification.

---

## 29. Chiffrement

- chiffrement en transit obligatoire ;
- chiffrement au repos pour les données sensibles ;
- clés séparées des données ;
- rotation des clés ;
- accès au déchiffrement limité et audité ;
- sauvegardes chiffrées et restaurables.

---

## 30. Secrets

Aucun secret ne doit être :

- stocké dans Git ;
- inscrit dans un contrat ;
- journalisé ;
- partagé entre plusieurs applications sans justification.

Les secrets disposent d’un cycle de vie : création, distribution, rotation, révocation et destruction.

---

# PARTIE XI — RÉSILIENCE ET EXPLOITATION

## 31. Disponibilité

Le Core doit pouvoir être déployé au minimum avec :

- plusieurs instances API stateless ;
- workers séparés ;
- base PostgreSQL sauvegardée ;
- queue persistante ;
- stockage de secrets sécurisé ;
- supervision.

Le premier déploiement peut être plus simple, mais ne doit pas empêcher cette évolution.

---

## 32. Pannes partielles

Les dépendances externes sont considérées faillibles.

Le Core doit utiliser selon le besoin :

- timeout ;
- retry limité ;
- circuit breaker ;
- idempotence ;
- file d’attente ;
- compensation ;
- mode dégradé documenté.

---

## 33. Sauvegarde et restauration

Une sauvegarde non testée n’est pas une garantie.

Le Core exige :

- sauvegardes automatiques ;
- rétention définie ;
- chiffrement ;
- tests réguliers de restauration ;
- procédure de reconstruction ;
- objectifs RPO/RTO définis avant production critique.

---

## 34. Migrations

Toute migration de données doit être :

- versionnée ;
- réversible lorsque possible ;
- testée sur copie ;
- compatible avec la fenêtre de déploiement ;
- documentée ;
- observable.

Les migrations destructives exigent une procédure d’approbation renforcée.

---

# PARTIE XII — PROFIL TECHNOLOGIQUE DE RÉFÉRENCE

## 35. Stack initiale autorisée

| Domaine | Choix de référence |
|---|---|
| Langage serveur | PHP 8.4+ |
| Framework d’adaptation | Laravel, utilisé comme outil et non comme modèle de domaine |
| Base de données | PostgreSQL |
| API | REST + JSON + OpenAPI |
| Authentification | OAuth 2.x / OpenID Connect selon profil |
| Messaging | File persistante avec abstraction de transport |
| Tests | Unitaires, intégration, contrats et architecture |
| Packaging | Conteneurs OCI/Docker |
| Déploiement initial | Compose ou orchestration simple |
| Observabilité | Logs structurés, métriques et traces standardisées |

Cette stack peut évoluer par ADR. Les contrats et le domaine ne doivent pas dépendre de ses détails.

---

## 36. Ce qui n’est pas requis initialement

- microservices ;
- Kubernetes ;
- Kafka ;
- event sourcing généralisé ;
- CQRS complet ;
- GraphQL ;
- cache distribué obligatoire ;
- architecture serverless.

Ces outils ne peuvent être introduits que pour répondre à une contrainte démontrée.

---

# PARTIE XIII — TESTS ET CONTRÔLES

## 37. Pyramide de tests

### Tests de domaine

Vérifient les invariants des agrégats sans infrastructure.

### Tests d’application

Vérifient les cas d’usage et ports.

### Tests d’intégration

Vérifient PostgreSQL, queue, cryptographie et adaptateurs.

### Tests de contrat

Vérifient API, événements et compatibilité.

### Tests d’architecture

Vérifient les dépendances autorisées entre couches et contextes.

### Tests de sécurité

Vérifient authentification, autorisation, isolation et abus.

### Tests de restauration

Vérifient la reconstruction réelle du système.

---

## 38. Contrôles CI minimaux

- format et analyse statique ;
- tests unitaires ;
- tests d’architecture ;
- validation OpenAPI et schémas ;
- détection de secrets ;
- contrôle des migrations ;
- dépendances vulnérables ;
- vérification de compatibilité des contrats.

---

# PARTIE XIV — ÉVOLUTION ARCHITECTURALE

## 39. Critères d’extraction d’un service

Un bounded context ne devient un service séparé que si au moins une contrainte démontrée l’exige :

- charge indépendante ;
- disponibilité différente ;
- sécurité ou isolation renforcée ;
- rythme de déploiement autonome ;
- équipe propriétaire distincte ;
- technologie spécialisée indispensable ;
- réduction mesurable d’un couplage devenu problématique.

L’extraction doit préserver les contrats existants.

---

## 40. Anti-critères

Ne justifient pas une extraction :

- effet de mode ;
- préférence personnelle ;
- volonté d’utiliser une nouvelle technologie ;
- simple taille d’un dossier ;
- ressemblance superficielle avec une architecture d’une grande entreprise.

---

## 41. Évolution sans rupture

Toute évolution doit prévoir :

- versionnement ;
- compatibilité ;
- migration ;
- observabilité ;
- possibilité de retour ;
- calendrier de dépréciation.

---

# PARTIE XV — STRUCTURE CIBLE DU DÉPÔT

## 42. Structure documentaire et exécutable

```text
gamad-core/
├── docs/
│   ├── 00-identity/
│   ├── 01-core/
│   ├── 02-glossary/
│   ├── 03-governance/
│   ├── 04-model/
│   ├── 05-architecture/
│   ├── 06-decisions/
│   └── 07-specifications/
├── contracts/
│   ├── openapi/
│   ├── events/
│   └── schemas/
├── src/
│   ├── IdentityRegistry/
│   ├── PersonsAccounts/
│   ├── OrganizationsMemberships/
│   ├── ApplicationsDevices/
│   ├── AuthenticationSessions/
│   ├── AccessControl/
│   ├── ResourceRegistry/
│   ├── ModulesEntitlements/
│   ├── PolicyLifecycle/
│   ├── ContractsGovernance/
│   ├── ProcessCoordination/
│   └── AuditEvidence/
├── tests/
├── infrastructure/
├── tools/
└── README.md
```

La création effective de cette structure se fera après validation des spécifications de démarrage.

---

# PARTIE XVI — CRITÈRES D’ACCEPTATION

## 43. L’architecture de référence est acceptable si

1. le domaine peut être testé sans framework ;
2. chaque bounded context possède une frontière explicite ;
3. aucun module externe ne nécessite un accès base ;
4. les transactions restent locales aux agrégats ;
5. les événements sont publiés de manière fiable ;
6. les contrats sont versionnés ;
7. les décisions d’accès sont traçables ;
8. l’audit est distinct des logs ;
9. la stack peut être remplacée derrière les ports ;
10. GAMAD Drive peut devenir un client sans transférer sa logique métier dans le Core ;
11. un contexte peut être extrait sans modifier son langage métier ;
12. la restauration du système peut être testée.

---

## 44. Risques à surveiller

- Core transformé en ERP universel ;
- duplication des identités dans les produits ;
- framework pénétrant le domaine ;
- jointures directes entre contextes ;
- événements non versionnés ;
- audit incomplet ;
- autorisation uniquement au niveau UI ;
- secrets dans les dépôts ;
- microservices prématurés ;
- contrat implicite connu seulement des développeurs.

---

## 45. Déclaration finale

L’architecture de référence du GAMAD Core adopte un monolithe modulaire headless, gouverné par des bounded contexts, des agrégats protégeant leurs invariants, des contrats versionnés et des événements fiables.

Elle permet de construire un noyau cohérent aujourd’hui sans empêcher sa distribution demain.

Le modèle gouverne l’implémentation.

Le framework sert le modèle.

Les contrats protègent l’écosystème.
