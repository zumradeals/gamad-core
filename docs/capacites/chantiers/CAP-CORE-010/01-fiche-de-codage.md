# GAMAD CORE — FICHE DE CODAGE CAP-CORE-010
# CANONICAL VOCABULARY — PASSAGE DE NO GO À GO PRODUCTION

**Référence :** `CAP-CORE-010`  
**Nom :** Canonical Vocabulary / Vocabulaire canonique  
**Statut initial :** `NO GO`  
**Statut cible :** `GO`  
**Dépôt :** `zumradeals/gamad-core`  
**Branche cible :** `main`  
**Nature :** chantier complet de code, migration, tests, exploitation et documentation

---

## 1. Mission

Construire le registre opérationnel des vocabulaires, concepts, codes et correspondances partagés de GAMAD Core.

À la fin du chantier, le Core doit disposer d’une source persistante, gouvernée, versionnée et exploitable permettant de savoir :

- quel terme canonique existe ;
- dans quel vocabulaire il appartient ;
- quelle est sa définition exacte ;
- quel code stable doit être échangé ;
- quels libellés humains peuvent l’afficher ;
- quels alias ou anciens codes lui correspondent explicitement ;
- quelles capacités, politiques, contrats et produits l’utilisent ;
- quelle version est active ;
- quels termes sont dépréciés, remplacés ou retirés ;
- quels consommateurs supportent réellement une version ou un terme ;
- si une modification est compatible ou constitue une rupture ;
- quelle preuve de conformité existe.

Le registre doit mettre fin à la dispersion non gouvernée des mêmes notions dans :

- constantes PHP ;
- contraintes SQL `CHECK` ;
- enums OpenAPI ;
- chaînes de caractères dans les contrôleurs ;
- règles d’autorisation ;
- contrats ;
- tests ;
- vues ;
- fichiers de bootstrap.

Le résultat attendu n’est pas seulement cette fiche Markdown.

Le résultat attendu est une capacité réellement codée, testée et raccordée à :

- Laravel ;
- PostgreSQL ;
- la console ;
- l’API ;
- `CAP-CORE-006` ;
- `CAP-CORE-007` ;
- `CAP-CORE-009` ;
- `CAP-CORE-011` ;
- la readiness ;
- la sauvegarde ;
- la restauration ;
- la CI.

`CAP-CORE-010` doit devenir `GO` avant de lancer les capacités qui ont besoin d’un langage commun entre plusieurs produits, notamment :

```text
CAP-CORE-002 — Organizations Registry
CAP-CORE-012 — Realms Registry
CAP-CORE-014 — Event Journal
CAP-CORE-021 — Matching Engine
```

---

## 2. Prérequis obligatoires

Le codage ne doit commencer qu’après que les capacités suivantes soient `GO` et fusionnées dans `main` :

```text
CAP-CORE-006 — Sources Registry
CAP-CORE-007 — Rules / Policies Registry
CAP-CORE-009 — Contracts Registry
CAP-CORE-011 — Products Registry
```

Raisons :

- `CAP-CORE-006` fournit la provenance d’un vocabulaire et de ses versions ;
- `CAP-CORE-007` fournit les politiques gouvernant création, activation, dépréciation et retrait ;
- `CAP-CORE-009` fournit les contrats qui consomment les termes canoniques ;
- `CAP-CORE-011` fournit les produits propriétaires et consommateurs.

Avant de coder :

1. récupérer le dernier `origin/main` ;
2. vérifier que ces quatre capacités sont `GO` dans le catalogue ;
3. vérifier que leurs magasins persistants et gardes sont présents ;
4. inspecter les références, actions, finalités et types réellement livrés ;
5. partir du code fusionné et non des anciennes fiches préparatoires ;
6. ne pas recréer un pseudo-registre de contrats, politiques, sources ou produits dans cette capacité.

Si `CAP-CORE-009` n’est pas fusionnée, arrêter après l’audit préparatoire.

---

## 3. Règle de statut

Le dépôt utilise seulement :

- `GO` ;
- `NO GO`.

`CAP-CORE-010` reste `NO GO` pendant tout le chantier.

Elle ne passe à `GO` que lorsque :

- le registre persistant existe ;
- les vocabulaires partagés actuels sont bootstrapés ;
- les usages sont reliés aux contrats et capacités ;
- les projections locales sont vérifiées ;
- les ruptures sont détectées ;
- la CI complète est verte ;
- la sauvegarde et la restauration sont éprouvées.

Les états métier d’un vocabulaire, d’une version ou d’un terme restent distincts du statut de la capacité.

---

## 4. Définition opérationnelle

### 4.1 Vocabulaire

Un vocabulaire est un ensemble versionné de concepts ou codes appartenant à un domaine précis.

Exemples attendus après audit :

```text
VOC-GAMAD-IDENTITE-TYPE
VOC-GAMAD-IDENTITE-ASSURANCE
VOC-GAMAD-RELATION-PRODUIT
VOC-GAMAD-RELATION-ORGANISATION
VOC-GAMAD-PRODUIT-TYPE
VOC-GAMAD-PRODUIT-ETAT
VOC-GAMAD-ENVIRONNEMENT
VOC-GAMAD-SOURCE-TYPE
VOC-GAMAD-SOURCE-ETAT
VOC-GAMAD-SOURCE-VERIFICATION-NIVEAU
VOC-GAMAD-SOURCE-VERIFICATION-RESULTAT
VOC-GAMAD-SOURCE-LIGNEE
VOC-GAMAD-POLITIQUE-EFFET
VOC-GAMAD-POLITIQUE-ETAT
VOC-GAMAD-CONTRAT-TYPE
VOC-GAMAD-CONTRAT-ETAT
VOC-GAMAD-CONTRAT-COMPATIBILITE
VOC-GAMAD-ACTION
VOC-GAMAD-FINALITE
```

Les références exactes doivent être arrêtées après inventaire du dépôt fusionné.

### 4.2 Concept ou terme canonique

Un terme canonique possède :

- une référence stable ;
- un code stable ;
- une définition ;
- un domaine ;
- un propriétaire ;
- une source ;
- une période de validité ;
- des libellés localisés ;
- des relations explicites ;
- des usages déclarés.

Exemple :

```text
Vocabulaire : VOC-GAMAD-PRODUIT-ETAT
Terme       : TERM-GAMAD-PRODUIT-ETAT-ACTIF
Code        : ACTIF
Définition  : produit autorisé à rendre ses fonctions prévues selon ses politiques et contrats actifs
```

### 4.3 Code canonique

Le code canonique est la valeur machine stable échangée dans les contrats.

Il ne doit pas dépendre :

- de la langue d’affichage ;
- de la casse saisie par une personne ;
- d’un libellé traduit ;
- d’un chemin de fichier ;
- d’une position dans une liste ;
- d’un identifiant auto-incrémenté.

### 4.4 Libellé

Le libellé sert à l’affichage humain.

Changer un libellé ne change pas le code canonique.

Exemples :

```text
Code : SUSPENDU
fr   : Suspendu
fr-FR: Suspendu
fr-CI: Suspendu
en   : Suspended
```

Une traduction ne doit jamais modifier la sémantique de sécurité.

---

## 5. Ce que CAP-CORE-010 possède

`CAP-CORE-010` possède :

- les références de vocabulaires ;
- leurs versions ;
- les termes canoniques ;
- leurs codes ;
- leurs définitions ;
- leurs libellés localisés ;
- leurs alias explicites ;
- leurs relations sémantiques explicites ;
- leurs correspondances avec des codes externes ;
- leur cycle de vie ;
- leurs propriétaires ;
- leurs sources ;
- leurs usages déclarés ;
- leurs projections immuables ;
- leurs analyses de compatibilité ;
- leurs preuves de conformité.

---

## 6. Ce que CAP-CORE-010 ne possède pas

`CAP-CORE-010` ne possède pas :

- les identités ;
- les produits ;
- les sources ;
- les politiques ;
- les contrats ;
- les décisions individuelles ;
- les données métier ;
- les documents ;
- les événements transportés ;
- les secrets ;
- les clés ;
- les scores de confiance ;
- les résultats du Matching ;
- les traductions libres de contenu métier ;
- les règles exécutables ;
- les expressions PHP ou SQL ;
- les taxonomies privées d’un satellite sans utilité transversale.

Répartition :

- `CAP-CORE-006` possède les sources ;
- `CAP-CORE-007` possède les politiques ;
- `CAP-CORE-009` possède les contrats ;
- `CAP-CORE-010` possède les termes partagés ;
- `CAP-CORE-011` possède les produits ;
- `CAP-CORE-013` possède l’audit ;
- `CAP-CORE-014` transportera les événements ;
- `CAP-CORE-021` consommera les termes nécessaires au Matching.

---

## 7. Règle de périmètre

Ne pas centraliser tous les enums de tous les satellites.

Un terme entre dans `CAP-CORE-010` seulement s’il satisfait au moins une condition :

1. il est utilisé par au moins deux capacités ;
2. il est échangé entre le Core et un produit ;
3. il figure dans un contrat actif de `CAP-CORE-009` ;
4. il porte une finalité, une action, un état ou un type dont la stabilité est nécessaire à l’écosystème ;
5. son changement pourrait rompre un consommateur ;
6. il doit être mappé avec un système externe.

Un vocabulaire purement local à Wasplex, GamaDrive ou IKOMA reste dans ce satellite tant qu’aucun contrat transversal n’en exige une projection canonique.

Exemple :

- `ACTIF` comme état d’un produit GAMAD peut être canonique ;
- une catégorie publicitaire interne propre à Wasplex reste dans Wasplex ;
- elle n’entre dans le Core que si un contrat transversal exige un signal normalisé précis.

---

## 8. État actuel à confirmer

Inspecter le dépôt fusionné avant toute modification.

État attendu :

1. `CAP-CORE-010` est `NO GO`.
2. Aucun module opérationnel `core/registre-vocabulaire/` n’existe.
3. Les vocabulaires sont dispersés dans les contraintes SQL, constantes PHP, OpenAPI, politiques et tests.
4. `CAP-CORE-001` contient notamment des listes fermées pour :
   - types d’identités ;
   - canaux d’inscription ;
   - niveaux d’assurance `A0` à `A3` ;
   - finalités ;
   - relations produit ;
   - relations organisation ;
   - événements ;
   - classifications ;
   - états d’identité.
5. `CAP-CORE-011` contient notamment :
   - types de produits ;
   - états `PREPARATION`, `ACTIF`, `SUSPENDU`, `RETIRE` ;
   - environnements `DEVELOPPEMENT`, `RECETTE`, `PRODUCTION`.
6. `CAP-CORE-006` contient notamment :
   - types de sources ;
   - états de sources ;
   - niveaux de vérification ;
   - résultats de vérification ;
   - types de lignée ;
   - finalités encore portées par références textuelles.
7. `CAP-CORE-007` contient notamment :
   - effets `PERMET` et `REFUSE` ;
   - états de versions de politique ;
   - actions canoniques.
8. `CAP-CORE-009` contient notamment :
   - types de contrats ;
   - états de versions ;
   - rôles des parties ;
   - types d’opérations ;
   - résultats de compatibilité ;
   - erreurs canoniques ;
   - finalités et actions référencées.
9. OpenAPI répète certains enums déjà présents en PHP ou SQL.
10. Les valeurs peuvent diverger en genre, casse ou formulation entre capacités.
11. Aucun service ne détecte systématiquement :
    - duplication sémantique ;
    - code inconnu ;
    - code retiré ;
    - alias ambigu ;
    - rupture de vocabulaire ;
    - dérive entre code, SQL, OpenAPI et contrats.
12. Une capacité peut aujourd’hui accepter une chaîne que les autres ne connaissent pas.
13. Les références de finalité de `CAP-CORE-006` restent textuelles faute de vocabulaire canonique.

Ne fixer aucun nombre de termes avant l’inventaire réel.

---

## 9. Audit initial obligatoire

Créer un inventaire temporaire des valeurs fermées.

Rechercher notamment :

```bash
rg -n --hidden \
  --glob '!.git/**' \
  --glob '!vendor/**' \
  --glob '!node_modules/**' \
  "CHECK \(|enum:|public const|private const|IN \('|finalite_reference|action_reference|type_|etat|niveau|resultat"
```

Inventorier pour chaque valeur :

- code ;
- définition réelle ;
- fichier ;
- table ou API ;
- capacité propriétaire ;
- contrats consommateurs ;
- langue ;
- casse ;
- stabilité ;
- caractère local ou transversal ;
- collisions ;
- synonymes ;
- divergences.

Produire un rapport distinguant :

- termes à bootstrapper ;
- termes locaux à laisser en place ;
- doublons exacts ;
- homonymes à ne pas fusionner ;
- divergences de casse ;
- divergences de définition ;
- valeurs historiques à conserver en alias ;
- valeurs inconnues à refuser.

Ne fusionner aucun terme uniquement parce que son libellé se ressemble.

---

## 10. Architecture cible
