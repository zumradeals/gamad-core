# Moteur de Matching (CAP-CORE-021)

Moteur déterministe, explicable et multi-consommateurs de qualification et d'appariement du GAMAD Core.

Voir le chantier complet : `docs/capacites/chantiers/CAP-CORE-021/` (5 parties) et les sources fondatrices `docs/03-matching-transversal.md` et `GAMAD_Core_Capacite_Matching_Note_Fondatrice_v0.1 (1).md` (conceptuelle, non normative).

Décisions de lancement du dirigeant (2026-08-05) : voir mémoire `projet-cap-core-021-matching-decisions-go` — deux prérequis de la fiche de chantier ont été explicitement écartés (l'ordre imposant 008/017/018/020 avant 021, et l'exigence nommée de Wasplex comme pilote). Le moteur est construit complet et prêt pour la production sans attendre qu'un deuxième consommateur réel soit branché ; le verdict `GO`/`NO GO` final reste honnête sur ce point.

## Fichiers

- `src/Magasin.php` — connexion au magasin persistant (`MATCHING_REGISTRY_URL` / `MATCHING_REGISTRY_PATH`).
- `src/SchemaMatching.php` — migration additive des tables (doc 02), réutilise l'outbox partagé `Gamad\EvenementsSortants\SchemaOutbox` pour la publication transactionnelle vers CAP-CORE-014 (pas de table `matching_outbox` bespoke).
- `tests/matching_p3.php` — garde de comportement (à venir).

## Ce que ce module NE possède PAS

L'identité canonique (CAP-CORE-001), le dossier d'organisation (CAP-CORE-002), les mandats (CAP-CORE-003), les décisions d'autorisation (CAP-CORE-004), les sources (CAP-CORE-006), les politiques (CAP-CORE-007 — le Matching compile une politique active en plan d'exécution, il n'en détient jamais une version souveraine concurrente), les contrats (CAP-CORE-009), les codes canoniques (CAP-CORE-010), les produits (CAP-CORE-011), aucune clé privée, aucun modèle d'apprentissage automatique, aucune lecture directe d'un autre magasin.

## Invariants imposés au niveau schéma

- `matching_segment.export_brut_autorise` est contraint à `0` : aucun export brut de membres dans ce premier périmètre (doc 01 §4, doc 04 §6).
- `matching_resultat.non_decisionnel` est contraint à `1` : le moteur ne produit jamais une décision finale automatique (doc 02 §15).
- toute table de cycle (`matching_cycle`) est en ajout seul.

## Exécuter la garde

```bash
php core/moteur-matching/tests/matching_p3.php
```
