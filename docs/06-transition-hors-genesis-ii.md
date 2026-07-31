# Transition hors Genesis II

## 1. État

**Migration terminée. `genesis-ii/` est supprimé de `main`.**

Le dépôt ne contient plus de corpus documentaire normatif, plus de parseur
Markdown, plus d’actes d’adoption, plus de contrôle d’intégrité documentaire et
plus de module dont l’unique fonction était de lire ces textes.

Aucun texte normatif n’est requis avant de coder. Les sources techniques du Core
sont désormais :

- le code des modules `core/` et de la console `apps/console-laravel` ;
- les configurations et les variables d’environnement ;
- les migrations et les schémas ;
- la baseline opérationnelle `core/registre-normes/resources/index-baseline-v1.json` ;
- les tests de comportement exécutés par la CI ;
- la documentation de `docs/`.

L’historique ancien reste intégralement récupérable dans Git : les textes
supprimés existent dans les commits antérieurs à la suppression finale, et rien
n’a été réécrit sur `main`.

## 2. Ce que la migration a produit

### 2.1 Initialisation de l’index

La reconstruction de l’index ne parcourt plus aucun fichier Markdown. Elle
s’appuie sur `BaselineOperationnelle` :

- une photographie technique versionnée, protégée par une empreinte SHA-256
  vérifiée avant toute écriture ;
- une validation du format, des tables, des colonnes, des identifiants et des
  compteurs ;
- une transaction unique avec retour arrière en cas d’échec ;
- une baseline altérée refusée sans destruction de l’index existant.

La baseline ne référence plus aucun chemin de fichier : les provenances y sont
exprimées en références canoniques. C’est une source d’initialisation contrôlée,
pas le modèle final des capacités du Core.

Elle est utilisée par `php artisan registre:reindexer`, par les contrôleurs
`CTR-01` à `CTR-04` lorsqu’ils rencontrent un index vide, et par les gardes.

### 2.2 Diagnostics au lieu de preuves documentaires

`CTR-04` ne recalcule plus l’empreinte de fichiers Markdown et ne compte plus
d’actes sur disque. Il expose `diagnostiquerIndex()` :

- intégrité de la source d’initialisation ;
- concordance des volumes réellement présents dans l’index ;
- divergences nommées, jamais présumées absentes.

Le tableau de bord de la console présente l’état de la fondation, la
disponibilité des magasins, l’état des capacités, le journal opérationnel, les
alertes techniques et ce diagnostic. Il ne présente plus de tableau d’adoptions.

### 2.3 Modules supprimés

Quatorze modules `core/registre-*` n’existaient que pour parcourir le corpus :
annuaire, audit, continuité, contrats, décisions, événements, incidents,
lexique, organisations, preuves, produits, realms, risques, secrets. Ils ne
rendaient aucun service au runtime et ne pouvaient plus fonctionner sans les
fichiers supprimés. Le catalogue des capacités reflète leur état réel.

### 2.4 Gardes conservées

Les gardes conservées initialisent leur index depuis la baseline et éprouvent le
comportement, non la conformité de textes. Chacune porte une contre-épreuve :
une garde qui ne peut pas échouer ne prouve rien.

## 3. Ce qui a survécu

- responsabilités des capacités et contrats utiles ;
- code transversal : identités, mandats, autorisation, accès, journal ;
- données techniques nécessaires à l’exploitation ;
- refus par défaut de l’autorisation ;
- journal opérationnel append-only et sa chaîne d’empreintes ;
- authentification, sessions, passkeys ;
- sauvegarde, restauration et exercice de restauration ;
- tests de comportement, SQLite et PostgreSQL ;
- historique Git permettant de retrouver les anciens textes.

## 4. Vérification

```bash
test ! -d genesis-ii
test ! -f core/registre-normes/src/Ingestion.php
grep -rn --exclude-dir=.git --exclude-dir=vendor --exclude-dir=node_modules \
  'new Ingestion\|REGN_CORPUS\|genesis-ii/' .
```

Les seules occurrences admises sont les mentions historiques du présent document
et les assertions de gardes qui vérifient précisément cette absence.

## 5. Règle de sécurité maintenue

La transition n’a rendu le système permissif sur aucun point. Lorsqu’une règle
n’a pas de remplaçant explicite, le comportement reste refusé par défaut et le
manque est signalé comme chantier, jamais comblé par une permission implicite.
