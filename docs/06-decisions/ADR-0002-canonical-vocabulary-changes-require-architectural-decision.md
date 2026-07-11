# ADR-0002 — Canonical vocabulary changes require architectural decision

**Statut :** Accepté  
**Date :** 2026-07-11  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-004 — Lexique Canonique du GAMAD Core

---

## Contexte

GAMAD est destiné à accueillir de nombreux modules, applications, équipes et métiers. Sans vocabulaire commun, les mêmes mots pourraient recevoir des significations différentes selon les systèmes.

Une ambiguïté documentaire finit généralement par devenir une ambiguïté de données, d’API, de permissions ou de responsabilités. Elle produit ensuite de la dette architecturale et rend les migrations difficiles.

Le vocabulaire du Core doit donc être gouverné avec la même rigueur que ses contrats.

---

## Décision

1. Le Lexique Canonique constitue la référence normative des concepts du GAMAD Core.
2. Aucun concept canonique nouveau ne peut être introduit directement dans le code, une API, un événement ou un cahier des charges.
3. Toute création, redéfinition, fusion ou suppression d’un concept canonique exige une ADR.
4. L’ADR doit démontrer que le besoin n’est pas déjà couvert par un concept existant.
5. Le concept proposé doit préciser sa définition, son rôle, ses inclusions, ses exclusions, ses relations, un exemple et un contre-exemple.
6. Les concepts métier spécialisés restent dans leurs modules et ne deviennent pas automatiquement canoniques.
7. Les synonymes d’interface peuvent exister, mais les contrats et documents d’architecture utilisent les termes canoniques.
8. Le Lexique doit être mis à jour avant l’utilisation normative du nouveau concept.

---

## Conséquences positives

- Cohérence entre documents, code, API et modèles de données.
- Réduction des concepts doublons.
- Meilleure compréhension par les nouvelles équipes.
- Évolution contrôlée du langage de GAMAD.
- Possibilité de reconstruire les systèmes à partir de définitions stables.

---

## Contraintes

- La création d’un nouveau terme structurel demande un effort de justification.
- Certaines équipes devront renommer des concepts locaux pour respecter le Canon.
- Les traductions d’interface devront rester reliées au terme canonique d’origine.

---

## Options rejetées

### Vocabulaire libre par module

Rejeté, car il crée des incompatibilités et des définitions concurrentes.

### Glossaire purement informatif

Rejeté, car une référence non normative ne protège pas les contrats et modèles.

### Validation après implémentation

Rejetée, car le coût de correction augmente une fois le concept encodé dans les données et API.

---

## Règle de contrôle

Lors d’une revue architecturale, tout terme non canonique utilisé comme concept transversal doit être soit remplacé, soit justifié par une ADR en attente.

---

## Formule canonique

> Une architecture durable commence par des mots stables.
