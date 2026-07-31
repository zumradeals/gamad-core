# Catalogue initial des capacités GAMAD Core

Ce catalogue sert de carte de travail. Les statuts indiquent seulement ce qui doit être vérifié ou migré ; ils ne remplacent pas l’audit du code.

| Référence | Capacité | Finalité | État documentaire de départ |
|---|---|---|---|
| CAP-CORE-001 | Identity Registry | Reconnaître les identités canoniques et leurs relations minimales | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-002 | Organizations Registry | Gérer les organisations et leur structure commune | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-003 | Authorities & Mandates | Résoudre les fonctions, titulaires, mandats et délégations | Dépendances historiques identifiées — `HÉRITÉ À MIGRER` |
| CAP-CORE-004 | Authorization | Décider si une action commune est permise ou refusée | Politique encore dérivée du corpus — `HÉRITÉ À MIGRER` |
| CAP-CORE-005 | Authentication & Access | Authentifier, ouvrir et révoquer les sessions | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-006 | Sources Registry | Identifier les sources techniques reconnues | Parseur documentaire historique — `HÉRITÉ À MIGRER` |
| CAP-CORE-007 | Rules / Policies Registry | Gérer les politiques techniques versionnées | Ancien registre normatif — `HÉRITÉ À REDÉFINIR` |
| CAP-CORE-008 | Decisions Registry | Tracer les décisions opérationnelles utiles | Ancien registre lié aux actes — `HÉRITÉ À REDÉFINIR` |
| CAP-CORE-009 | Contracts Registry | Décrire et versionner les contrats intercapacités | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-010 | Canonical Vocabulary | Partager des termes et codes stables entre produits | Ancien lexique normatif — `HÉRITÉ À REDÉFINIR` |
| CAP-CORE-011 | Products Registry | Référencer les satellites, services et points d’entrée | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-012 | Realms Registry | Isoler les périmètres techniques et institutionnels | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-013 | Common Audit | Conserver les traces transversales autorisées | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-014 | Event Journal | Publier et consommer les événements communs | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-015 | Integrity Proofs | Vérifier les empreintes et preuves techniques | Anciennes preuves documentaires dominantes — `HÉRITÉ À MIGRER` |
| CAP-CORE-016 | Secrets & Keys | Gérer les références, rotations et usages des secrets | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-017 | Risks & Exceptions | Enregistrer et suivre les risques et exceptions techniques | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-018 | Incidents | Déclarer, suivre et clôturer les incidents | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-019 | Backup & Restore | Sauvegarder, restaurer et prouver la continuité | Module historique présent — `À VÉRIFIER` |
| CAP-CORE-020 | Directory & Atlas | Produire un annuaire opérationnel des capacités et produits | Fortement dérivé de Genesis II — `HÉRITÉ À MIGRER` |
| CAP-CORE-021 | Matching Engine | Produire des correspondances contextualisées entre besoins, offres et signaux | Conception existante, moteur complet non prouvé — `PARTIEL OU ABSENT À VÉRIFIER` |
| CAP-CORE-022 | Satellite Federation | Relier le Compte GAMAD, le Portail et les comptes produit locaux | Capacité cible — `À CONCEVOIR ET IMPLÉMENTER` |

## Regroupement fonctionnel

### Identité et accès

- CAP-CORE-001 — Identity Registry
- CAP-CORE-002 — Organizations Registry
- CAP-CORE-003 — Authorities & Mandates
- CAP-CORE-004 — Authorization
- CAP-CORE-005 — Authentication & Access

### Référentiels et contrats

- CAP-CORE-006 — Sources Registry
- CAP-CORE-007 — Rules / Policies Registry
- CAP-CORE-008 — Decisions Registry
- CAP-CORE-009 — Contracts Registry
- CAP-CORE-010 — Canonical Vocabulary
- CAP-CORE-011 — Products Registry
- CAP-CORE-012 — Realms Registry

### Traçabilité et sécurité

- CAP-CORE-013 — Common Audit
- CAP-CORE-014 — Event Journal
- CAP-CORE-015 — Integrity Proofs
- CAP-CORE-016 — Secrets & Keys
- CAP-CORE-017 — Risks & Exceptions
- CAP-CORE-018 — Incidents
- CAP-CORE-019 — Backup & Restore

### Écosystème

- CAP-CORE-020 — Directory & Atlas
- CAP-CORE-021 — Matching Engine
- CAP-CORE-022 — Satellite Federation

## Règles de migration

Pour chaque capacité :

1. inventorier les contrats et consommateurs réels ;
2. identifier les lectures directes de `genesis-ii/` ;
3. distinguer la fonction utile de l’ancien habillage normatif ;
4. créer une fiche individuelle fondée sur le code ;
5. migrer les données nécessaires vers une source technique ;
6. conserver ou améliorer les gardes de comportement ;
7. supprimer seulement les composants devenus réellement inutiles.

## Priorité proposée

La première migration technique doit cibler le chemin critique utilisé par la console et l’API :

```text
CAP-CORE-001 identité
→ CAP-CORE-003 mandats
→ CAP-CORE-004 autorisation
→ CAP-CORE-005 authentification
→ CAP-CORE-011 produits
→ CAP-CORE-022 fédération
```

Cette proposition doit être confirmée après un audit précis des dépendances et des tests du dépôt.