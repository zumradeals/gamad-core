# CAP-CORE-008 — Decisions Registry

## 1. Mission du chantier

Construire un registre persistant, gouverné et vérifiable des décisions opérationnelles utiles à GAMAD Core et à ses satellites.

Une décision formelle répond au minimum aux questions suivantes :

```text
Quelle question devait être tranchée ?
Quel périmètre et quelles ressources sont concernés ?
Quelle autorité était compétente ?
Quel mandat était valide à la date de décision ?
Quelles options ont été examinées ?
Quel résultat a été adopté ?
Pourquoi ?
Sous quelles conditions ?
À partir de quand et jusqu’à quand ?
Quels effets les capacités concernées doivent-elles exécuter ?
Ces effets ont-ils réellement été exécutés ?
Quelle preuve protège la décision adoptée ?
```

Le chantier doit faire passer `CAP-CORE-008` de `NO GO` à `GO` sans transformer le registre en moteur d’autorisation, en moteur de workflow universel, en système juridique, en registre des risques ou en gestionnaire d’incidents.

---

## 2. Problème actuel

Le dépôt sait déjà :

- évaluer une permission avec `CAP-CORE-004` ;
- vérifier les mandats avec `CAP-CORE-003` ;
- tracer les actions et décisions d’autorisation dans `CAP-CORE-013` ;
- gérer les cycles de vie des produits, sources, politiques et contrats ;
- produire des événements avec la future `CAP-CORE-014` ;
- protéger des preuves avec la future `CAP-CORE-015`.

Mais il ne possède aucun dossier commun permettant de dire :

- qu’une suspension a été formellement décidée ;
- qui a pris cette décision ;
- selon quel mandat ;
- sur quelles sources et preuves ;
- avec quelle durée ;
- quelles conditions ont été imposées ;
- quelles capacités devaient exécuter la décision ;
- si les effets ont été réellement appliqués ;
- si une décision ultérieure l’a remplacée ou annulée.

Aujourd’hui, les changements sont généralement exécutés directement après une autorisation puis tracés dans le journal. Cette trace prouve qu’une action a été tentée ou exécutée ; elle ne constitue pas un dossier de décision structuré.

---

## 3. Définition d’une décision formelle

Une décision formelle est un acte structuré, pris par une autorité compétente, portant sur une question précise et produisant un ou plusieurs effets attendus.

Elle possède :

- une référence stable ;
- un domaine ;
- un type canonique ;
- un objet et un sujet clairement identifiés ;
- une organisation et un realm ;
- une autorité responsable ;
- un mandat vérifié à une date donnée ;
- un mode de décision ;
- une ou plusieurs options ;
- des sources et pièces référencées ;
- un résultat ;
- un motif ;
- des conditions ;
- une date de prise ;
- une date d’effet ;
- une date d’expiration éventuelle ;
- des effets attendus ;
- une preuve d’intégrité ;
- un historique en ajout seul.

---

## 4. Ce qui n’est pas une décision de CAP-CORE-008

### 4.1 Décision d’autorisation

La réponse suivante reste la responsabilité de `CAP-CORE-004` :

```json
{
  "decision": "PERMIS",
  "sujet": "IDN-PER-...",
  "action": "activer une politique",
  "motif": "règle active correspondante"
}
```

Elle indique seulement qu’un acteur peut accomplir une action au moment de l’évaluation.

Elle ne dit pas qu’une politique doit être activée.

### 4.2 Trace d’audit

Une ligne du journal `CAP-CORE-013` indique qu’une opération a été tentée ou exécutée.

Elle ne remplace pas le dossier de décision.

### 4.3 Transition métier

Le passage d’un produit de `ACTIF` à `SUSPENDU` reste stocké par `CAP-CORE-011`.

`CAP-CORE-008` stocke la décision ayant demandé cette transition lorsque cette opération est classée comme exigeant une décision formelle.

### 4.4 Politique

Une politique de `CAP-CORE-007` définit les règles applicables.

Une décision ne crée pas une règle permanente et ne remplace pas une politique.

### 4.5 Mandat

`CAP-CORE-003` possède les fonctions, titulaires, mandats et délégations.

`CAP-CORE-008` enregistre la référence du mandat vérifié et un instantané minimal de sa validité au moment de la décision.

### 4.6 Risque ou exception

`CAP-CORE-017` possédera les risques et exceptions.

`CAP-CORE-008` enregistrera la décision d’accepter, refuser, limiter ou prolonger une exception, sans posséder le dossier de risque lui-même.

### 4.7 Incident

`CAP-CORE-018` possédera les incidents.

`CAP-CORE-008` pourra enregistrer une décision majeure liée à un incident : déclaration de crise, arrêt de service, reprise, clôture exceptionnelle.

### 4.8 Preuve juridique

Le registre produit une preuve technique structurée.

Il ne qualifie jamais automatiquement une décision de contrat, acte administratif, résolution sociale, jugement ou preuve juridique.

---

## 5. Responsabilité souveraine de CAP-CORE-008

La capacité possède :

- la fiche de décision ;
- le dossier d’instruction ;
- les options examinées ;
- les participants et positions ;
- le résultat formel ;
- les motifs et conditions ;
- les effets attendus ;
- les accusés d’exécution ;
- les liens de remplacement, annulation ou rectification ;
- les preuves et paquets de décision ;
- le diagnostic de cohérence du registre.

Elle ne possède pas :

- l’état métier final des autres capacités ;
- les identités ;
- les organisations ;
- les realms ;
- les mandats ;
- les politiques ;
- les sources elles-mêmes ;
- les contrats techniques ;
- les secrets ou clés privées ;
- les risques ;
- les incidents ;
- les résultats du Matching.

---

## 6. Architecture cible

Créer :

```text
core/registre-decisions/
├── README.md
├── resources/
│   └── bootstrap-decisions-v1.json
├── src/
│   ├── Magasin.php
│   ├── Schema.php
│   ├── RegistreDecisions.php
│   ├── ValidateurDecision.php
│   ├── EvaluateurCompetence.php
│   ├── CalculateurQuorum.php
│   ├── AssembleurPaquetDecision.php
│   ├── DiagnosticDecisions.php
│   └── PolitiqueDecisions.php
└── tests/
    └── decisions_p3.php
```

Ajouter dans Laravel :

```text
apps/console-laravel/app/Application/Decisions/
apps/console-laravel/app/Http/Controllers/Api/V1/DecisionController.php
apps/console-laravel/app/Http/Controllers/DecisionConsoleController.php
apps/console-laravel/resources/views/decisions/
apps/console-laravel/tests/Integration/decisions_v1_p1.php
apps/console-laravel/tests/Integration/decisions_console_p1.php
apps/console-laravel/tests/Integration/decisions_effets_p1.php
```

---

## 7. Magasin distinct

Variables :

```text
DECISION_REGISTRY_URL=postgresql://...
DECISION_REGISTRY_PATH=/chemin/registre-decisions.sqlite
GAMAD_DECISIONS_DRIVER=pgsql
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite seulement en local et en CI ;
- aucun fallback silencieux ;
- base distincte des identités, accès, organisations, realms, produits, sources, politiques, contrats, événements, preuves et journal d’audit ;
- migration intégrée à `core:fondation:migrer` ;
- readiness intégrée ;
- sauvegarde et restauration intégrées ;
- import SQLite vers PostgreSQL intégré.

---

## 8. Modes de décision

Liste initiale, portée par `CAP-CORE-010` :

```text
AUTORITE_UNIQUE
COLLEGIALE
PRISE_D_ACTE
```

### AUTORITE_UNIQUE

Une seule autorité compétente adopte ou rejette.

### COLLEGIALE

Plusieurs participants habilités expriment une position et un quorum doit être atteint.

### PRISE_D_ACTE

L’autorité constate un fait ou un résultat déjà établi sans prétendre l’avoir produit.

Le registre n’implémente pas de vote politique général, d’élection ou de gouvernance d’entreprise complète.

---

## 9. Résultats formels

Liste initiale :

```text
APPROUVEE
REFUSEE
AJOURNEE
PRISE_ACTE
SANS_SUITE
```

Le résultat est distinct du cycle du dossier.

Une décision `REFUSEE` reste une décision formelle immuable et consultable.

Une décision `AJOURNEE` peut être suivie d’une nouvelle décision, mais son résultat ne doit jamais être réécrit.

---

## 10. Types de décisions initiaux

Les types doivent être inscrits dans `CAP-CORE-010` et ne doivent jamais être de simples textes libres.

Bootstrap minimal :

```text
APPROBATION
ACTIVATION
SUSPENSION
RETRAIT
REMPLACEMENT
ANNULATION
DEROGATION
ACCEPTATION_RISQUE
REJEU_EVENEMENTS
RESTAURATION
MISE_EN_PRODUCTION
ARRET_URGENCE
REPRISE_SERVICE
CLOTURE
PRISE_ACTE
```

Un type ne donne aucune permission et n’ajoute aucun comportement automatique.

---

## 11. Décisions exigeant une décision formelle

Toutes les opérations ne doivent pas être encombrées par un dossier de décision.

Créer une matrice explicite des opérations sensibles exigeant une décision formelle.

Exemples initiaux :

- activation d’une rupture de contrat `CAP-CORE-009` ;
- activation d’une politique majeure ;
- suspension ou retrait d’un produit fédéré ;
- suspension d’un realm ;
- rejeu massif d’événements ;
- compromission et destruction d’une clé critique ;
- restauration de production ;
- acceptation d’une exception de sécurité ;
- clôture d’un incident majeur ;
- mise en production d’une capacité critique.

La matrice doit référencer une opération de contrat connue de `CAP-CORE-009` ou une action canonique de `CAP-CORE-010`.

Elle ne peut pas contenir de code exécutable ni d’expression libre.

---

## 12. Exécution par les capacités propriétaires

Une décision n’écrit jamais directement dans la base d’une autre capacité.

Exemple :

```text
CAP-CORE-008 adopte DEC-... : suspendre PRD-GAMAD-003
→ CAP-CORE-014 publie DECISION_MISE_EN_VIGUEUR
→ CAP-CORE-011 vérifie la décision, son type, son effet et sa preuve
→ CAP-CORE-011 exécute sa propre transition
→ CAP-CORE-011 retourne un accusé d’exécution
→ CAP-CORE-008 enregistre l’effet comme exécuté
```

La décision ne vaut donc pas exécution.

L’exécution ne vaut pas décision.

---

## 13. Autorité et compétence

Avant toute adoption :

1. identifier l’autorité ;
2. résoudre son identité avec `CAP-CORE-001` ;
3. vérifier son organisation avec `CAP-CORE-002` ;
4. vérifier son realm avec `CAP-CORE-012` ;
5. vérifier son mandat et sa délégation à la date de décision avec `CAP-CORE-003` ;
6. vérifier l’autorisation de l’action avec `CAP-CORE-004` ;
7. vérifier la politique active avec `CAP-CORE-007` ;
8. conserver les références de preuve avec `CAP-CORE-015`.

Une qualité affichée dans la console ne suffit jamais.

Un participant sans mandat valide peut être consulté, mais ne peut pas être compté comme décideur compétent.

---

## 14. Organisation et realm

Toute décision doit porter :

- une organisation responsable ;
- un realm principal ;
- une finalité ;
- éventuellement des realms affectés explicitement.

Un realm parent n’acquiert aucune compétence automatique sur ses enfants.

Une décision inter-realm exige :

- une relation ou autorisation de franchissement valide de `CAP-CORE-012` ;
- des mandats valides pour les autorités concernées ;
- une portée explicite ;
- aucune wildcard universelle.

---

## 15. Sources, pièces et preuves

Le dossier ne stocke pas les documents complets.

Il conserve des références vers :

- sources `CAP-CORE-006` ;
- preuves `CAP-CORE-015` ;
- contrats `CAP-CORE-009` ;
- politiques `CAP-CORE-007` ;
- événements `CAP-CORE-014` ;
- traces `CAP-CORE-013` ;
- ressources des capacités propriétaires.

Une décision adoptée exige au minimum :

- une source active ou une justification explicite de l’absence de source ;
- un motif ;
- une autorité compétente ;
- une preuve d’intégrité du paquet adopté.

---

## 16. Immutabilité

Avant adoption, le dossier peut évoluer dans les limites du cycle.

Après adoption ou rejet :

- la question ;
- les options ;
- les positions ;
- le résultat ;
- le motif ;
- les conditions ;
- les effets ;
- les références de source ;
- l’empreinte ;

sont immuables.

Une erreur se corrige par une nouvelle décision liée :

```text
RECTIFIE
REMPLACE
ANNULE
COMPLETE
```

Aucun `UPDATE` destructif ne réécrit une décision adoptée.

---

## 17. Compatibilité et dépendances

Le chantier doit conserver verts :

- `CAP-CORE-001` identités ;
- `CAP-CORE-003` mandats ;
- `CAP-CORE-004` autorisation ;
- `CAP-CORE-005` sessions ;
- `CAP-CORE-006` sources ;
- `CAP-CORE-007` politiques ;
- `CAP-CORE-009` contrats ;
- `CAP-CORE-010` vocabulaire ;
- `CAP-CORE-011` produits ;
- `CAP-CORE-012` realms ;
- `CAP-CORE-013` audit ;
- `CAP-CORE-014` événements ;
- `CAP-CORE-015` preuves ;
- `CAP-CORE-016` clés ;
- `CAP-CORE-019` continuité ;
- `CAP-CORE-022` fédération.

---

## 18. Interdictions de conception

Ne pas :

- remplacer `CAP-CORE-004` ;
- considérer chaque autorisation comme une décision formelle ;
- stocker des documents complets ;
- stocker des secrets ;
- exécuter du code depuis une décision ;
- écrire directement dans une base satellite ;
- accorder une permission depuis le registre ;
- accepter une décision sans autorité compétente ;
- faire d’un libellé de fonction une preuve de mandat ;
- utiliser un realm parent comme wildcard ;
- réécrire une décision adoptée ;
- supprimer une décision rejetée ;
- antidater une adoption ;
- confondre preuve technique et qualification juridique ;
- permettre une décision automatique sans autorité responsable explicitement désignée.

---

## 19. Branche de travail

```text
claude/cap-core-008-decisions-registry-go
```

Une seule capacité, une seule PR, aucun démarrage de `CAP-CORE-017` dans la même session.
