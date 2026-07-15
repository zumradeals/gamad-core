# ADR-0020 — Contrainte d'unicité de membership actif via index partiel PostgreSQL

**Statut :** Accepté
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** GENESIS-011 §4 invariant 6, GENESIS-012 §D

---

## Contexte

L'invariant « au plus un membership actif par personne par organisation » doit être garanti structurellement, pas seulement applicativement. Une contrainte UNIQUE classique sur `(person_id, organization_id)` interdirait les memberships historiques (`ended`) pour une même paire — ce qui contredirait GENESIS-011 §4 invariant 8 (pas de suppression physique, les memberships terminés doivent subsister).

---

## Décision

Utiliser un index partiel PostgreSQL : `UNIQUE (person_id, organization_id) WHERE status = 'active'`. Cette contrainte garantit l'unicité uniquement sur les memberships actifs, sans affecter les memberships terminés ou suspendus.

---

## Conséquences

- La contrainte est structurelle — elle ne repose pas uniquement sur la logique applicative.
- Un même couple `(person_id, organization_id)` peut avoir un membership `ended` et un membership `active` simultanément, ce qui couvre le cas d'une personne qui quitte puis revient.
- Cette approche est déjà éprouvée dans le Core (patron de l'index partiel utilisé pour d'autres contraintes conditionnelles).
