# CAP-CORE-016 — Secrets & Keys

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, prérequis, frontières et état actuel.
2. `02-modele-fournisseurs-et-cycle.md` — registre persistant, fournisseurs, versions, usages et cycle.
3. `03-inventaire-migration-rotation.md` — inventaire réel, bootstrap, commandes, migrations et rotations.
4. `04-api-console-contrats-tests.md` — API de métadonnées, console, contrats, audit et épreuves.
5. `05-exploitation-criteres-go.md` — exploitation, récupération, sécurité, CI, critères GO et rapport final.

## Prérequis de lancement

```text
CAP-CORE-010 — GO et fusionnée
CAP-CORE-002 — GO et fusionnée
CAP-CORE-012 — GO et fusionnée
CAP-CORE-014 — GO et fusionnée
```

Les capacités déjà `GO`, notamment `CAP-CORE-005`, `CAP-CORE-009`, `CAP-CORE-013`, `CAP-CORE-019` et `CAP-CORE-022`, doivent rester vertes pendant tout le chantier.

## Règle absolue

Le registre de `CAP-CORE-016` ne stocke jamais :

- un secret en clair ;
- une clé privée ;
- une phrase secrète ;
- un mot de passe ;
- un code de secours ;
- un jeton actif ;
- une valeur de variable d’environnement sensible.

Il stocke uniquement les références, métadonnées, versions, empreintes publiques, usages, rotations et états nécessaires pour gouverner des secrets conservés dans des fournisseurs externes.

Claude Code doit lire les cinq parties avant de commencer, traiter uniquement `CAP-CORE-016`, ouvrir une PR dédiée et s’arrêter lorsque cette PR est verte et prête à fusionner.
