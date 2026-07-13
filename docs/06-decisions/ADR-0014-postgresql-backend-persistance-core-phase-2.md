# ADR-0014 — PostgreSQL comme backend de persistance du Core (Phase 2)

**Statut :** Accepté  
**Date :** 2026-07-13  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-003 §12 (Neutralité technologique), ADR-0010

---

## Contexte

Toutes les implémentations d'infrastructure actuelles (`PostgreSqlIdentityRepository`, `PostgreSqlOutboxRepository`, `PostgreSqlAdministrativeAuditRepository`, etc.) dépendent de PostgreSQL. La Charter proclame la neutralité technologique en principe (§12), mais aucune décision écrite n'acte ce choix concret ni ne documente qu'il est remplaçable.

---

## Décision

1. PostgreSQL 17 est le backend de persistance retenu pour toute la Phase 2 du Core (les 13 sous-phases de GENESIS-003 §5).
2. Ce choix est une décision de Niveau 3 (implémentation), au sens de GENESIS-003 §14 — remplaçable sans changer les contrats ni les invariants.
3. Chaque bounded context expose ses interfaces de dépôt (`*Repository`) dans sa couche Domain, indépendamment de PostgreSQL — seule l'implémentation `Infrastructure/Persistence` en dépend. C'est déjà le cas pour l'Identity Registry ; cette règle devient obligatoire pour tout contexte futur.
4. Un changement de backend ne justifie pas de modifier une interface de dépôt existante sans ADR dédiée.

---

## Conséquences

- Documente formellement un choix déjà de fait, sans surprise pour un futur lecteur.
- Confirme que le remplacement reste possible sans réécrire les couches Domain/Application.
