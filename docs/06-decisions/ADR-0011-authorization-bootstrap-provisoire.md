# ADR-0011 — Le mécanisme d'autorisation actuel est provisoire

**Statut :** Accepté  
**Date :** 2026-07-13  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-003 §6.7 (Access Control)

---

## Contexte

L'API d'administration utilise aujourd'hui `ScopeAuthorizationMiddleware` combiné à une liste de tokens/scopes statiques (`GAMAD_ADMIN_TOKENS_JSON` ou `GAMAD_ADMIN_PERMISSIONS_JSON`) ou à la vérification OIDC d'un token porteur. Ce mécanisme répond à la question technique « ce token a-t-il ce scope ? ». Il ne répond pas à la question posée par la Charter §6.7 : « cet acteur peut-il effectuer cette action sur cet objet dans ce contexte ? ». Il n'y a ni notion d'objet, ni de contexte organisationnel, ni de politique évaluée dynamiquement.

---

## Décision

1. Le mécanisme actuel est qualifié officiellement de **bootstrap administratif**, non de composante Access Control.
2. Toute nouvelle route ajoutée à l'Identity Registry ou à `Shared` avant l'implémentation du contexte Access Control (Masterplan Phase 2, sous-phase 6) doit utiliser exclusivement ce mécanisme de bootstrap — aucun mécanisme d'autorisation alternatif ne doit être introduit en parallèle.
3. Le code source de `ScopeAuthorizationMiddleware` et `EnvironmentAuthorizationService` doit porter un commentaire de bloc renvoyant explicitement à ADR-0011.
4. Ce mécanisme ne doit jamais être exposé au-delà de l'usage administratif interne (pas de token statique distribué à un produit métier).

---

## Conséquences

- Aucune régression : le comportement actuel ne change pas.
- Toute personne relisant le code sait immédiatement que ce n'est pas le modèle final.
- Le contexte Access Control, quand il sera construit, pourra remplacer ce mécanisme sans qu'aucune route n'ait pris une dépendance cachée sur son caractère provisoire.
