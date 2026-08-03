# CAP-CORE-014 — Event Journal

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, prérequis, frontières et architecture.
2. `02-modele-de-donnees.md` — enveloppe, outbox, journal, abonnements et livraisons.
3. `03-commandes-routage-consommation.md` — publication, routage, consommation, reprise et rapprochement.
4. `04-api-console-contrats-tests.md` — API, console, contrats, audit et épreuves.
5. `05-exploitation-criteres-go.md` — exploitation, sécurité, CI, critères GO et rapport final.

## Prérequis de lancement

```text
CAP-CORE-010 — GO et fusionnée
CAP-CORE-002 — GO et fusionnée
CAP-CORE-012 — GO et fusionnée
```

Les autres capacités déjà `GO`, notamment `CAP-CORE-009`, `CAP-CORE-013` et `CAP-CORE-022`, doivent rester vertes pendant tout le chantier.

Claude Code doit lire les cinq parties avant de commencer, traiter uniquement `CAP-CORE-014`, ouvrir une PR dédiée et s’arrêter lorsque cette PR est verte et prête à fusionner.
