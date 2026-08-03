# CAP-CORE-020 — Directory & Atlas

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, prérequis, frontières, architecture et cas d’usage.
2. `02-modele-annuaire-atlas.md` — entrées, nœuds, relations, observations, projections, fraîcheur et visibilité.
3. `03-collecte-reconciliation-et-requetes.md` — descripteurs, événements, réconciliation, recherche, graphe et analyse d’impact.
4. `04-api-console-contrats-tests.md` — API, console, politiques, contrats, audit, événements et épreuves.
5. `05-exploitation-criteres-go.md` — exploitation, sécurité, performance, sauvegarde, CI, critères GO et rapport final.

## Prérequis de lancement

```text
CAP-CORE-010 — GO et fusionnée
CAP-CORE-002 — GO et fusionnée
CAP-CORE-012 — GO et fusionnée
CAP-CORE-014 — GO et fusionnée
CAP-CORE-016 — GO et fusionnée
CAP-CORE-015 — GO et fusionnée
CAP-CORE-008 — GO et fusionnée
CAP-CORE-017 — GO et fusionnée
CAP-CORE-018 — GO et fusionnée
```

Les capacités déjà `GO`, notamment `CAP-CORE-001`, `CAP-CORE-003`, `CAP-CORE-004`, `CAP-CORE-005`, `CAP-CORE-006`, `CAP-CORE-007`, `CAP-CORE-009`, `CAP-CORE-011`, `CAP-CORE-013`, `CAP-CORE-019` et `CAP-CORE-022`, doivent rester vertes pendant tout le chantier.

## Séparation essentielle

```text
DIRECTORY
= rechercher et présenter les éléments opérationnels connus

ATLAS
= représenter les relations, dépendances, flux et périmètres entre ces éléments

REGISTRES SOUVERAINS
= restent propriétaires des données et états métier

CAP-CORE-020
= conserve des projections traçables, fraîches et reconstructibles
```

`CAP-CORE-020` ne devient jamais une seconde source de vérité pour les produits, organisations, realms, contrats, politiques, risques ou incidents.

Il ne décide jamais qu’une capacité est `GO` en lisant une fiche Markdown. Il peut seulement afficher un statut attesté par une source technique autorisée, par exemple une livraison signée, une CI reconnue, un événement de déploiement ou une sonde de préparation.

## Règles absolues

- aucune donnée n’est inventée pour compléter une fiche incomplète ;
- toute projection indique sa source, sa date d’observation et sa fraîcheur ;
- un état déclaré et un état observé restent séparés ;
- l’indisponibilité d’une source n’est jamais affichée comme un état sain ;
- un realm parent ne voit pas automatiquement les détails des realms enfants ;
- aucun secret, jeton, clé privée, mot de passe ou donnée métier complète n’entre dans l’Atlas ;
- aucune dépendance n’est déduite par rapprochement approximatif de noms ;
- aucun scan arbitraire du dépôt, du réseau ou des bases n’est utilisé comme source de vérité ;
- aucune URL interne sensible n’est publiée sans projection explicitement autorisée ;
- aucune vue publique universelle n’est activée par défaut ;
- aucune relation supprimée de la source ne reste présentée comme actuelle après réconciliation ;
- aucune projection ancienne n’est confondue avec une observation actuelle.

## Règle d’exécution

Claude Code doit lire les cinq parties avant de commencer, traiter uniquement `CAP-CORE-020`, ouvrir une PR dédiée et s’arrêter lorsque cette PR est verte et prête à fusionner.

Ne pas commencer `CAP-CORE-021 — Matching Engine` dans la même session.