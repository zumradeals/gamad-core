# CAP-CORE-008 — Decisions Registry

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, prérequis, frontières et architecture.
2. `02-modele-de-donnees-et-cycle.md` — dossiers, autorités, options, positions, décisions et effets.
3. `03-commandes-instruction-decision-execution.md` — instruction, adoption, preuve, mise en vigueur et rapprochement des effets.
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
```

Les capacités déjà `GO`, notamment `CAP-CORE-001`, `CAP-CORE-003`, `CAP-CORE-004`, `CAP-CORE-006`, `CAP-CORE-007`, `CAP-CORE-009`, `CAP-CORE-013`, `CAP-CORE-019` et `CAP-CORE-022`, doivent rester vertes pendant tout le chantier.

## Séparation essentielle

```text
CAP-CORE-004 — Authorization
= décide si une action est permise maintenant

CAP-CORE-008 — Decisions Registry
= conserve une décision formelle, son autorité, son motif, ses conditions et ses effets durables

CAP-CORE-013 — Common Audit
= conserve la trace de ce qui a été fait
```

Une réponse `PERMIS` de `CAP-CORE-004` n’est jamais une décision formelle de `CAP-CORE-008`.

Une décision enregistrée dans `CAP-CORE-008` ne contourne jamais `CAP-CORE-004` : chaque instruction, position, adoption, annulation et exécution reste autorisée séparément.

## Règle d’exécution

Claude Code doit lire les cinq parties avant de commencer, traiter uniquement `CAP-CORE-008`, ouvrir une PR dédiée et s’arrêter lorsque cette PR est verte et prête à fusionner.
