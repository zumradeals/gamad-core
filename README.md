# GAMAD Core

GAMAD Core est le socle commun de l’écosystème numérique GAMAD.

Il fournit les fonctions transversales qui ne doivent pas être recréées séparément dans chaque produit : identité canonique, authentification, autorisations, organisations, contrats, produits, événements, audit, sécurité, continuité, Matching et fédération des satellites.

Le dépôt contient :

- `apps/` : applications et interfaces du Core ;
- `core/` : modules techniques des capacités ;
- `docs/` : description fonctionnelle et technique active ;
- `outils/` et les répertoires d’exploitation : scripts, sauvegarde, restauration, déploiement et contrôles ;
- `CLAUDE.md` : méthode de travail dans le dépôt.

## Documentation active

Commencer par :

1. `docs/00-vision-gamad-core.md`
2. `docs/01-architecture-core-et-satellites.md`
3. `docs/02-compte-gamad-federation-et-portail.md`
4. `docs/03-moteur-de-matching-gamad.md`
5. `docs/capacites/README.md`

Les anciens textes Genesis II, actes, lois, adoptions et registres normatifs ont été retirés de la version active. Ils restent consultables dans l’historique Git.

## Règle de vérité

Un fichier Markdown décrit une capacité ; il ne prouve pas qu’elle fonctionne.

L’état réel est établi par le code, les migrations, les tests reproductibles, l’exploitation, la sauvegarde, la restauration et les observations de production.
