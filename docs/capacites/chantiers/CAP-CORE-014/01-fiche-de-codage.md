# GAMAD CORE — FICHE DE CODAGE CAP-CORE-014
# EVENT JOURNAL — PASSAGE DE NO GO À GO PRODUCTION

**Référence :** `CAP-CORE-014`  
**Nom :** Event Journal / Journal commun des événements  
**Statut initial :** `NO GO`  
**Statut cible :** `GO`  
**Dépôt :** `zumradeals/gamad-core`  
**Branche cible :** `main`  
**Nature :** chantier complet de code, migration, raccordement, tests, exploitation et documentation

---

## 1. Mission

Construire le service opérationnel de publication et de consommation des événements communs de GAMAD Core.

À la fin du chantier, une capacité ou un produit autorisé doit pouvoir annoncer un fait métier ou technique stable sans donner accès à sa base privée, et un consommateur autorisé doit pouvoir :

- découvrir les types d’événements qu’il peut recevoir ;
- s’abonner à un ensemble explicite d’événements ;
- recevoir uniquement les événements compatibles avec son contrat, sa finalité et son realm ;
- lire les événements de manière durable ;
- reprendre après une interruption ;
- accuser réception ;
- refuser temporairement un événement avec un motif ;
- éviter un traitement en double ;
- demander un rejeu borné et autorisé ;
- consulter son retard de consommation ;
- traiter les événements en échec sans perdre la trace d’origine.

Le producteur doit pouvoir :

- préparer un événement dans une outbox liée à sa transaction métier ;
- publier une seule fois une intention logique, même si le transport la rejoue ;
- conserver l’identifiant de corrélation et la cause ;
- prouver quel contrat et quelle version décrivent l’événement ;
- savoir si l’événement a été accepté dans le journal commun ;
- rapprocher les événements métier des traces privées de `CAP-CORE-013`.

Le résultat attendu n’est pas seulement cette note Markdown.

Le résultat attendu est une capacité réellement codée, éprouvée et raccordée à :

- Laravel ;
- PostgreSQL ;
- les magasins producteurs ;
- le registre des contrats ;
- le vocabulaire canonique ;
- les realms ;
- l’autorisation ;
- l’audit ;
- la fédération des satellites ;
- la readiness ;
- la sauvegarde ;
- la restauration ;
- la CI.

`CAP-CORE-014` doit devenir `GO` avant les capacités qui dépendent d’une circulation fiable des faits, notamment :

```text
CAP-CORE-016 — Secrets & Keys
CAP-CORE-015 — Integrity Proofs
CAP-CORE-008 — Decisions Registry
CAP-CORE-017 — Risks & Exceptions
CAP-CORE-018 — Incidents
CAP-CORE-020 — Directory & Atlas
CAP-CORE-021 — Matching Engine
```

---

## 2. Prérequis obligatoires

Le codage ne doit commencer qu’après que les capacités suivantes soient `GO` et fusionnées dans `main` :

```text
CAP-CORE-001 — Identity Registry
CAP-CORE-002 — Organizations Registry
CAP-CORE-004 — Authorization
CAP-CORE-005 — Authentication & Access
CAP-CORE-006 — Sources Registry
CAP-CORE-007 — Rules / Policies Registry
CAP-CORE-009 — Contracts Registry
CAP-CORE-010 — Canonical Vocabulary
CAP-CORE-011 — Products Registry
CAP-CORE-012 — Realms Registry
CAP-CORE-013 — Common Audit
CAP-CORE-019 — Backup & Restore
CAP-CORE-022 — Satellite Federation
```

Raisons principales :

- `CAP-CORE-001` identifie les acteurs et propriétaires ;
- `CAP-CORE-002` identifie les organisations exploitantes ;
- `CAP-CORE-004` décide qui peut publier, s’abonner, lire, rejouer ou administrer ;
- `CAP-CORE-005` authentifie producteurs et consommateurs ;
- `CAP-CORE-006` fournit la provenance ;
- `CAP-CORE-007` fournit les politiques ;
- `CAP-CORE-009` décrit les événements, leurs schémas et leurs parties ;
- `CAP-CORE-010` fournit les codes canoniques ;
- `CAP-CORE-011` identifie les produits ;
- `CAP-CORE-012` borne le routage par realm ;
- `CAP-CORE-013` conserve la preuve privée d’exploitation ;
- `CAP-CORE-019` protège le nouveau magasin ;
- `CAP-CORE-022` fournit le premier chemin d’accès satellite.

Avant de coder :

1. récupérer le dernier `origin/main` ;
2. vérifier chaque prérequis dans `docs/capacites/CATALOGUE.md` ;
3. vérifier les contrats `EVENEMENT` actifs dans `CAP-CORE-009` ;
4. vérifier les codes d’événements et d’états livrés par `CAP-CORE-010` ;
5. vérifier les realms et règles de franchissement livrés par `CAP-CORE-012` ;
6. inventorier tous les appels actuels à `Journal::enregistrer()` ;
7. distinguer les événements d’audit privés des événements publiables ;
8. ne commencer aucune migration si un prérequis est encore `NO GO`.

Si `CAP-CORE-012` n’est pas fusionnée, arrêter après l’audit préparatoire.

---

## 3. Règle de statut

Le dépôt utilise uniquement :

- `GO` ;
- `NO GO`.

`CAP-CORE-014` reste `NO GO` pendant tout le chantier.

Elle ne passe à `GO` qu’après :

- publication durable depuis une outbox réelle ;
- consommation authentifiée ;
- reprise après interruption ;
- déduplication ;
- accusé de réception ;
- rejet temporaire ;
- lettre morte ;
- rejeu borné ;
- filtrage par contrat, consommateur et realm ;
- absence de fuite de secrets ou de données personnelles non prévues ;
- sauvegarde et restauration du nouveau magasin ;
- CI complète verte.

Les états d’un événement, d’un abonnement ou d’une livraison ne sont pas des statuts de capacité.

---

## 4. Différence fondamentale entre CAP-CORE-013 et CAP-CORE-014

### 4.1 CAP-CORE-013 — Common Audit

`CAP-CORE-013` répond à la question :

> Que s’est-il réellement passé dans l’exploitation du Core, dans quel ordre, sous quelle corrélation et avec quelle empreinte ?

Son journal actuel :

- est privé à l’exploitation ;
- est en ajout seul ;
- chaîne ses lignes par SHA-256 ;
- expurge secrets, cookies, sessions et jetons ;
- sert de preuve interne ;
- ne constitue pas une signature d’origine ;
- ne livre rien aux satellites ;
- ne connaît ni abonnement, ni curseur, ni accusé de réception.

Il doit rester `GO` et conserver cette responsabilité.

### 4.2 CAP-CORE-014 — Event Journal

`CAP-CORE-014` répond à la question :

> Quels faits communs, contractuels et autorisés doivent être rendus disponibles à quels consommateurs, de manière durable et reprenable ?

Il possède :

- l’enveloppe commune d’événement ;
- la publication ;
- le journal de diffusion ;
- le routage ;
- les abonnements ;
- les livraisons ;
- les curseurs ;
- les accusés de réception ;
- les reprises ;
- les lettres mortes ;
- les rejeux.

### 4.3 Règle de séparation

Un événement d’audit n’est pas automatiquement publiable.

Un événement publiable doit produire également une trace d’audit, mais sa charge utile est gouvernée par un contrat d’événement actif.

```text
CAP-CORE-013 = preuve privée de l’exécution
CAP-CORE-014 = diffusion contractuelle d’un fait minimal
```

Ne pas transformer le journal d’audit actuel en bus public.

---

## 5. Définition opérationnelle d’un événement commun

Un événement commun est un fait passé, immuable et minimal, publié par un producteur reconnu selon un contrat actif.

Exemples :

```text
PRODUIT_ACTIVE
PRODUIT_SUSPENDU
SOURCE_RETIREE
POLITIQUE_VERSION_ACTIVEE
CONTRAT_VERSION_DEPRECIEE
ORGANISATION_SUSPENDUE
REALM_FERME
LIEN_FEDERE_REVOQUE
```

Un événement commun doit dire :

- ce qui s’est produit ;
- qui ou quel composant l’a produit ;
- quand le fait s’est produit ;
- quand il a été enregistré ;
- quel contrat le décrit ;
- dans quel realm il s’applique ;
- quelle source le fonde ;
- quelle finalité justifie sa diffusion ;
- quel identifiant de corrélation le relie à l’opération ;
- quel événement ou quelle commande l’a causé ;
- quelle charge utile minimale est autorisée.

Un événement ne donne pas automatiquement le droit de consulter la ressource complète.

Il doit généralement porter une référence opaque et inviter le consommateur à utiliser une requête contractuelle autorisée pour obtenir davantage d’informations.

---

## 6. Ce que CAP-CORE-014 possède

`CAP-CORE-014` possède :

- le format commun de l’enveloppe ;
- la référence globale de l’événement ;
- la séquence du journal de diffusion ;
- le rattachement au contrat et à sa version ;
- le producteur ;
- la source ;
- le realm ;
- la corrélation et la causalité ;
- la charge utile minimale contractuelle ;
- l’empreinte de la charge utile ;
- les abonnements ;
- les filtres fermés ;
- les livraisons ;
- les tentatives ;
- les accusés de réception ;
- les rejets temporaires ;
- les lettres mortes ;
- les demandes de rejeu ;
- les diagnostics de retard ;
- les règles de conservation du journal de diffusion.

---

## 7. Ce que CAP-CORE-014 ne possède pas

`CAP-CORE-014` ne possède pas :

- les données métier complètes ;
- les fichiers des satellites ;
- les profils complets ;
- les mots de passe ;
- les passkeys ;
- les secrets ;
- les clés privées ;
- les jetons fédérés ;
- les sessions ;
- les contrats d’événements ;
- les vocabulaires ;
- les politiques ;
- les identités ;
- les produits ;
- les organisations ;
- les realms ;
- les décisions d’autorisation ;
- les preuves d’audit privées ;
- les résultats de Matching ;
- un moteur de workflow métier ;
- une garantie universelle « exactement une fois ».

Répartition :

- `CAP-CORE-009` décrit l’événement ;
- `CAP-CORE-010` nomme ses codes ;
- `CAP-CORE-012` borne son realm ;
- `CAP-CORE-013` prouve l’exécution ;
- `CAP-CORE-014` diffuse et suit la consommation ;
- `CAP-CORE-016` gérera plus tard les références de clés ;
- `CAP-CORE-015` ajoutera plus tard les preuves générales et signatures.

---

## 8. État actuel à confirmer

Inspecter le dépôt avant toute modification.

État attendu :

1. `CAP-CORE-013` est `GO`.
2. `CAP-CORE-014` est `NO GO`.
3. `core/journal-operationnel/` contient :
   - `Journal.php` ;
   - `Schema.php` ;
   - `Magasin.php` ;
   - une garde de fondation.
4. La table `evenement_operationnel` est en ajout seul.
5. Les événements sont chaînés par empreinte SHA-256.
6. Le journal retourne explicitement `signee: false`.
7. Le journal n’expose aucune API de consommation satellite.
8. Il n’existe aucune table d’abonnement.
9. Il n’existe aucun curseur de consommateur.
10. Il n’existe aucun accusé de réception.
11. Il n’existe aucune lettre morte.
12. Il n’existe aucun rejeu gouverné.
13. Les capacités `GO` produisent déjà de nombreux types d’audit.
14. Ces types ne sont pas tous adaptés à une publication externe.
15. `CAP-CORE-009` connaît le type de contrat `EVENEMENT`, mais ne transporte rien.
16. Aucun courtier Kafka, RabbitMQ ou service externe ne doit être supposé disponible.

Si l’inspection révèle une différence, adapter l’implémentation au dépôt réel sans modifier les frontières de cette fiche.

---

## 9. Architecture cible

Créer un module distinct :

```text
core/journal-evenements/
├── README.md
├── resources/
│   └── bootstrap-evenements-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaEvenements.php
│   ├── EnveloppeEvenement.php
│   ├── Outbox.php
│   ├── RegistreEvenements.php
│   ├── RouteurEvenements.php
│   ├── RegistreAbonnements.php
│   ├── LivreurEvenements.php
│   ├── RejoueurEvenements.php
│   ├── ValidateurEvenement.php
│   ├── PolitiqueEvenements.php
│   └── ExceptionEvenement.php
└── tests/
    └── evenements_p3.php
```

Créer également une bibliothèque légère d’outbox réutilisable dans les magasins producteurs, par exemple :

```text
core/evenements-sortants/
├── src/
│   ├── SchemaOutbox.php
│   ├── OutboxProducteur.php
│   └── RelaisOutbox.php
└── tests/
```

Les noms exacts peuvent suivre les conventions du dépôt, mais les responsabilités doivent rester séparées.

---

## 10. Choix de transport initial

Le passage à `GO` ne doit pas dépendre d’un courtier externe absent de l’exploitation actuelle.

La première version doit utiliser :

- PostgreSQL comme journal durable ;
- une outbox transactionnelle dans chaque magasin producteur raccordé ;
- un relais exécuté par worker/commande planifiée ;
- une API de lecture authentifiée avec curseur ;
- des accusés de réception explicites ;
- une livraison au moins une fois ;
- une déduplication obligatoire côté livraison et côté consommateur pilote.

Le mode `PULL` authentifié est obligatoire pour `GO`.

Le mode `PUSH_HTTPS` peut être ajouté seulement s’il utilise un mécanisme d’authentification déjà exploitable sans inventer de secret. Il ne doit pas bloquer `GO` tant que `CAP-CORE-016` n’est pas livré.

Kafka, RabbitMQ, NATS ou un autre broker pourront être ajoutés plus tard derrière les mêmes contrats, sans modifier l’enveloppe canonique.

---

## 11. Garanties contractuelles

La capacité garantit :

- persistance avant exposition au consommateur ;
- ordre stable du journal de diffusion ;
- livraison au moins une fois ;
- identifiant d’événement stable ;
- déduplication possible ;
- reprise à partir d’un curseur ;
- aucune perte silencieuse ;
- aucune permission implicite ;
- aucune lecture inter-realm implicite ;
- aucune mutation de l’événement publié ;
- visibilité des échecs et du retard.

Elle ne garantit pas :

- traitement exactement une fois ;
- ordre causal universel entre plusieurs bases productrices ;
- disponibilité d’un consommateur ;
- application métier réussie chez le consommateur ;
- livraison instantanée ;
- signature cryptographique d’origine avant `CAP-CORE-015` et `CAP-CORE-016`.

L’ordre garanti est l’ordre d’acceptation dans le journal commun.

La date du fait métier reste distincte de la date d’acceptation.

---

## 12. Portée initiale obligatoire

Raccorder au minimum les familles d’événements suivantes, après vérification des contrats réellement actifs :

### Produits

- activation ;
- suspension ;
- retrait ;
- changement de fédérabilité.

### Sources

- activation ;
- suspension ;
- retrait ;
- fermeture d’une finalité.

### Politiques

- activation d’une version ;
- suspension ;
- remplacement ;
- retrait.

### Contrats

- activation d’une version ;
- dépréciation ;
- suspension ;
- retrait.

### Organisations

- activation ;
- suspension ;
- dissolution ou retrait ;
- fermeture d’une affiliation importante, uniquement si le contrat le prévoit.

### Realms

- activation ;
- suspension ;
- fermeture ;
- retrait ;
- fermeture d’un rattachement.

### Fédération

- révocation d’un lien produit ;
- suspension d’un produit fédéré ;
- aucun jeton ni identifiant local sensible.

Ne pas publier automatiquement :

- chaque ouverture de session ;
- chaque refus d’autorisation ;
- les codes de secours ;
- les détails de passkeys ;
- les secrets ;
- les jetons ;
- les charges d’audit internes ;
- les données personnelles profondes.

---

## 13. Politique d’administration

Créer dans `CAP-CORE-007` :

```text
POL-EVENEMENTS-V1
```

Actions minimales :

- `evenement.publier` ;
- `evenement.lire` ;
- `evenement.abonnement.creer` ;
- `evenement.abonnement.modifier` ;
- `evenement.abonnement.activer` ;
- `evenement.abonnement.suspendre` ;
- `evenement.abonnement.retirer` ;
- `evenement.livraison.accuser` ;
- `evenement.livraison.refuser` ;
- `evenement.rejeu.demander` ;
- `evenement.lettre-morte.relancer` ;
- `evenement.diagnostic.lire`.

Bornes minimales :

- seul un producteur déclaré dans le contrat actif peut publier ;
- seul un consommateur déclaré peut s’abonner ;
- un consommateur ne lit que ses propres abonnements ;
- une autorité peut diagnostiquer sans recevoir automatiquement la charge utile ;
- un rejeu exige une décision explicite ;
- un rejeu inter-realm exige une autorisation de franchissement ;
- un abonnement ne peut pas élargir le contrat ;
- toute absence ou indétermination vaut refus.

---

## 14. Branche de chantier

Claude Code doit créer :

```text
claude/cap-core-014-event-journal-go
```

Une seule capacité doit être traitée dans cette branche.

La PR doit rester non fusionnée tant que le dirigeant n’a pas donné l’autorisation explicite de fusion.
