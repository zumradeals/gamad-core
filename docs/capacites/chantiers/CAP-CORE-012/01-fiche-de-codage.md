# GAMAD CORE — FICHE DE CODAGE CAP-CORE-012
# REALMS REGISTRY — PASSAGE DE NO GO À GO PRODUCTION

**Référence :** `CAP-CORE-012`  
**Nom :** Realms Registry / Registre des realms et périmètres  
**Statut initial :** `NO GO`  
**Statut cible :** `GO`  
**Dépôt :** `zumradeals/gamad-core`  
**Branche cible :** `main`  
**Nature :** chantier complet de code, migration, tests, exploitation et documentation

---

## 1. Mission

Construire le registre opérationnel des realms de GAMAD Core.

Un realm est un **périmètre nommé, borné et gouverné** dans lequel des organisations, produits, capacités, contrats ou opérations peuvent être rattachés.

Il permet au Core de répondre clairement à des questions comme :

```text
Dans quel périmètre cette opération s’exécute-t-elle ?
Quel pays, territoire, institution, programme ou domaine technique est concerné ?
Ce produit est-il autorisé à opérer dans ce realm ?
Cette organisation administre-t-elle ce realm ?
Ce realm est-il actif à la date demandée ?
Ce realm appartient-il à un realm parent ?
Deux realms se chevauchent-ils ou sont-ils séparés ?
Cette donnée ou ce contrat peut-il franchir la frontière du realm ?
Quelle source et quelle preuve fondent ce rattachement ?
```

À la fin du chantier, le Core doit pouvoir :

- inscrire un realm lié à une identité canonique de type `realm` ;
- définir sa nature et son code canonique ;
- enregistrer ses révisions descriptives ;
- gérer son cycle de vie ;
- créer une hiérarchie acyclique de realms ;
- rattacher des organisations à un realm ;
- rattacher des produits à un realm ;
- rattacher des capacités ou contrats lorsque cela est nécessaire ;
- enregistrer des périmètres territoriaux, institutionnels, fonctionnels ou techniques ;
- enregistrer des identifiants externes comme un code pays ou une référence institutionnelle ;
- vérifier si une opération est dans la portée d’un realm ;
- expliquer pourquoi un rattachement ou une utilisation est accepté ou refusé ;
- préserver l’historique daté ;
- refuser les traversées de frontière non déclarées ;
- auditer toutes les commandes sensibles ;
- sauvegarder et restaurer le registre.

Le résultat attendu n’est pas seulement cette note Markdown.

Le résultat attendu est une capacité réellement codée, éprouvée et raccordée à :

- Laravel ;
- PostgreSQL ;
- la console ;
- l’API ;
- `CAP-CORE-001` ;
- `CAP-CORE-002` ;
- `CAP-CORE-003` ;
- `CAP-CORE-004` ;
- `CAP-CORE-006` ;
- `CAP-CORE-007` ;
- `CAP-CORE-009` ;
- `CAP-CORE-010` ;
- `CAP-CORE-011` ;
- `CAP-CORE-013` ;
- la readiness ;
- la sauvegarde ;
- la restauration ;
- la CI.

`CAP-CORE-012` doit devenir `GO` avant le chantier de :

```text
CAP-CORE-014 — Event Journal
```

---

## 2. Prérequis obligatoires

Le codage ne doit commencer qu’après que les capacités suivantes soient `GO` et fusionnées dans `main` :

```text
CAP-CORE-001 — Identity Registry
CAP-CORE-002 — Organizations Registry
CAP-CORE-003 — Authorities & Mandates
CAP-CORE-004 — Authorization
CAP-CORE-006 — Sources Registry
CAP-CORE-007 — Rules / Policies Registry
CAP-CORE-009 — Contracts Registry
CAP-CORE-010 — Canonical Vocabulary
CAP-CORE-011 — Products Registry
CAP-CORE-013 — Common Audit
```

Raisons principales :

- `CAP-CORE-001` fournit l’identité canonique de type `realm` ;
- `CAP-CORE-002` fournit les organisations rattachées et responsables ;
- `CAP-CORE-003` vérifie les mandats des personnes qui administrent un realm ;
- `CAP-CORE-004` autorise ou refuse les commandes ;
- `CAP-CORE-006` fournit les sources de provenance ;
- `CAP-CORE-007` porte les politiques ;
- `CAP-CORE-009` versionne les contrats qui traversent ou utilisent les realms ;
- `CAP-CORE-010` fournit les codes canoniques des types, rôles, relations et motifs ;
- `CAP-CORE-011` fournit les produits rattachés ;
- `CAP-CORE-013` conserve les preuves d’exploitation.

Avant de coder :

1. récupérer le dernier `origin/main` ;
2. vérifier chaque prérequis dans `docs/capacites/CATALOGUE.md` ;
3. vérifier que les magasins persistants et leurs gardes existent ;
4. inspecter les contrats et vocabulaires réellement livrés ;
5. relever les identités de type `realm` déjà présentes ;
6. relever les organisations et produits susceptibles d’être rattachés ;
7. ne créer aucune référence métier fictive pour satisfaire les tests.

Si `CAP-CORE-002` ou `CAP-CORE-010` n’est pas encore `GO` et fusionnée, arrêter après l’audit préparatoire.

---

## 3. Règle de statut

Le dépôt utilise uniquement :

- `GO` ;
- `NO GO`.

`CAP-CORE-012` reste `NO GO` pendant tout le chantier.

Elle ne passe à `GO` qu’après :

- mise en place du magasin persistant ;
- bootstrap fidèle des éventuels realms existants ;
- hiérarchie acyclique ;
- rattachements gouvernés ;
- contrôle de portée ;
- API et console fonctionnelles ;
- contrats et vocabulaires raccordés ;
- PostgreSQL réel ;
- sauvegarde et restauration ;
- CI complète verte.

Les états métier d’un realm ou d’un rattachement restent distincts du statut de la capacité.

---

## 4. Définition opérationnelle d’un realm

Un realm est une frontière logique et gouvernée.

Il peut représenter notamment :

- un pays ;
- une région ;
- une ville ou zone territoriale ;
- une juridiction ;
- une institution ;
- un programme ;
- un marché ;
- un domaine d’activité ;
- un périmètre de produit ;
- un périmètre technique ;
- un environnement partagé ;
- une communauté fermée ;
- un périmètre temporaire de coopération.

Un realm n’est pas nécessairement géographique.

Exemples conceptuels :

```text
Realm Côte d’Ivoire
Realm Mali
Realm Wasplex Côte d’Ivoire
Realm GamaDrive Production
Realm Programme Agriculture 2027
Realm Institution KAMIS AGRO
```

Ces exemples ne doivent pas être inscrits automatiquement dans le registre.

Ils illustrent les usages possibles.

---

## 5. Différence entre realm, organisation, produit et territoire

### 5.1 Realm

Le realm répond à la question :

```text
Dans quelle frontière cette opération, cette relation ou ce contrat s’applique-t-il ?
```

### 5.2 Organisation

L’organisation répond à la question :

```text
Quelle structure collective existe, possède des unités, des membres ou des responsabilités ?
```

Une organisation peut administrer plusieurs realms.

Un realm peut être administré par plusieurs organisations selon des rôles distincts.

### 5.3 Produit

Le produit répond à la question :

```text
Quel logiciel, service ou satellite fournit une fonction ?
```

Un produit peut être autorisé dans plusieurs realms.

Le même produit peut avoir des règles ou environnements différents selon le realm, sans que le realm duplique les URLs et secrets déjà possédés par `CAP-CORE-011`.

### 5.4 Territoire

Un territoire est une dimension possible d’un realm.

Le registre des realms ne doit pas devenir une base cartographique générale.

Il peut enregistrer :

- un code pays ;
- une référence de région ;
- une référence de ville ;
- un périmètre administratif ;
- une géométrie externe référencée.

Il ne doit pas stocker une cartographie lourde ou des coordonnées individuelles sans besoin réel et contrat explicite.

---

## 6. Ce que CAP-CORE-012 possède

`CAP-CORE-012` possède :

- la fiche du realm liée à une identité canonique ;
- son code canonique ;
- son type ;
- ses révisions descriptives ;
- son cycle de vie ;
- sa classification ;
- sa période de validité ;
- sa hiérarchie ;
- ses relations avec d’autres realms ;
- ses dimensions de périmètre ;
- ses identifiants externes ;
- ses organisations rattachées ;
- ses produits rattachés ;
- ses capacités ou contrats explicitement rattachés ;
- les règles minimales de franchissement de frontière ;
- les sources et preuves ;
- l’historique des changements ;
- les réponses explicables de contrôle de portée.

---

## 7. Ce que CAP-CORE-012 ne possède pas

`CAP-CORE-012` ne possède pas :

- l’identité canonique elle-même ;
- le dossier d’une organisation ;
- les unités d’une organisation ;
- les utilisateurs d’un produit ;
- les comptes locaux d’un satellite ;
- les URLs et secrets d’environnement d’un produit ;
- les politiques ;
- les contrats ;
- les décisions d’autorisation ;
- les mandats ;
- les clés ;
- les secrets ;
- les événements transportés ;
- les données métier d’un pays ;
- les revenus d’un pays ;
- les données personnelles ;
- les fichiers ;
- les campagnes ;
- les ventes ;
- les transactions ;
- les résultats du Matching ;
- une base cartographique complète ;
- un moteur général de géolocalisation.

Le fait qu’un produit ou une organisation soit rattaché à un realm ne lui donne jamais automatiquement une autorisation.

`CAP-CORE-004` reste le moteur de décision.

---

## 8. Répartition avec les autres capacités

- `CAP-CORE-001` possède l’identité minimale du realm.
- `CAP-CORE-002` possède les organisations et leurs structures.
- `CAP-CORE-003` possède les mandats opposables.
- `CAP-CORE-004` autorise les opérations.
- `CAP-CORE-006` possède les sources.
- `CAP-CORE-007` possède les politiques.
- `CAP-CORE-009` possède les contrats.
- `CAP-CORE-010` possède les codes canoniques.
- `CAP-CORE-011` possède les produits et leurs environnements.
- `CAP-CORE-012` possède les frontières et rattachements de realm.
- `CAP-CORE-013` conserve l’audit.
- `CAP-CORE-014` publiera les changements de realm vers les consommateurs.
- `CAP-CORE-020` pourra publier une vue d’annuaire des realms.
- `CAP-CORE-021` pourra utiliser les realms pour borner un Matching.

---

## 9. État actuel à confirmer

Inspecter le dépôt avant toute modification.

État attendu :

1. `CAP-CORE-012` est `NO GO`.
2. Aucun module persistant `core/registre-realms/` n’existe.
3. `CAP-CORE-001` reconnaît déjà le type d’identité `realm`.
4. `CAP-CORE-001` utilise le préfixe `IDN-RLM` pour ce type.
5. Seuls les canaux réservés à l’autorité ou à la création technique peuvent inscrire ce type.
6. Une identité de type `realm` ne contient aujourd’hui aucune structure de périmètre.
7. Aucun cycle de realm distinct n’existe.
8. Aucune hiérarchie de realms n’existe.
9. Aucun rattachement gouverné d’organisation ou de produit à un realm n’existe.
10. Aucun contrôle transversal de portée de realm n’existe.
11. Aucun contrat `CTR-REALMS-*` n’existe.
12. Aucune politique `POL-REALMS-V1` n’existe.
13. Aucun écran `Realms` n’existe dans la console.
14. Aucune API `/api/v1/realms*` n’existe.
15. Le nombre réel d’identités de type `realm` peut être nul.

Ne pas inventer un realm global pour rendre le test vert.

Si aucun realm historique n’existe, le bootstrap doit réussir avec zéro import et les tests doivent créer leurs propres données temporaires.

---

## 10. Architecture cible

Créer :

```text
core/registre-realms/
├── README.md
├── resources/
│   └── bootstrap-realms-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaRealms.php
│   ├── RegistreRealms.php
│   ├── PolitiqueRealms.php
│   ├── ValidateurRealms.php
│   ├── EvaluateurPortee.php
│   ├── Ctr12.php
│   └── ExceptionRealm.php
└── tests/
    └── realms_p3.php
```

Les noms peuvent être adaptés aux conventions réelles de `main`, mais les responsabilités doivent rester séparées.

Variables proposées :

```text
REALM_REGISTRY_URL
REALM_REGISTRY_PATH
GAMAD_REALM_DRIVER
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite autorisé en développement et CI ;
- aucun fallback silencieux vers SQLite en production ;
- magasin distinct de l’index reconstructible ;
- magasin distinct des identités, organisations, produits et contrats ;
- configuration Laravel mise en cache supportée ;
- tests sur bases isolées ;
- aucune migration ou bootstrap pendant une requête HTTP métier.

Ajouter le magasin à :

- `apps/console-laravel/config/database.php` ;
- `.env.example` ;
- `core:fondation:migrer` ;
- `core:fondation:importer-sqlite` ;
- readiness ;
- diagnostic de fondation ;
- sauvegarde ;
- restauration ;
- copie hors machine ;
- exercice PostgreSQL réel.
