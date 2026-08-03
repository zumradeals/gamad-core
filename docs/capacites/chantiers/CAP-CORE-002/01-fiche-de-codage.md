# GAMAD CORE — FICHE DE CODAGE CAP-CORE-002
# ORGANIZATIONS REGISTRY — PASSAGE DE NO GO À GO PRODUCTION

**Référence :** `CAP-CORE-002`  
**Nom :** Organizations Registry / Registre des organisations  
**Statut initial :** `NO GO`  
**Statut cible :** `GO`  
**Dépôt :** `zumradeals/gamad-core`  
**Branche cible :** `main`  
**Nature :** chantier complet de code, migration, tests, exploitation et documentation

---

## 1. Mission

Construire le registre opérationnel des organisations de GAMAD Core.

À la fin du chantier, le Core doit pouvoir représenter de manière persistante, gouvernée et vérifiable :

- une organisation reconnue par son identité canonique GAMAD ;
- ses dénominations et caractéristiques organisationnelles minimales ;
- son cycle de vie ;
- ses unités ou établissements ;
- sa structure hiérarchique ;
- ses relations avec d’autres organisations ;
- ses membres et affiliations ;
- ses fonctions internes descriptives ;
- les rattachements d’une personne ou d’une organisation ;
- les sources et preuves de ces informations ;
- les périodes de validité ;
- les classifications de visibilité ;
- la distinction entre une affiliation déclarée et une représentation juridiquement ou institutionnellement opposable.

Le registre doit permettre de répondre notamment à ces questions :

```text
Cette organisation existe-t-elle dans GAMAD ?
Quelle identité canonique la représente ?
Quel est son état courant ?
Quelles unités composent sa structure ?
Quelle organisation parente lui est liée ?
Cette personne est-elle membre ou employée de cette organisation ?
Cette relation est-elle active à la date demandée ?
Cette personne peut-elle représenter l’organisation ?
Quelle source et quelle preuve fondent l’information ?
Quel produit est autorisé à consulter cette information ?
```

Le résultat attendu n’est pas seulement cette fiche Markdown.

Le résultat attendu est une capacité réellement codée, testée et raccordée à :

- Laravel ;
- PostgreSQL ;
- la console ;
- l’API ;
- `CAP-CORE-001` ;
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

`CAP-CORE-002` doit devenir `GO` avant le chantier de :

```text
CAP-CORE-012 — Realms Registry
```

---

## 2. Prérequis obligatoires

Le codage ne doit commencer qu’après que les capacités suivantes soient `GO` et fusionnées dans `main` :

```text
CAP-CORE-001 — Identity Registry
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

- `CAP-CORE-001` fournit les identités canoniques des organisations et des membres ;
- `CAP-CORE-003` fournit les mandats qui rendent une représentation opposable ;
- `CAP-CORE-006` fournit la provenance ;
- `CAP-CORE-007` fournit les politiques ;
- `CAP-CORE-009` fournit les contrats d’échange ;
- `CAP-CORE-010` fournit les codes canoniques ;
- `CAP-CORE-011` fournit les produits producteurs et consommateurs ;
- `CAP-CORE-013` fournit les preuves d’exploitation.

Avant de coder :

1. récupérer le dernier `origin/main` ;
2. vérifier que chaque prérequis est marqué `GO` dans le catalogue ;
3. vérifier que les magasins persistants et gardes correspondants existent ;
4. inspecter les contrats et vocabulaires réellement livrés ;
5. utiliser leurs références canoniques ;
6. ne pas dupliquer leurs données dans le nouveau registre.

Si `CAP-CORE-010` n’est pas encore fusionnée, arrêter après l’audit préparatoire.

---

## 3. Règle de statut

Le dépôt utilise uniquement :

- `GO` ;
- `NO GO`.

`CAP-CORE-002` reste `NO GO` pendant le chantier.

Elle ne passe à `GO` qu’après :

- migration des relations organisationnelles existantes ;
- raccordement de `CAP-CORE-001` ;
- résolution des mandats via `CAP-CORE-003` ;
- API et console fonctionnelles ;
- PostgreSQL, sauvegarde et restauration éprouvés ;
- CI complète verte.

Les états métier d’une organisation, d’une unité ou d’une affiliation restent distincts du statut de capacité.

---

## 4. Définition opérationnelle d’une organisation

Une organisation est une entité collective reconnue par une identité canonique GAMAD de type `organisation`.

Elle peut représenter notamment :

- une société ;
- une association ;
- une institution ;
- une administration ;
- une fondation ;
- une coopérative ;
- une ONG ;
- un groupe ;
- un établissement ;
- une unité interne ;
- une structure partenaire.

Le registre ne doit pas supposer qu’une organisation possède nécessairement une personnalité juridique.

Cette distinction doit être explicite dans les données.

---

## 5. Ce que CAP-CORE-002 possède

`CAP-CORE-002` possède :

- la fiche organisationnelle liée à une identité canonique ;
- ses révisions descriptives ;
- son cycle de vie organisationnel ;
- son type canonique ;
- son caractère juridique ou non juridique ;
- ses identifiants externes gouvernés ;
- ses unités et établissements ;
- sa structure hiérarchique ;
- ses relations organisation-à-organisation ;
- ses affiliations de personnes ou d’organisations ;
- ses fonctions internes descriptives ;
- ses périodes de validité ;
- ses classifications ;
- ses sources et preuves ;
- l’historique des changements.

---

## 6. Ce que CAP-CORE-002 ne possède pas

`CAP-CORE-002` ne possède pas :

- l’identité canonique elle-même ;
- les authentificateurs ;
- les sessions ;
- les mots de passe ;
- les passkeys ;
- les mandats opposables ;
- les décisions d’autorisation ;
- les politiques ;
- les contrats ;
- les secrets ;
- les clés ;
- les données RH détaillées ;
- la paie ;
- les salaires ;
- les évaluations du personnel ;
- les dossiers disciplinaires ;
- les dossiers médicaux ;
- les contrats de travail complets ;
- les pièces justificatives complètes ;
- les fichiers d’un satellite ;
- les ventes ;
- les stocks ;
- les campagnes ;
- les transactions ;
- les scores de réputation ;
- les résultats du Matching ;
- les décisions économiques d’un satellite.

Une relation `DIRIGEANT`, `REPRESENTANT` ou équivalente ne suffit jamais, à elle seule, à rendre un acte opposable.

La représentation opposable relève de `CAP-CORE-003`.

---

## 7. Répartition avec les autres capacités

- `CAP-CORE-001` possède l’identité canonique minimale.
- `CAP-CORE-002` possède la structure et les affiliations organisationnelles.
- `CAP-CORE-003` possède les fonctions institutionnelles et mandats opposables.
- `CAP-CORE-004` décide les opérations autorisées.
- `CAP-CORE-005` authentifie les acteurs.
- `CAP-CORE-006` possède les sources.
- `CAP-CORE-007` possède les politiques.
- `CAP-CORE-009` possède les contrats.
- `CAP-CORE-010` possède les codes canoniques.
- `CAP-CORE-011` possède les produits.
- `CAP-CORE-012` possédera les realms et périmètres.
- `CAP-CORE-013` conserve les preuves.
- `CAP-CORE-014` publiera plus tard les événements.
- `CAP-CORE-021` consommera plus tard certaines informations autorisées.

Le registre des organisations ne doit pas devenir un monolithe absorbant ces responsabilités.

---

## 8. État actuel à confirmer

Inspecter le dépôt avant toute modification.

État attendu :

1. `CAP-CORE-002` est `NO GO`.
2. Aucun module persistant `core/registre-organisations/` n’existe.
3. `CAP-CORE-001` reconnaît déjà le type d’identité `organisation`.
4. `CAP-CORE-001` contient actuellement une table `relation_organisation`.
5. Cette table porte notamment :
   - identité ;
   - organisation ;
   - type de relation ;
   - mandat éventuel ;
   - indicateur de mandat vérifié ;
   - assurance ;
   - source ;
   - preuve ;
   - producteur ;
   - date ;
   - classification.
6. `Ctr01::resoudreLiensOrganisations()` expose ces relations.
7. Les relations à mandat sont aujourd’hui déterminées par une liste dans le code de `CAP-CORE-001`.
8. `CAP-CORE-001` indique explicitement que les dossiers d’organisation appartiennent à `CAP-CORE-002`.
9. `CAP-CORE-003` sait résoudre des mandats, mais son magasin et ses contrats exacts doivent être vérifiés au moment du chantier.
10. Il n’existe pas de registre opérationnel des unités, structures, affiliations ou identifiants externes.
11. Il n’existe pas de cycle organisationnel distinct.
12. Il n’existe pas d’API d’administration des organisations.
13. Il n’existe pas d’écran console dédié.
14. Les anciens types de relation doivent être préservés ou migrés par alias canonique.
15. Le nombre d’organisations et de relations existantes doit être compté depuis les données réelles, sans hypothèse.

Ne pas se limiter à rechercher le mot `organisation`.

Inspecter également :

- identités ;
- relations ;
- mandats ;
- produits propriétaires ;
- sources ;
- contrats ;
- OpenAPI ;
- tests ;
- console ;
- sauvegarde et restauration.

---

## 9. Audit initial obligatoire

Produire avant codage un inventaire des éléments existants :

- identités de type organisation ;
- relations organisationnelles ;
- types de relation utilisés ;
- mandats référencés ;
- relations déclarées opposables ;
- sources ;
- producteurs ;
- classifications ;
- routes et contrôleurs ;
- tests ;
- consommateurs.

Commandes indicatives :

```bash
rg -n --hidden \
  --glob '!.git/**' \
  --glob '!vendor/**' \
  --glob '!node_modules/**' \
  "organisation|RELATIONS_ORGANISATION|relation_organisation|mandat_reference|DIRIGEANT|REPRESENTANT"

php artisan route:list
```

Le rapport doit distinguer :

- donnée d’identité ;
- donnée de structure ;
- affiliation ;
- mandat ;
- droit d’accès ;
- donnée métier de satellite.

---

## 10. Architecture cible

Créer :

```text
core/registre-organisations/
├── README.md
├── resources/
│   └── bootstrap-organisations-v1.json
├── src/
│   ├── Magasin.php
│   ├── SchemaOrganisations.php
│   ├── RegistreOrganisations.php
│   ├── PolitiqueOrganisations.php
│   ├── ValidateurStructure.php
│   ├── ProjectionIdentites.php
│   └── ExceptionOrganisation.php
└── tests/
    └── organisations_p3.php
```

Variables proposées :

```text
ORGANIZATION_REGISTRY_URL
ORGANIZATION_REGISTRY_PATH
GAMAD_ORGANIZATION_DRIVER
```

Règles :

- PostgreSQL obligatoire en production ;
- SQLite autorisé en local et CI ;
- aucun fallback silencieux en production ;
- magasin distinct de l’index ;
- magasin distinct du registre d’identités ;
- configuration Laravel mise en cache supportée ;
- chemins de tests explicitement isolés ;
- aucune migration implicite pendant une lecture HTTP ;
- aucun bootstrap implicite pendant une lecture HTTP.

Raccorder le magasin à :

- `apps/console-laravel/config/database.php` ;
- `.env.example` ;
- `core:fondation:migrer` ;
- readiness ;
- import SQLite ;
- sauvegarde ;
- restauration ;
- copie hors machine ;
- CI PostgreSQL.

---

## 11. Modèle de données minimal

Toutes les tables d’historique sont en ajout seul.

### 11.1 Table `organisation`

Champs minimaux :

- `reference` ;
- `identite_reference` ;
- `type_organisation_reference` ;