# Chantiers de capacités NO GO

Ce dossier contient les **notes de codage préparatoires** des capacités qui ne sont pas encore `GO`.

Ces notes ne décrivent pas un état déjà livré. Elles donnent à Claude Code l’ordre, les dépendances, les frontières, les travaux et les critères de passage à `GO`.

Les fiches finales décrivant le code réellement livré restent à la racine de `docs/capacites/`, sous la forme :

```text
docs/capacites/CAP-CORE-XXX-*.md
```

## Cycle d’une note

1. La note est rédigée ici avant le codage.
2. Claude Code vérifie que tous ses prérequis sont `GO` et fusionnés dans `main`.
3. Claude exécute un seul chantier dans une branche et un worktree isolés.
4. La capacité reste `NO GO` jusqu’à réussite de toute la CI.
5. Après fusion, Claude crée ou remplace la fiche finale à la racine de `docs/capacites/` pour décrire le code réel.
6. La note de chantier est conservée comme trace, avec une mention de la PR et du commit de livraison.

## Capacités déjà GO et déjà documentées

Les notes préparatoires de `CAP-CORE-006`, `CAP-CORE-007`, `CAP-CORE-009` et `CAP-CORE-011` ne sont pas recopiées ici : leurs chantiers sont terminés et leurs fiches finales existent déjà dans `docs/capacites/`.

## Ordre d’implémentation restant

```text
01. CAP-CORE-010 — Canonical Vocabulary
02. CAP-CORE-002 — Organizations Registry
03. CAP-CORE-012 — Realms Registry
04. CAP-CORE-014 — Event Journal
05. CAP-CORE-016 — Secrets & Keys
06. CAP-CORE-015 — Integrity Proofs
07. CAP-CORE-008 — Decisions Registry
08. CAP-CORE-017 — Risks & Exceptions
09. CAP-CORE-018 — Incidents
10. CAP-CORE-020 — Directory & Atlas
11. CAP-CORE-021 — Matching Engine
```

## Règle d’exécution

Claude ne commence jamais une capacité tant que la capacité précédente et tous ses prérequis ne sont pas `GO` et fusionnés dans `main`.

Une session de codage traite une seule capacité, ouvre une seule PR et s’arrête lorsque la PR est verte et prête à fusionner.

## Notes déjà préparées

- `CAP-CORE-010/` — Vocabulaire canonique.
- `CAP-CORE-002/` — Registre des organisations.
- `CAP-CORE-012/` — Registre des realms et périmètres.
- `CAP-CORE-014/` — Journal commun des événements, outbox, abonnements, livraison, reprise et rejeu.
- `CAP-CORE-016/` — Registre de gouvernance des secrets et clés, fournisseurs externes, usages, rotations, compromissions et récupération.
- `CAP-CORE-015/` — Preuves d’intégrité, empreintes, signatures, manifestes, attestations, checkpoints et vérifications.
- `CAP-CORE-008/` — Dossiers de décision, autorités compétentes, instruction, quorum, adoption, effets, exécution, annulation et remplacement.

Les notes suivantes seront ajoutées directement dans ce dossier, dans l’ordre ci-dessus.
