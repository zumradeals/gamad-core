# CAP-CORE-017 — Risks & Exceptions

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, prérequis, frontières et architecture.
2. `02-modele-risques-exceptions.md` — registre persistant, évaluations, traitements, dérogations et cycles.
3. `03-commandes-evaluation-et-raccordements.md` — commandes, résolutions, décisions, expirations et intégrations.
4. `04-api-console-contrats-tests.md` — API, console, contrats, audit, événements et épreuves.
5. `05-exploitation-criteres-go.md` — exploitation, sécurité, sauvegarde, CI, critères GO et rapport final.

## Prérequis de lancement

```text
CAP-CORE-010 — GO et fusionnée
CAP-CORE-002 — GO et fusionnée
CAP-CORE-012 — GO et fusionnée
CAP-CORE-014 — GO et fusionnée
CAP-CORE-016 — GO et fusionnée
CAP-CORE-015 — GO et fusionnée
CAP-CORE-008 — GO et fusionnée
```

Les capacités déjà `GO`, notamment `CAP-CORE-001`, `CAP-CORE-003`, `CAP-CORE-004`, `CAP-CORE-006`, `CAP-CORE-007`, `CAP-CORE-009`, `CAP-CORE-011`, `CAP-CORE-013`, `CAP-CORE-019` et `CAP-CORE-022`, doivent rester vertes pendant tout le chantier.

## Séparation essentielle

```text
RISQUE
= situation possible susceptible de produire un dommage

EXCEPTION
= écart temporaire, borné et approuvé à une exigence dérogeable

DÉCISION
= acte formel qui accepte, refuse, renouvelle ou révoque l’exception

INCIDENT
= événement réellement survenu, traité par CAP-CORE-018
```

Un risque n’est pas automatiquement un incident.

Une exception n’est jamais une permission générale.

Une décision d’acceptation ne modifie jamais silencieusement une politique, un contrat ou une règle. Elle autorise uniquement l’existence d’une exception précisément bornée ; la capacité consommatrice continue d’appliquer `CAP-CORE-004` et les règles actives de `CAP-CORE-007`.

## Règles absolues

Toute exception doit être :

- liée à une exigence exacte et dérogeable ;
- limitée à un sujet, une ressource, un produit, une organisation, un realm et un environnement précis ;
- justifiée par un risque évalué ;
- accompagnée de mesures compensatoires vérifiables ;
- approuvée par une décision `CAP-CORE-008` ;
- assortie d’une date de début et d’une date d’expiration ;
- automatiquement inactive à l’expiration ;
- réévaluée avant tout renouvellement ;
- journalisée et prouvable.

Aucune exception permanente, implicite, rétroactive ou fondée sur un texte libre n’est autorisée.

Les contrôles déclarés non dérogeables restent non dérogeables, y compris pour le fondateur.

## Règle d’exécution

Claude Code doit lire les cinq parties avant de commencer, traiter uniquement `CAP-CORE-017`, ouvrir une PR dédiée et s’arrêter lorsque cette PR est verte et prête à fusionner.
