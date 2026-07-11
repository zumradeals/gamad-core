# GENESIS-006 — Atlas GAMAD

## Version 1.0

**Projet :** GAMAD Core  
**Statut :** Référence architecturale  
**Auteur de la vision :** Zakaria Le SOUFI — Orchestrateur de GAMAD  
**Architecture :** SIRR — Architecte de GAMAD  
**Dépendances :**
- GENESIS-001 — Livre Blanc de GAMAD
- GENESIS-002 — Charte Fondatrice de GAMAD
- GENESIS-003 — GAMAD Core Charter
- GENESIS-004 — Lexique Canonique du GAMAD Core
- GENESIS-005 — Les Lois du GAMAD Core

---

## 1. Mission de l’Atlas

L’Atlas GAMAD fournit une représentation lisible de l’écosystème, de ses couches, de ses frontières et de ses dépendances.

Il ne remplace ni le modèle conceptuel, ni les contrats, ni les diagrammes d’implémentation. Il répond à une question plus fondamentale :

> Où se situe chaque composant dans l’univers GAMAD, et de quoi dépend-il ?

L’Atlas doit permettre à un nouvel architecte, développeur, partenaire ou responsable de comprendre l’ensemble sans devoir lire immédiatement tout le dépôt.

---

## 2. Vue générale de l’écosystème

```text
                               GAMAD
                                 │
                ┌────────────────┼────────────────┐
                │                │                │
             PUBLIC          OPÉRATIONNEL       INTERNE
                │                │                │
          Portail gamad.net   Applications      GAMAD Core
          Documentation      Modules            Services internes
          Formation          Expériences        Gouvernance API
          Actualités         Utilisateurs       Audit transversal
```

GAMAD est l’écosystème.

GAMAD Core est son noyau souverain.

Les applications offrent des expériences.

Les modules fournissent des capacités.

Les services exécutent des responsabilités spécialisées.

Le portail présente l’écosystème au public mais ne le gouverne pas.

---

## 3. Les trois mondes de GAMAD

### 3.1 Monde public

Le monde public est visible, indexable et destiné à présenter GAMAD.

Il peut comprendre :

- gamad.net ;
- les pages de produits ;
- la documentation publique ;
- la bibliothèque ;
- la formation ;
- les publications ;
- les actualités ;
- les cas d’usage ;
- les offres commerciales.

Le monde public ne possède aucune autorité sur le Core.

### 3.2 Monde opérationnel

Le monde opérationnel est celui dans lequel les personnes et organisations utilisent les capacités GAMAD.

Il peut comprendre :

- GAMAD Drive ;
- GAMAD Docs ;
- GAMAD Mail ;
- GAMAD Share ;
- GAMAD Copilote ;
- GAMAD Mobile ;
- GAMAD Hub ;
- les futures applications métiers.

Chaque application consomme les contrats du Core et reste propriétaire de ses données métier.

### 3.3 Monde interne

Le monde interne assure la cohérence et le fonctionnement invisible de l’écosystème.

Il comprend notamment :

- GAMAD Core ;
- Identity Registry ;
- Access Control ;
- Resource Registry ;
- Module Registry ;
- Contract Registry ;
- API Governance ;
- Event Catalog ;
- Audit ;
- services techniques partagés.

Ce monde n’est pas une offre marketing.

---

## 4. Carte du GAMAD Core

```text
                           GAMAD CORE
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
 IDENTITÉS & ACTEURS      GOUVERNANCE           INTEROPÉRABILITÉ
        │                      │                      │
 Identity Registry        Organizations          Contracts
 Persons & Users          Memberships            API Governance
 Applications             Resources              Events
 Services                 Access Control         Audit
 Agents                    Modules                Lifecycle
 Devices                   Capabilities           Policies
```

Le Core est organisé en responsabilités logiques.

Ces responsabilités ne sont pas nécessairement déployées en services séparés.

---

## 5. Carte des applications principales

```text
GAMAD Applications
│
├── GAMAD Drive
│   ├── accès aux ressources
│   ├── publication
│   ├── synchronisation
│   ├── sauvegarde
│   └── partage documentaire
│
├── GAMAD Docs
│   ├── visualisation
│   ├── édition
│   ├── conversion
│   ├── signature
│   └── workflow documentaire
│
├── GAMAD Mail
│   ├── messagerie
│   ├── comptes IMAP/SMTP
│   ├── pièces jointes
│   └── intégration aux ressources
│
├── GAMAD Copilote
│   ├── assistance
│   ├── rédaction
│   ├── synthèse
│   ├── génération
│   └── exploitation de connaissances autorisées
│
├── GAMAD Hub
│   ├── espace interne affilié
│   ├── interactions
│   ├── coordination
│   └── vie communautaire interne
│
└── Applications futures
    ├── GAMAD Santé
    ├── GAMAD Transport
    ├── GAMAD Phone
    └── autres domaines
```

Aucune application ne définit l’identité de GAMAD.

Aucune application ne gouverne le Core.

---

## 6. Module, application et service

```text
Module
= ensemble cohérent de capacités

Application
= expérience exécutable consommant des contrats

Service
= composant spécialisé exécutant une responsabilité
```

Exemple :

```text
GAMAD Docs
│
├── Module Docs
│   ├── docs.view
│   ├── docs.edit
│   ├── docs.convert
│   └── docs.sign
│
├── Application Web Docs
└── Services
    ├── Conversion Service
    ├── OCR Service
    └── Signature Service
```

---

## 7. Flux d’autorité

```text
Vision
  ↓
Livre Blanc
  ↓
Charte Fondatrice
  ↓
Core Charter
  ↓
Lois et Lexique
  ↓
ADR et Contrats
  ↓
Implémentations
```

Le code n’est jamais l’autorité suprême.

Le code implémente des contrats dérivés des fondations documentaires.

---

## 8. Flux d’usage

```text
Personne
   ↓
Application
   ↓
Authentification
   ↓
Actor
   ↓
Permission Decision
   ↓
Resource
   ↓
Module responsable
   ↓
Résultat
   ↓
Audit Record
```

Chaque étape doit rester identifiable.

---

## 9. Flux de ressource

```text
Ressource réelle ou logique
        ↓
Enregistrement dans le Core
        ↓
Identity persistante
        ↓
Responsible Party
        ↓
Resource Type
        ↓
Policy et Permissions
        ↓
Module métier responsable
```

Le Core gouverne la ressource.

Le module gouverne son contenu métier.

---

## 10. Flux de capacité

```text
Module
   ↓ fournit
Capability
   ↓ accordée par
Entitlement
   ↓ utilisable dans
Organization
   ↓ exercée par
Actor
   ↓ sous contrôle de
Permission et Policy
```

Une capacité disponible n’est pas automatiquement autorisée à tous.

---

## 11. Flux contractuel

```text
Consumer Application
        ↓
API Contract
        ↓
GAMAD Core ou Module
        ↓
Command / Query
        ↓
Response
        ↓
Event éventuel
        ↓
Audit
```

Aucun accès direct à une base externe ne remplace un contrat.

---

## 12. Carte de dépendance

```text
Applications
   ↓ dépendent de
Contracts
   ↓ dépendent de
Canonical Concepts
   ↓ dépendent de
Core Charter et Laws
   ↓ dépendent de
Vision et Charte Fondatrice
```

La dépendance inverse est interdite.

Le Core Charter ne dépend pas de GAMAD Drive.

Le Lexique ne dépend pas d’un framework.

La vision ne dépend pas d’un produit.

---

## 13. Frontière du Hub

GAMAD Hub est une application interne particulière.

Il n’est pas :

- le Core ;
- l’Identity Registry ;
- la communauté publique ;
- le portail gamad.net ;
- l’autorité suprême de GAMAD.

Il consomme les identités, appartenances, autorisations et contrats du Core.

Il reste propriétaire de ses propres données d’interaction et de vie communautaire interne.

```text
GAMAD Hub
   ↓ consomme
GAMAD Core
   ↓ fournit
Identity, Membership, Permission, Audit
```

---

## 14. Frontière du portail gamad.net

Le portail public est la porte d’entrée visible de l’écosystème.

Il présente :

- la vision ;
- les produits ;
- les modules ;
- les contenus publics ;
- la documentation ;
- les formations ;
- les actualités.

Il ne contient pas la logique du Core.

Il peut consommer des API publiques contrôlées mais ne possède aucune donnée souveraine par défaut.

---

## 15. Carte des responsabilités

| Élément | Responsabilité principale | Ne gouverne pas |
|---|---|---|
| GAMAD Core | cohérence transversale | métier des applications |
| GAMAD Drive | ressources et fichiers distants | identité globale |
| GAMAD Docs | production documentaire | politiques globales |
| GAMAD Mail | communication électronique | Core Access Control |
| GAMAD Copilote | assistance intelligente | décisions humaines finales |
| GAMAD Hub | expérience communautaire interne | identité souveraine |
| gamad.net | présentation publique | gouvernance interne |
| Module | capacités | expérience utilisateur complète |
| Application | expérience et actions | contrats canoniques |
| Service | exécution spécialisée | gouvernance globale |

---

## 16. Carte des sources de vérité

| Domaine | Source de vérité |
|---|---|
| Identités | GAMAD Core — Identity Registry |
| Organisations | GAMAD Core — Organizations |
| Memberships | GAMAD Core — Memberships |
| Permissions | GAMAD Core — Access Control |
| Ressources enregistrées | GAMAD Core — Resource Registry |
| Modules et capacités | GAMAD Core — Module Registry |
| Contrats communs | GAMAD Core — Contract Registry |
| Contenu Drive | GAMAD Drive |
| Documents édités | GAMAD Docs |
| Courriels | GAMAD Mail |
| Conversations Copilote | GAMAD Copilote |
| Interactions Hub | GAMAD Hub |

Une source de vérité peut publier des événements mais ne délègue pas implicitement son autorité.

---

## 17. Carte de confiance

```text
Aucune application n’est fiable par défaut.
Aucun service n’est fiable par défaut.
Aucun appareil n’est fiable par défaut.
Aucun acteur n’est autorisé par défaut.
```

La confiance est :

- explicite ;
- contextualisée ;
- limitée ;
- révocable ;
- auditable.

---

## 18. Carte d’évolution

### Fondation

- Livre Blanc
- Charte Fondatrice
- Core Charter
- Lexique
- Lois
- Atlas

### Conception

- Modèle conceptuel
- Frontières détaillées
- Threat Model
- Contrats API
- Catalogue d’événements

### Implémentation

- Core V0.x
- Client contractuel minimal
- Intégration GAMAD Drive
- Portail gamad.net
- Modules futurs

---

## 19. Atlas des futurs domaines

```text
GAMAD
│
├── Noyau souverain
│   └── GAMAD Core
│
├── Travail numérique
│   ├── Drive
│   ├── Docs
│   ├── Mail
│   └── Copilote
│
├── Vie communautaire
│   ├── Hub
│   └── futurs espaces
│
├── Domaines métiers
│   ├── Santé
│   ├── Transport
│   ├── Éducation
│   ├── Finance
│   └── autres
│
└── Monde public
    └── gamad.net
```

Le nom GAMAD peut accueillir de nouveaux domaines sans imposer que tout soit intégré au même produit.

---

## 20. Règles de lecture de l’Atlas

1. Une flèche signifie une dépendance ou une consommation de contrat.
2. Une boîte ne signifie pas nécessairement un service déployé séparément.
3. Une application peut utiliser plusieurs modules.
4. Un module peut être utilisé par plusieurs applications.
5. Un service peut soutenir plusieurs modules.
6. Le Core ne dépend d’aucune application métier.
7. Le portail public n’est jamais confondu avec le Core.
8. Le Hub n’est jamais confondu avec le Core.
9. Les données métier restent dans leurs sources de vérité.
10. Toute nouvelle branche doit trouver sa place dans l’Atlas avant implémentation.

---

## 21. Test d’intégration d’un nouveau composant

Avant d’ajouter un composant à GAMAD, il faut répondre :

1. Est-ce un concept du Core, un module, une application, un service ou un domaine métier ?
2. Quelle est sa source de vérité ?
3. Quels contrats consomme-t-il ?
4. Quels contrats expose-t-il ?
5. Quelle identité possède-t-il ?
6. Quelles ressources gouverne-t-il ?
7. Quelles données ne doit-il jamais posséder ?
8. Qui peut le révoquer ?
9. Comment est-il audité ?
10. Où apparaît-il dans l’Atlas ?

Un composant sans place claire dans l’Atlas n’est pas prêt à être intégré.

---

## 22. Déclaration finale

L’Atlas GAMAD n’est pas un dessin décoratif.

Il constitue la carte de cohérence de l’écosystème.

Le Core est le noyau.

Les modules apportent les capacités.

Les applications offrent les expériences.

Les services exécutent les responsabilités spécialisées.

Le portail présente GAMAD au monde.

Le Hub demeure un espace d’usage interne distinct du Core.

Chaque nouvel élément doit trouver sa place sans déplacer les fondations.
