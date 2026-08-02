# Registre des normes — service `CTR-04` (`CAP-CORE-007`)

Ce module expose le contrat `CTR-04` **en lecture seule** sur l'index technique
du Core. Il ne lit aucun fichier documentaire : l'index est une base
relationnelle, initialisée de façon contrôlée depuis une baseline versionnée et
reconstructible à volonté.

## Ce qu'il fait

- **`BaselineOperationnelle`** — initialise ou réinitialise l'index depuis
  `resources/index-baseline-v1.json`, dont l'empreinte SHA-256 est vérifiée
  avant toute écriture. La reconstruction est transactionnelle et idempotente ;
  une baseline altérée est refusée sans détruire l'index existant.
- **`resoudreNorme`** — résout une norme et son statut, y compris à une date
  passée (reconstruction temporelle).
- **`resoudreSource`** — délègue à `CTR-15` (`core/registre-sources`),
  titulaire du contrat des sources et de son magasin persistant propre
  (CAP-CORE-006). La dépendance va de ce module vers `CTR-15`, jamais
  l'inverse : `CTR-15` ne lit aucune table de cet index. Ce module ajoute
  seulement, ici, une projection de compatibilité historique (`rang`,
  `statut`, `adoption_reference`, `versionnee`) pour les appelants existants,
  quand la source est aussi connue comme norme versionnée dans la baseline.
- **`resoudreCapacite`** — résout l'état d'une capacité, dimension par
  dimension, à une date donnée.
- **`diagnostiquerIndex`** — diagnostic opérationnel : intégrité de la baseline
  et concordance des volumes réellement présents dans l'index.

## Ce qui est tenu par le code

Historique en ajout seul (aucun `UPDATE`/`DELETE` sur `statut`, `adoption`,
`relation_evolution`), index reconstructible et jamais autoritatif sur les
registres persistants, empreinte de la source d'initialisation vérifiée. En
déploiement, l'ajout seul est en outre durci par les privilèges PostgreSQL du
rôle applicatif.

## Exécuter en local

Aucune dépendance, aucun secret. PHP 8.2+ avec `pdo_sqlite` suffit.

```bash
# Garde de reconstruction temporelle (doit sortir 0)
php core/registre-normes/tests/temporel_p3.php
```

La console Laravel initialise l'index depuis la baseline au premier accès s'il
est encore vide. La réindexation explicite passe par
`php artisan registre:reindexer`.

## Déploiement actif — serveur local

Nginx sert le monolithe Laravel dans `apps/console-laravel/public`, via PHP
8.3-FPM. Le monolithe appelle ce module pour initialiser et lire l'index.

En exploitation, `DATABASE_URL` désigne la base PostgreSQL locale dédiée à
l'index. **`DATABASE_URL` est un secret** : elle réside dans l'environnement
protégé du serveur, jamais dans le dépôt. SQLite reste uniquement un repli local
et de CI.

La topologie, l'ordre de migration, la sauvegarde et l'exercice de restauration
sont décrits dans `ops/core-foundation/README.md`.

## Ce que ce module n'est pas

Il n'est pas un magasin métier, ne porte aucun dossier de satellite et ne juge
personne. C'est l'index technique commun du Core, en lecture seule.
