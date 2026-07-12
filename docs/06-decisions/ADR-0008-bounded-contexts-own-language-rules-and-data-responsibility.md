# ADR-0008 — Bounded contexts own language, rules and data responsibility

**Statut :** Accepté  
**Date :** 2026-07-12  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-007C — Bounded Contexts et responsabilités de domaine du GAMAD Core

---

## Contexte

GAMAD Core regroupe plusieurs responsabilités transversales : identité, organisations, accès, ressources, capacités, contrats, événements et audit.

Sans frontières explicites, ces responsabilités risquent de partager les mêmes modèles internes, de se modifier directement ou d’attribuer des sens concurrents aux mêmes termes. Le Core deviendrait alors un monolithe conceptuel difficile à maintenir, même si son code restait organisé en dossiers.

Il faut distinguer une simple organisation technique d’une véritable frontière de domaine.

---

## Décision

1. GAMAD Core est organisé en Bounded Contexts explicites.
2. Chaque Bounded Context possède :
   - son langage local ;
   - ses invariants ;
   - ses agrégats ;
   - ses données sous responsabilité ;
   - ses contrats exposés ;
   - ses dépendances autorisées ;
   - son cycle d’évolution.
3. Un concept transverse possède un seul contexte propriétaire.
4. Les autres contextes utilisent une référence, une projection ou une traduction contractuelle.
5. Aucun contexte ne lit ni ne modifie directement la persistance interne d’un autre.
6. Les modèles de domaine ne sont pas partagés comme objets communs entre contextes.
7. Les échanges inter-contextes passent par Command, Query, Event ou Contract versionnés.
8. Les Anti-Corruption Layers protègent le Canon face aux systèmes historiques ou externes.
9. Les contextes sont d’abord logiques dans un monolithe modulaire ; ils ne deviennent des services indépendants que sur justification documentée.
10. Toute séparation physique future doit préserver les frontières conceptuelles définies ici.

---

## Conséquences positives

- Propriété claire des concepts et données.
- Réduction des dépendances cachées.
- Langages locaux plus précis.
- Évolution indépendante des responsabilités.
- Meilleure préparation à une éventuelle distribution physique.
- Intégration des systèmes historiques sans contamination du Canon.
- Tests d’architecture possibles sur les dépendances.

---

## Contraintes

- Les contrats doivent être conçus et versionnés avec discipline.
- Les projections de données doivent préciser leur fraîcheur et leur autorité.
- Certaines opérations deviennent éventuellement cohérentes plutôt qu’atomiques.
- Les équipes doivent traduire explicitement les modèles au lieu de partager des entités internes.
- Les dépendances entre contextes doivent être revues régulièrement.

---

## Options rejetées

### Modèle de domaine partagé global

Rejeté, car il donnerait le même modèle à tous les contextes et créerait un couplage structurel.

### Découpage par couches techniques uniquement

Rejeté, car Controllers, Services et Repositories ne définissent pas les responsabilités métier.

### Microservices immédiats

Rejetés, car la distribution physique prématurée ajoute de la complexité sans garantir de bonnes frontières.

### Base de données commune accessible librement

Rejetée, car une base partagée ne doit pas supprimer la propriété des données par contexte.

---

## Test de conformité

Pour toute nouvelle dépendance entre contextes, la revue doit vérifier :

1. Quel contexte possède le concept échangé ?
2. Quel contrat est utilisé ?
3. La donnée est-elle une référence, une projection ou une copie ?
4. Quelle est sa source de vérité ?
5. Comment la compatibilité est-elle assurée ?
6. Comment les erreurs et retards sont-ils traités ?
7. Une Anti-Corruption Layer est-elle nécessaire ?
8. La dépendance crée-t-elle un cycle ?

Une dépendance sans réponses explicites n’est pas conforme.

---

## Formule canonique

> Un contexte possède son langage, ses règles et sa responsabilité. Il coopère par contrat sans exposer son intérieur.
