# ADR-0005 — Planning precedes conceptual modeling

**Statut :** Accepté  
**Date :** 2026-07-11  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** MASTERPLAN-001 — Plan Directeur de l’Écosystème GAMAD

---

## Contexte

Le modèle conceptuel du GAMAD Core déterminera durablement les relations entre identités, organisations, acteurs, ressources, permissions, applications, modules, contrats et événements.

Construire ce modèle sans connaître l’ordre de développement de l’écosystème exposerait GAMAD à deux risques :

1. surdimensionner le Core pour des usages hypothétiques ;
2. sous-dimensionner ses fondations pour les produits réellement prioritaires.

La planification stratégique ne doit pas dicter les détails internes du modèle, mais elle doit fournir le contexte nécessaire pour distinguer les besoins présents, les extensions probables et les spéculations lointaines.

---

## Décision

1. Le Plan Directeur précède la publication du modèle conceptuel consolidé.
2. Le modèle conceptuel doit soutenir les phases officiellement retenues sans incorporer la logique métier des produits.
3. Les besoins futurs sont pris en compte par extensibilité contractuelle, non par généralisation prématurée.
4. Toute relation conceptuelle doit être justifiée par un invariant du Core ou par au moins un usage stratégique identifié.
5. Les produits pilotes servent à valider le modèle ; ils ne doivent pas le gouverner.
6. Une modification majeure de l’ordre stratégique exige une analyse d’impact sur le modèle conceptuel.

---

## Conséquences positives

- Le modèle conceptuel est relié à une trajectoire réelle.
- Les abstractions prématurées sont limitées.
- Les priorités de validation deviennent explicites.
- GAMAD Drive peut servir de laboratoire sans transformer le Core en Drive.
- Les branches futures restent possibles sans être encodées trop tôt.

---

## Contraintes

- Le Masterplan doit rester assez stable pour guider la conception.
- Le modèle conceptuel ne doit pas devenir une copie de la roadmap produit.
- Toute capacité future non prioritaire doit être traitée comme extension, sauf invariant démontré.

---

## Options rejetées

### Modélisation entièrement indépendante du plan

Rejetée, car elle favorise des abstractions sans preuve d’usage et rend difficile la priorisation des invariants.

### Modélisation dictée par le premier produit

Rejetée, car elle transformerait GAMAD Core en noyau spécialisé pour GAMAD Drive.

### Implémentation avant modèle

Rejetée conformément à la Constitution et à ADR-0004.

---

## Règle de contrôle

Toute composante du modèle conceptuel doit indiquer si elle répond :

- à un invariant transversal ;
- à une phase du Masterplan ;
- à une extension future explicitement identifiée.

Une abstraction sans justification doit être retirée ou reportée.

---

## Formule canonique

> La planification fournit le cap. Le modèle définit les fondations. Aucun des deux ne doit absorber l’autre.
