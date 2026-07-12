# ADR-0010 — Modular monolith first

**Statut :** Accepté  
**Date :** 2026-07-12  
**Décideurs :** Orchestrateur de GAMAD et Architecte de GAMAD  
**Référence :** ARCHITECTURE-001 — Architecture de Référence du GAMAD Core

---

## Contexte

GAMAD Core doit porter plusieurs bounded contexts, des contrats stables, des exigences fortes d’audit, de sécurité et de continuité, tout en restant compréhensible et exploitable par une équipe encore réduite.

Une architecture distribuée en microservices dès le départ augmenterait immédiatement :

- le nombre de déploiements ;
- la complexité réseau ;
- les problèmes de cohérence ;
- les coûts d’observabilité ;
- les besoins d’orchestration ;
- les scénarios de panne ;
- la difficulté de reconstruction.

Cette complexité ne correspond pas encore à un besoin démontré.

Le modèle conceptuel a toutefois défini des bounded contexts et des contrats explicites qui doivent rester suffisamment nets pour permettre une extraction future.

---

## Décision

1. GAMAD Core commence comme un monolithe modulaire headless.
2. Les bounded contexts restent séparés par des frontières logiques, des contrats et des règles de dépendance.
3. Chaque contexte possède son domaine, son application layer, ses contrats, ses adaptateurs et ses migrations.
4. Les communications inter-contextes n’utilisent pas les modèles internes ou repositories privés d’un autre contexte.
5. Les événements internes sont publiés de manière fiable, notamment par Outbox transactionnelle lorsque nécessaire.
6. Les composants déployables initiaux peuvent être séparés par responsabilité opérationnelle : API, Worker, Scheduler et surface d’administration minimale.
7. Aucun bounded context ne devient un microservice sans contrainte démontrée et ADR dédiée.
8. Une extraction future doit préserver le langage, les contrats et les responsabilités du contexte.

---

## Conséquences positives

- Déploiement et exploitation plus simples.
- Transactions locales prévisibles.
- Coût d’infrastructure limité.
- Débogage et tests plus accessibles.
- Cohérence documentaire et logicielle plus facile à maintenir.
- Possibilité de distribuer ultérieurement les contextes réellement contraints.

---

## Contraintes

- Les équipes doivent respecter strictement les frontières malgré la proximité physique du code et de la base.
- Les jointures inter-contextes et appels directs aux repositories doivent être empêchés par convention, tests d’architecture et revue.
- Les migrations doivent rester attribuées à leur contexte.
- Le monolithe ne doit pas devenir un amas de dépendances croisées.

---

## Critères d’extraction future

Une extraction peut être proposée lorsque l’un des besoins suivants est prouvé :

- charge indépendante significative ;
- disponibilité ou isolation différente ;
- contrainte de sécurité particulière ;
- rythme de livraison autonome ;
- équipe propriétaire distincte ;
- technologie spécialisée indispensable ;
- couplage opérationnel devenu plus coûteux que la distribution.

La simple taille du code, une préférence personnelle ou un effet de mode ne constituent pas des critères suffisants.

---

## Options rejetées

### Microservices dès le départ

Rejetés en raison de la dette opérationnelle et distribuée créée avant validation des usages réels.

### Monolithe sans frontières

Rejeté, car il empêcherait l’évolution, la compréhension et l’extraction future.

### Services indépendants par agrégat

Rejetés, car un agrégat est une frontière de cohérence, pas nécessairement une unité de déploiement.

---

## Test de conformité

Une modification est conforme si :

- elle reste dans le bounded context propriétaire ;
- elle n’accède pas à l’intérieur d’un autre contexte ;
- elle respecte les contrats publics ;
- elle peut être testée indépendamment du framework lorsque la logique est métier ;
- elle ne crée pas de dépendance circulaire.

---

## Formule canonique

> Modularité logique d’abord. Distribution physique seulement lorsqu’une contrainte réelle l’exige.
