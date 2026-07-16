# ADR-0022 — Audit des décisions DENY obligatoire

**Statut :** Accepté
**Date :** 2026-07-15
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** GENESIS-013 §6 invariant 2

---

## Contexte

GENESIS-013 pose que toute décision d'autorisation est auditée, y compris les refus. Les systèmes qui n'auditent que les accès accordés créent des angles morts : une attaque par énumération de permissions ou une tentative d'escalade de privilèges devient invisible.

---

## Décision

1. Chaque évaluation du moteur (`RbacAccessControlGateway::can()`) publie un événement `AccessDecisionMade` via l'Outbox, qu'elle retourne `ALLOW` ou `DENY`.
2. L'événement porte : `actor_id`, `action`, `context_id`, `decision` (`ALLOW`/`DENY`), `reason` (nom du rôle ayant accordé, ou `no_matching_role` si DENY), `evaluated_at`.
3. `PermissiveAccessControlGateway` ne publie aucun événement — elle est provisoire et son volume d'appels ne doit pas polluer l'audit.
4. Le volume d'événements `AccessDecisionMade` étant potentiellement élevé, ils sont publiés dans l'Outbox avec une priorité inférieure aux événements de domaine métier — une file dédiée `access_decisions` sera ajoutée à la configuration de l'Outbox pour les isoler.

---

## Conséquences

- Toute tentative d'accès non autorisée est tracée dans la chaîne d'audit.
- L'Outbox reste un mécanisme unique, mais avec deux files de priorité distinctes.
