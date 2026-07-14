# ADR-0018 — Authentification locale par mot de passe comme mécanisme souverain par défaut

**Statut :** Accepté  
**Date :** 2026-07-13  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-010 §D (décision différée), ADR-0011 (bootstrap administratif — hors périmètre ici)

---

## Contexte

GENESIS-010 laisse ouverte la question du mécanisme concret d'authentification pour les personnes (pas les opérateurs admin, déjà couverts par ADR-0011). Deux options existaient : construire une authentification locale (mot de passe), ou déléguer systématiquement à un fournisseur OIDC externe comme le fait déjà l'API d'administration.

Déléguer par défaut à un OIDC externe contredirait l'esprit souverain de GAMAD : ça ferait dépendre l'accès de chaque personne d'un tiers, avant même qu'une fédération réelle entre realms n'existe.

---

## Décision

1. Persons and User Accounts implémente une authentification locale par mot de passe comme mécanisme par défaut de ce realm — hachage Argon2id, jamais de mot de passe ou d'empreinte réversible stocké.
2. Le Core ne devient pas, à ce stade, un fournisseur OIDC complet vis-à-vis de tiers externes. Cette capacité reste hors périmètre tant qu'une fédération réelle (GENESIS-008 §5.2) n'est pas activée avec un second realm.
3. L'émission de session réutilise le style déjà établi pour les jetons administratifs (signature, expiration, révocation) sans dupliquer un nouveau formalisme cryptographique — mais reste un mécanisme distinct d'ADR-0011, car les personnes ne sont pas des opérateurs admin.
4. `AuthenticationMethod` est conçu dès le départ comme extensible à d'autres types (`oidc_external`, futur) sans que cela n'exige de réécrire `UserAccount` ou `Session`.

---

## Conséquences

- GAMAD reste maître de l'authentification de ses propres personnes, sans dépendance externe non justifiée.
- Une future ADR tranchera l'ouverture vers l'OIDC externe le jour où la fédération l'exigera réellement.
