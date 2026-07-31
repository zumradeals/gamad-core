# GAMAD Core

GAMAD Core est le socle commun de l’écosystème GAMAD.

Il fournit les capacités transversales dont plusieurs produits ont besoin : identité canonique, authentification, autorisations, organisations, contrats, événements, audit, continuité, catalogue des produits, fédération des satellites et Matching transversal.

Le Core ne remplace pas les produits. Wasplex, GamaDrive, IKOMA et les futurs satellites conservent leurs interfaces, leurs comptes produit, leurs données détaillées et leurs règles métier.

## Documentation active

La documentation de travail commence dans [`docs/README.md`](docs/README.md).

Ordre conseillé :

1. [`docs/00-vision-ecosysteme-gamad.md`](docs/00-vision-ecosysteme-gamad.md) ;
2. [`docs/01-architecture-core-portail-satellites.md`](docs/01-architecture-core-portail-satellites.md) ;
3. [`docs/02-compte-gamad-et-federation.md`](docs/02-compte-gamad-et-federation.md) ;
4. [`docs/03-matching-transversal.md`](docs/03-matching-transversal.md) ;
5. [`docs/capacites/CATALOGUE.md`](docs/capacites/CATALOGUE.md).

## État de la transition

La transition hors de l’ancien corpus normatif est **terminée**. Le dépôt est
piloté par le code, les configurations, les migrations, les tests et `docs/`.

Aucun texte normatif n’est requis avant de coder. L’historique ancien reste
récupérable dans Git. Le détail figure dans
[`docs/06-transition-hors-genesis-ii.md`](docs/06-transition-hors-genesis-ii.md).

## Structure principale

```text
apps/        applications et console
core/        capacités et services partagés
ops/         exploitation, sauvegarde et restauration
docs/        documentation active simple
archives/    archives historiques, sans rôle runtime
```

## Principe de travail

Une capacité n’est pas un fichier Markdown. Elle existe réellement lorsqu’elle possède une responsabilité claire, des données maîtrisées, des contrats, du code, des tests et une exploitation vérifiable.

Les documents de `docs/` décrivent directement ce qu’il faut construire et les frontières à respecter. Ils ne constituent ni des lois, ni des actes d’adoption, ni une preuve que le code existe.