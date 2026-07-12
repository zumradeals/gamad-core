# ADR-0009 — Logical model precedes physical model

**Statut :** Accepté  
**Date :** 2026-07-11  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-007D — Projection logique et validation finale du modèle conceptuel

---

## Contexte

Le GAMAD Core doit pouvoir traverser plusieurs générations technologiques sans perdre sa cohérence. Si les tables, classes, endpoints ou conventions d’un framework définissent directement le modèle, l’architecture devient dépendante de l’implémentation du moment.

Cette dépendance produit plusieurs risques :

- le schéma de base devient le modèle métier implicite ;
- les contraintes du framework remplacent les responsabilités de domaine ;
- les API reproduisent les tables au lieu d’exposer des contrats ;
- les migrations techniques deviennent des migrations conceptuelles involontaires ;
- une réécriture future exige de redécouvrir les règles cachées dans le code.

Le modèle logique doit donc précéder et gouverner toute projection physique.

---

## Décision

1. Toute projection physique du GAMAD Core doit être dérivée d’un modèle logique validé.
2. Le modèle logique définit les concepts, responsabilités, relations, invariants, agrégats, bounded contexts et contrats.
3. Les tables, classes, services, endpoints, messages, index et fichiers de configuration sont des projections remplaçables.
4. Une contrainte technique locale ne peut modifier silencieusement le modèle logique.
5. Lorsqu’une implémentation révèle une faiblesse conceptuelle réelle, le modèle doit être amendé explicitement avant adaptation de la projection physique.
6. Aucune API publique ne doit être conçue avant son contrat logique.
7. Aucun schéma de persistance définitif ne doit être créé avant l’identification du propriétaire logique et de la frontière transactionnelle.
8. Le framework ne peut déterminer ni les bounded contexts ni les agrégats.
9. Toute divergence entre modèle logique et implémentation doit être documentée, temporaire et accompagnée d’un plan de résolution.
10. Les tests architecturaux devront vérifier l’alignement entre modèle logique et projection physique.

---

## Ordre obligatoire de dérivation

```text
Vision et principes
        ↓
Lexique canonique
        ↓
Concepts et relations
        ↓
Agrégats et invariants
        ↓
Bounded Contexts
        ↓
Contrats logiques
        ↓
Architecture de référence
        ↓
Projection physique
        ↓
Code et déploiement
```

---

## Interdictions

Il est interdit de :

- créer une table uniquement parce qu’un écran en a besoin ;
- exposer directement un modèle de persistance comme contrat public ;
- confondre modèle ORM et modèle de domaine ;
- créer un endpoint CRUD générique sans responsabilité contractuelle ;
- laisser le framework imposer les frontières du Core ;
- modifier un concept canonique pour contourner une difficulté technique locale ;
- considérer le code existant comme supérieur au modèle validé.

---

## Conséquences positives

- indépendance vis-à-vis des technologies ;
- reconstruction possible du Core à partir de ses contrats ;
- migrations plus contrôlées ;
- meilleure séparation entre métier et infrastructure ;
- API plus stables ;
- réduction de la dette conceptuelle ;
- capacité de remplacer une implémentation sans redéfinir GAMAD.

---

## Contraintes

- la conception initiale demande davantage de discipline ;
- les décisions physiques doivent référencer les concepts logiques ;
- certaines optimisations techniques nécessiteront des projections spécifiques ;
- les équipes doivent maintenir les documents et le code en cohérence ;
- une revue architecturale est requise lorsque l’implémentation semble contredire le modèle.

---

## Options rejetées

### Database-first

Rejetée comme méthode de définition du Core. Elle peut être utilisée ponctuellement pour explorer une projection, mais ne devient jamais source de vérité.

### Framework-first

Rejetée, car les conventions d’un outil ne doivent pas définir les responsabilités durables.

### API-first sans modèle de domaine

Rejetée lorsqu’elle produit des contrats sans propriétaire, invariants ou cycle de vie clairement définis.

### Code as documentation

Rejetée comme principe suffisant. Le code décrit une implémentation, pas nécessairement la vision, les choix et les frontières qui la justifient.

---

## Test de conformité

Toute proposition physique doit pouvoir répondre à ces questions :

1. Quel concept canonique implémente-t-elle ?
2. Quel bounded context en est propriétaire ?
3. Quel agrégat protège l’invariant concerné ?
4. Quel contrat expose-t-elle ou consomme-t-elle ?
5. Quelle partie est remplaçable sans modifier le modèle ?
6. Quelle ADR justifie le choix technique structurant ?

Une proposition incapable d’y répondre n’est pas prête à entrer dans le Core.

---

## Formule canonique

> La technologie implémente le modèle. Elle ne le définit jamais.
