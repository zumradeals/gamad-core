# CAP-CORE-015 — Integrity Proofs

Note préparatoire du chantier de passage de `NO GO` à `GO`.

## Ordre de lecture

1. `01-fiche-de-codage.md` — mission, prérequis, frontières et état actuel.
2. `02-modele-preuves-et-cryptographie.md` — modèle persistant, types de preuve, canonicalisation et algorithmes.
3. `03-commandes-verification-et-raccordements.md` — émission, vérification, révocation, manifestes et intégrations.
4. `04-api-console-contrats-tests.md` — API de vérification, console, contrats, audit et épreuves.
5. `05-exploitation-criteres-go.md` — exploitation, sécurité, sauvegarde, CI, critères GO et rapport final.

## Prérequis de lancement

```text
CAP-CORE-010 — GO et fusionnée
CAP-CORE-002 — GO et fusionnée
CAP-CORE-012 — GO et fusionnée
CAP-CORE-014 — GO et fusionnée
CAP-CORE-016 — GO et fusionnée
```

Les capacités déjà `GO`, notamment `CAP-CORE-006`, `CAP-CORE-009`, `CAP-CORE-013`, `CAP-CORE-019` et `CAP-CORE-022`, doivent rester vertes pendant tout le chantier.

## Règle absolue

`CAP-CORE-015` distingue toujours :

```text
empreinte
≠ preuve d’origine

signature valide
≠ vérité du contenu signé

preuve technique
≠ qualification juridique automatique
```

La capacité ne stocke aucune clé privée. Elle demande à `CAP-CORE-016` d’exécuter les opérations cryptographiques autorisées à partir de références et handles opaques.

Claude Code doit lire les cinq parties avant de commencer, traiter uniquement `CAP-CORE-015`, ouvrir une PR dédiée et s’arrêter lorsque cette PR est verte et prête à fusionner.
