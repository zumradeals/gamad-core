# ADR-0015 — Exposition en lecture de la vérification d'audit

**Statut :** Accepté  
**Date :** 2026-07-13  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** `PostgreSqlAuditChainVerifier` (existant), GENESIS-003 §6.12

---

## Contexte

La vérification d'intégrité de la chaîne d'audit n'existe aujourd'hui que via `bin/audit-verify` (CLI). La Console d'Exploitation doit pouvoir l'afficher sans accès shell au serveur.

---

## Décision

1. Ajouter la route `GET /admin/runtime/audit/verify` au contrat `openapi/admin-runtime-v1.yaml`, protégée par le scope `core.audit.verify.read`.
2. Cette route appelle exclusivement la logique déjà existante de `PostgreSqlAuditChainVerifier` — aucune nouvelle règle métier n'est introduite, uniquement un nouveau point d'entrée HTTP en lecture seule.
3. La réponse expose : `valid` (bool), `verified_count` (int), et, si invalide, l'identifiant de l'entrée en rupture — jamais le contenu détaillé d'un enregistrement d'audit (pas de fuite de données métier via cette route de supervision).

---

## Conséquences

- Seule extension de contrat API de cette Directive. Toute autre fonctionnalité de la console consomme des routes déjà existantes.
