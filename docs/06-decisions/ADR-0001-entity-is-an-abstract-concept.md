# ADR-0001 — Entity is an abstract concept, not a universal domain model

**Statut :** Accepté  
**Date :** 2026-07-11  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** GENESIS-003 — GAMAD Core Charter

---

## Contexte

GAMAD Core doit reconnaître des réalités très différentes : personnes, organisations, applications, services, appareils, ressources, projets et futures catégories d’objets.

Une modélisation apparemment simple consisterait à créer un domaine universel `Entity`, accompagné d’une table, d’un service et d’une API génériques capables de représenter tous les objets du système.

Cette approche présente toutefois un risque majeur : l’objet universel accumulerait progressivement des responsabilités hétérogènes, des statuts, des relations, des métadonnées, des permissions et des cycles de vie propres à des domaines différents. Il deviendrait un objet central sans métier clair et finirait par effacer les frontières entre les domaines spécialisés.

---

## Décision

1. `Entity` est un concept canonique abstrait du glossaire GAMAD.
2. Une entité désigne toute réalité humaine, organisationnelle, numérique ou technique reconnue par GAMAD et susceptible de recevoir une identité persistante.
3. `Entity` ne devient pas un domaine métier universel.
4. Le Core ne crée pas de modèle parent obligatoire chargé de contenir toutes les données des entités.
5. Le Core ne crée pas d’API CRUD générique universelle `/entities` destinée à remplacer les domaines spécialisés.
6. L’Identity Registry maintient la continuité identitaire et les références communes.
7. Les domaines spécialisés restent responsables de leur sens, de leur cycle de vie métier et de leurs données propres.
8. Identity, User Account, Authentication et Actor restent des concepts distincts.

---

## Modèle retenu

```text
Entité reconnue
      │
      ▼
Identity Registry
identité persistante commune
      │
      ├── Person domain
      ├── Organization domain
      ├── Application domain
      ├── Resource domain
      ├── Device domain
      └── autres domaines spécialisés
```

L’Identity Registry répond à la question :

> Quelle entité est reconnue, quelle identité persistante lui a été attribuée et quel est son état identitaire ?

Le domaine spécialisé répond à la question :

> Quelle est la fonction, la structure et la vie métier de cette entité ?

---

## Conséquences positives

- Un langage commun sans table universelle.
- Une identité transversale sans monolithe conceptuel.
- Des domaines spécialisés compréhensibles et autonomes.
- Une meilleure séparation entre identité et données métier.
- Une réduction du risque de God Object.
- Une évolution plus sûre vers de nouvelles catégories d’entités.

---

## Conséquences et contraintes

- Les relations entre identités et domaines spécialisés devront être explicites.
- Les catégories identitaires devront être gouvernées et versionnées.
- Les équipes ne pourront pas contourner les domaines spécialisés par une API générique pratique mais ambiguë.
- Certaines opérations transversales demanderont une orchestration entre l’Identity Registry et le domaine concerné.

---

## Options rejetées

### Option A — Table universelle `entities`

Rejetée comme modèle métier global en raison du risque d’accumulation de responsabilités et de dilution des frontières.

### Option B — Identités totalement séparées par domaine

Rejetée, car elle empêcherait une continuité identitaire commune et rendrait plus difficile l’audit transversal, la gouvernance et les relations inter-domaines.

### Option C — Identity Registry comme propriétaire de toutes les données

Rejetée, car l’identité ne doit pas absorber les données métier des personnes, organisations, applications ou ressources.

---

## Règle de contrôle

Toute proposition future introduisant un modèle générique d’entité devra démontrer qu’elle ne remplace pas les domaines spécialisés et qu’elle ne transforme pas `Entity` en objet universel chargé de représenter toute la vie du système.

---

## Formule canonique

> L’entité existe. L’identité permet à GAMAD de la reconnaître. Le domaine spécialisé permet à GAMAD de comprendre sa fonction.
