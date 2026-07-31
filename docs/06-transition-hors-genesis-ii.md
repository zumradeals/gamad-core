# Transition hors Genesis II

## 1. Décision

Genesis II n’est plus la méthode cible pour piloter le développement de GAMAD Core.

Les nouveaux chantiers utilisent les documents simples de `docs/`, le code, les contrats, les configurations et les tests.

L’ancien corpus reste temporairement dans le dépôt parce que certains modules et certaines gardes lisent encore directement ses fichiers.

## 2. Pourquoi la suppression immédiate est dangereuse

Les contrôles réalisés ont montré que Genesis II sert encore de source de données runtime ou de données de test pour plusieurs registres.

Le supprimer avant migration peut provoquer :

- un index vide ;
- des autorisations refusées faute de politique ;
- des identités ou produits historiques introuvables ;
- des tests d’intégration cassés ;
- une console partiellement indisponible ;
- une fausse impression de simplification obtenue en supprimant les gardes.

## 3. Étape 1 — Documentation active

Périmètre :

- `README.md` ;
- `CLAUDE.md` ;
- `docs/`.

Cette étape ne supprime aucun module, aucun test, aucun workflow et aucun fichier Genesis II.

Résultat attendu : les développeurs et agents disposent d’une vision claire, sans que le runtime change.

## 4. Étape 2 — Migration technique

Migrer un consommateur à la fois.

Pour chaque module :

1. identifier les fichiers Genesis II lus ;
2. identifier les données réellement nécessaires ;
3. créer une source technique explicite : table, configuration versionnée, seed contrôlé ou contrat ;
4. adapter le module ;
5. adapter les tests sans réduire leur exigence ;
6. exécuter les tests du module et les intégrations affectées ;
7. supprimer uniquement les lectures historiques devenues inutiles ;
8. documenter l’état réel.

Ordre conseillé :

```text
console et API critiques
→ identité
→ autorisation
→ autorités et mandats
→ produits et fédération
→ contrats et événements
→ autres registres encore utiles
```

L’ordre définitif dépendra de l’audit des dépendances.

### 4.1 Première migration réalisée — `registre:reindexer`

**État : IMPLÉMENTÉ dans `main`.**

La commande de réindexation ne reconstruit plus l’index en parcourant les fichiers Markdown de Genesis II. Elle utilise désormais :

- `core/registre-normes/resources/index-baseline-v1.json` comme photographie technique versionnée ;
- `BaselineOperationnelle` comme importeur contrôlé ;
- une empreinte SHA-256 fixe ;
- une validation du format, des tables, des colonnes, des identifiants et des compteurs ;
- une transaction unique avec retour arrière en cas d’échec ;
- un test d’intégration dédié et exécuté par la CI.

La baseline conserve temporairement les données nécessaires à la compatibilité des consommateurs non encore migrés : normes, statuts, politiques, règles, identités techniques, fonctions et mandat actif. Elle ne réintroduit pas les textes Markdown et n’est pas présentée comme le modèle final du Core.

### 4.2 Deuxième migration — contrôleur HTTP `CTR-01`

**État : IMPLÉMENTÉ sur la branche de migration, sous réserve de fusion après tests.**

Le contrôleur de lecture des identités n’appelle plus `Ingestion` lorsqu’il rencontre un index absent ou vide. Il initialise désormais l’index avec `BaselineOperationnelle::standard()`.

Le test d’intégration dédié vérifie :

- un démarrage sans index préconstruit ;
- l’ouverture d’une session API sans initialisation implicite de l’index ;
- la reconstruction lors de la première lecture d’identité ;
- la présence des sept identités techniques et des quatorze règles attendues ;
- la réutilisation stable de l’index lors d’une seconde lecture ;
- l’absence de référence à `Ingestion` et à `genesis-ii` dans le contrôleur.

Cette migration ne permet pas encore de supprimer `Ingestion.php` ni `genesis-ii/`, car d’autres contrôleurs, la console et plusieurs gardes les utilisent toujours directement.

## 5. Étape 3 — Suppression finale

La suppression de `genesis-ii/` est autorisée seulement lorsque :

- aucun chemin runtime utile ne lit le corpus ;
- aucun test conservé ne dépend de ses actes ;
- les données indispensables possèdent une source technique nouvelle ;
- la console et l’API passent leurs tests ;
- les capacités conservées ont des gardes adaptées ;
- une procédure de retour arrière est disponible.

Vérification minimale :

```bash
grep -R "genesis-ii" core apps config routes tests .github outils
```

Chaque résultat doit être classé : dépendance runtime, test historique, commentaire, documentation ou référence d’archive.

## 6. Ce qui peut disparaître à la fin

- lois et constitutions ;
- actes d’adoption ;
- registres purement normatifs ;
- parseurs de Markdown ;
- contrôles d’intégrité propres au corpus ;
- écrans ne présentant que les normes ;
- tests qui prouvent seulement la cohérence des textes supprimés.

## 7. Ce qui doit survivre

- responsabilités des capacités ;
- contrats utiles ;
- code métier transversal ;
- données techniques nécessaires ;
- autorisations ;
- identités et relations ;
- tests de comportement ;
- audit ;
- continuité ;
- sauvegarde et restauration ;
- historique Git permettant de retrouver les anciens textes.

## 8. Règle de sécurité

La transition ne doit jamais produire un système plus permissif par accident.

Lorsqu’une politique historique n’a pas encore de remplaçant explicite, le comportement doit rester refusé par défaut et le manque doit être signalé comme chantier à résoudre.
