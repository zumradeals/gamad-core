# ADR-0013 — Frontière du noyau `Shared`

**Statut :** Accepté  
**Date :** 2026-07-13  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** ADR-0010 (Modular monolith first)

---

## Contexte

`Shared` porte déjà plus de code que l'Identity Registry (Outbox, Audit, Http, Sécurité, Observabilité, Metrics, Health). C'est légitime tant que ce contenu reste **technique et transversal**. Le risque, à l'arrivée des prochains bounded contexts, est que des concepts métier (ex. un statut propre à Organizations, une règle propre à Memberships) migrent dans `Shared` par facilité.

---

## Décision

1. `Shared` ne contient que des préoccupations techniques transversales : persistance, transport HTTP, messagerie, audit générique, sécurité générique, observabilité, métriques.
2. Aucune classe de `Shared` ne référence un concept propre à un bounded context (`Identity`, futur `Organization`, futur `Membership`, etc.).
3. Un test d'architecture (fitness function) vérifie automatiquement qu'aucun namespace sous `Gamad\Core\Shared\*` n'importe un namespace sous `Gamad\Core\IdentityRegistry\*` (ni, plus tard, tout autre bounded context). La dépendance ne s'autorise que dans le sens inverse.
4. Toute proposition d'ajout dans `Shared` doit répondre par écrit à la question : « cette classe aurait-elle un sens si `IdentityRegistry` n'existait pas ? ». Si non, elle n'appartient pas à `Shared`.

---

## Conséquences

- Empêche `Shared` de devenir un dépotoir.
- Rend l'extraction future d'un bounded context (si un jour justifiée par ADR) mécaniquement plus simple.
