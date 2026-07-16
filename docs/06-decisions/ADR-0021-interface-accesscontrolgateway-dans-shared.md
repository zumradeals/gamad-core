# ADR-0021 — Interface AccessControlGateway dans Shared

**Statut :** Accepté
**Date :** 2026-07-15
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD
**Référence :** GENESIS-014 §C, ADR-0013

---

## Contexte

Les bounded contexts existants (Identity Registry, Persons and Accounts, Organizations and Memberships) doivent pouvoir appeler le moteur Access Control sans prendre une dépendance directe vers `src/AccessControl/` — ce qui violerait ADR-0013.

---

## Décision

1. Définir `AccessControlGateway` dans `src/Shared/Contract/AccessControlGateway.php` — une interface minimale à une seule méthode : `can(IdentityId $actor, string $action, IdentityId $context): AccessDecision`.
2. Deux implémentations coexistent temporairement : `PermissiveAccessControlGateway` (retourne `ALLOW` sur tout, utilisée pendant l'implémentation) et `RbacAccessControlGateway` (moteur réel, branchée à la livraison).
3. Le câblage dans `public/index.php` détermine laquelle est active — jamais une variable d'environnement qui basculerait silencieusement entre les deux.
4. `AccessDecision` est un value object sealed à deux états (`ALLOW`, `DENY`) défini dans `src/Shared/Contract/`.

---

## Conséquences

- Les trois contextes existants peuvent commencer à appeler l'interface dès la Tâche 2, sans attendre le moteur réel.
- Le remplacement (Tâche 9) ne change rien côté appelant — juste l'implémentation derrière l'interface.
