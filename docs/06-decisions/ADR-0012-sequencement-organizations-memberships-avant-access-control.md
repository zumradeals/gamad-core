# ADR-0012 — Séquencement Organizations/Memberships avant Access Control

**Statut :** Accepté  
**Date :** 2026-07-13  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** MASTERPLAN-001 §4, Phase 2

---

## Contexte

Le Masterplan place Organizations (sous-phase 3) et Memberships (sous-phase 4) avant Access Control (sous-phase 6). Or Organizations et Memberships exposeront nécessairement des routes d'écriture. Sans le contexte Access Control, ces routes ne peuvent être protégées que par le bootstrap administratif d'ADR-0011.

---

## Décision

1. Le séquencement du Masterplan est confirmé : Organizations et Memberships peuvent être construits avant Access Control.
2. Toutes les routes d'écriture d'Organizations et de Memberships utiliseront le bootstrap administratif d'ADR-0011 jusqu'à la livraison d'Access Control.
3. Chaque route ainsi protégée doit être recensée dans un registre `docs/06-decisions/REGISTRE-BOOTSTRAP.md` (à créer), afin qu'aucune ne soit oubliée lors du remplacement par le vrai Access Control.
4. Ce registre est un livrable obligatoire de toute sous-phase livrée avant la sous-phase 6.

---

## Conséquences

- Le rythme du Masterplan n'est pas modifié.
- La dette d'autorisation provisoire est traçable et bornée, elle ne s'accumule pas silencieusement.
