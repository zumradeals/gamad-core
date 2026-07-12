# ADR-0007 — Aggregates protect invariants, not object collections

**Statut :** Accepté  
**Date :** 2026-07-12  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-007B — Agrégats et frontières transactionnelles du GAMAD Core

---

## Contexte

Les agrégats sont souvent modélisés comme de simples regroupements d’objets proches, des dossiers de code ou des graphes de données pratiques à charger ensemble.

Cette interprétation produit généralement des agrégats trop grands, des transactions couvrant plusieurs responsabilités, des dépendances circulaires et des invariants dont personne n’est clairement propriétaire.

GAMAD Core doit préserver des vérités transversales durables : unicité identitaire, responsabilité des ressources, validité des appartenances, cohérence des capacités, intégrité des contrats et traçabilité des actions.

Ces vérités doivent déterminer les frontières transactionnelles.

---

## Décision

1. Un agrégat existe pour protéger un ou plusieurs invariants transactionnels clairement identifiés.
2. La racine d’agrégat est le seul point d’entrée autorisé pour modifier l’état interne de l’agrégat.
3. La taille d’un agrégat est déterminée par la cohérence immédiate requise, jamais par la structure de l’interface ou du code.
4. Une transaction métier ne modifie directement qu’un seul agrégat.
5. Un agrégat référence les autres agrégats par identité et ne dépend pas de leur état interne chargé.
6. La coordination entre agrégats passe par Commands, Events, Contracts ou Process Managers explicites.
7. Les transactions distribuées implicites sont interdites.
8. Toute modification de frontière d’agrégat exige une décision architecturale lorsqu’elle déplace un invariant ou change le System of Record.
9. Les événements produits par une modification doivent être persistés de manière fiable avec la transaction locale.
10. Un agrégat doit rester aussi petit que possible, mais suffisamment large pour garantir son invariant.

---

## Conséquences positives

- Frontières transactionnelles compréhensibles.
- Invariants clairement attribués.
- Réduction des agrégats géants et des graphes couplés.
- Meilleure gestion de la concurrence.
- Coordination inter-domaines explicite et auditée.
- Possibilité d’évolution vers plusieurs services sans redessiner le métier.
- Tests métier plus ciblés.

---

## Contraintes

- Les processus multi-agrégats deviennent éventuellement cohérents.
- Des mécanismes de publication fiable des événements sont nécessaires.
- Les opérations distribuées doivent prévoir idempotence, reprise et compensation.
- Certaines interfaces devront composer plusieurs vues au lieu de charger un unique modèle central.
- Les développeurs doivent distinguer agrégat, module, bounded context et table.

---

## Options rejetées

### Agrégat par écran

Rejeté, car une interface ne définit pas une frontière de cohérence métier.

### Agrégat par table principale

Rejeté, car la persistance ne doit pas dicter le modèle conceptuel.

### Grand agrégat GAMAD Core

Rejeté, car il créerait une transaction globale, un couplage extrême et un point de contention permanent.

### Transaction distribuée couvrant plusieurs agrégats

Rejetée comme modèle normal, car elle masque les responsabilités et fragilise la résilience.

---

## Test de conformité

Toute proposition d’agrégat doit répondre :

1. Quel invariant protège-t-elle ?
2. Pourquoi cet invariant exige-t-il une cohérence immédiate ?
3. Quelle est la racine d’agrégat ?
4. Quelles données doivent rester hors de sa frontière ?
5. Quels autres agrégats sont référencés uniquement par identité ?
6. Quel mécanisme coordonne les changements externes ?
7. Comment les conflits de concurrence sont-ils détectés ?

Une proposition incapable de répondre précisément à ces questions n’est pas prête à être implémentée.

---

## Formule canonique

> Un agrégat n’existe pas pour regrouper des objets. Il existe pour protéger une vérité.
