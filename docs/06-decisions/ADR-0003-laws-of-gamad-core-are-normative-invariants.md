# ADR-0003 — The Laws of GAMAD Core are normative invariants

**Statut :** Accepté  
**Date :** 2026-07-11  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-005 — Les Lois du GAMAD Core

---

## Contexte

Le GAMAD Core doit évoluer pendant plusieurs générations technologiques et accueillir des applications, modules, équipes et secteurs différents.

Sans règles normatives communes, les décisions locales peuvent progressivement affaiblir les frontières du Core, contourner les contrats, mélanger les responsabilités ou transformer des exceptions temporaires en architecture permanente.

Un ensemble de principes purement informatifs serait insuffisant. Les règles structurantes doivent être vérifiables et opposables aux conceptions, aux contrats et aux implémentations.

---

## Décision

1. Le document GENESIS-005 définit les Lois normatives du GAMAD Core.
2. Une Loi exprime un Invariant ou une contrainte architecturale fondamentale.
3. Toute conception, spécification, API, migration et implémentation du Core doit respecter les Lois applicables.
4. Une fonctionnalité qui viole une Loi doit être refusée, repensée ou déplacée hors du Core.
5. Une exception temporaire doit être documentée, isolée, limitée et accompagnée d’une condition de sortie.
6. Les Lois doivent être traduites progressivement en mécanismes de contrôle : tests, contraintes, revues, linters de contrats, politiques de sécurité et vérifications CI.
7. Toute modification d’une Loi exige une ADR dédiée, une analyse d’impact, une nouvelle version de GENESIS-005 et un plan de migration.
8. Une implémentation existante ne peut pas redéfinir rétroactivement une Loi.

---

## Hiérarchie normative retenue

En cas de contradiction :

1. Livre Blanc de GAMAD ;
2. Charte Fondatrice de GAMAD ;
3. GAMAD Core Charter ;
4. Lois du GAMAD Core ;
5. Lexique Canonique ;
6. ADR acceptées ;
7. Contracts versionnés ;
8. Spécifications ;
9. Implémentation.

Cette hiérarchie ne signifie pas que les documents supérieurs contiennent davantage de détails. Elle indique quelle source doit guider la correction en cas de conflit.

---

## Conséquences positives

- Protection du Core contre l’érosion architecturale.
- Arbitrage clair des propositions futures.
- Cohérence entre documents, modèles, contrats et code.
- Possibilité d’automatiser progressivement des contrôles de conformité.
- Conservation d’une mémoire explicite des amendements.
- Réduction de la dépendance à la mémoire des personnes.

---

## Contraintes

- Certaines fonctionnalités devront être repensées avant développement.
- Les revues architecturales devront référencer les Lois concernées.
- Les exceptions demanderont une documentation explicite.
- La CI et les tests devront progressivement intégrer des contrôles dérivés des Lois.

---

## Options rejetées

### Principes non contraignants

Rejetés, car ils pourraient être ignorés sous pression commerciale ou technique.

### Validation uniquement humaine

Rejetée comme mécanisme unique. Les revues humaines restent nécessaires, mais les Lois vérifiables doivent aussi être traduites en contrôles automatisés.

### Lois figées sans amendement possible

Rejetées, car une architecture durable doit pouvoir évoluer. L’évolution est permise, mais elle doit être explicite, versionnée et accompagnée d’une migration.

---

## Règle de contrôle

Toute proposition architecturale importante doit inclure une section :

> Conformité aux Lois du GAMAD Core

Cette section doit identifier :

- les Lois renforcées ;
- les Lois potentiellement affectées ;
- les mécanismes de vérification ;
- les éventuelles exceptions temporaires.

---

## Formule canonique

> Les Lois n’empêchent pas GAMAD d’évoluer. Elles empêchent son évolution de détruire sa cohérence.
