# Catalogue initial des capacités GAMAD Core

Ce catalogue est la carte de travail du Core. Les états sont établis par
inspection du code, des tests et de la CI ; ils ne sont jamais déduits d’un
document.

| Référence | Capacité | Finalité | Code livré | État réel |
|---|---|---|---|---|
| CAP-CORE-001 | Identity Registry | Reconnaître les identités canoniques et leurs relations minimales | `core/registre-identites`, console et API v1 | `IMPLÉMENTÉ` — garde et intégrations vertes |
| CAP-CORE-002 | Organizations Registry | Gérer les organisations et leur structure commune | aucun | `ABSENT` — l’ancien module ne lisait qu’un texte |
| CAP-CORE-003 | Authorities & Mandates | Résoudre les fonctions, titulaires, mandats et délégations | `core/registre-autorites` | `IMPLÉMENTÉ` — résolution datée éprouvée |
| CAP-CORE-004 | Authorization | Décider si une action commune est permise ou refusée | `core/registre-autorisation` | `IMPLÉMENTÉ` — refus par défaut éprouvé |
| CAP-CORE-005 | Authentication & Access | Authentifier, ouvrir et révoquer les sessions | `core/registre-acces`, passkeys, écran Mon accès | `IMPLÉMENTÉ` — sessions, révocation, WebAuthn, codes de secours à usage unique et garde du dernier moyen d’accès, tous éprouvés |
| CAP-CORE-006 | Sources Registry | Identifier les sources techniques reconnues | `core/registre-sources` | `PARTIEL` — lecture de l’index seulement |
| CAP-CORE-007 | Rules / Policies Registry | Gérer les politiques techniques versionnées | `core/registre-normes` | `PARTIEL` — lecture et diagnostic ; aucune écriture gouvernée |
| CAP-CORE-008 | Decisions Registry | Tracer les décisions opérationnelles utiles | aucun ; traces dans le journal opérationnel | `ABSENT` |
| CAP-CORE-009 | Contracts Registry | Décrire et versionner les contrats intercapacités | aucun ; contrats portés par le code et `openapi/core-v1.yaml` | `ABSENT` |
| CAP-CORE-010 | Canonical Vocabulary | Partager des termes et codes stables entre produits | aucun | `ABSENT` |
| CAP-CORE-011 | Products Registry | Référencer les satellites, services et points d’entrée | données techniques dans l’index, catalogue servi par `core/registre-federation` | `PARTIEL` — quatre produits en données, lecture et distinction du fédérable ; aucune écriture gouvernée |
| CAP-CORE-012 | Realms Registry | Isoler les périmètres techniques et institutionnels | aucun | `ABSENT` |
| CAP-CORE-013 | Common Audit | Conserver les traces transversales autorisées | `core/journal-operationnel` | `IMPLÉMENTÉ` — chaîne append-only vérifiée, trigger PostgreSQL |
| CAP-CORE-014 | Event Journal | Publier et consommer les événements communs | `core/journal-operationnel` en écriture seule | `PARTIEL` — aucune publication vers les satellites |
| CAP-CORE-015 | Integrity Proofs | Vérifier les empreintes et preuves techniques | empreinte de baseline, chaîne du journal | `PARTIEL` — pas de service d’empreintes général |
| CAP-CORE-016 | Secrets & Keys | Gérer les références, rotations et usages des secrets | aucun ; secrets hors dépôt | `ABSENT` |
| CAP-CORE-017 | Risks & Exceptions | Enregistrer et suivre les risques et exceptions techniques | aucun | `ABSENT` |
| CAP-CORE-018 | Incidents | Déclarer, suivre et clôturer les incidents | aucun | `ABSENT` |
| CAP-CORE-019 | Backup & Restore | Sauvegarder, restaurer et prouver la continuité | `ops/core-foundation`, écran Continuité | `EXPLOITÉ` — copie chiffrée hors machine sur destination réelle, TLS épinglé, et exercice de restauration complet exécuté depuis cette copie le 1er août 2026 |
| CAP-CORE-020 | Directory & Atlas | Produire un annuaire opérationnel des capacités et produits | aucun | `ABSENT` — l’ancien module dérivait d’un corpus supprimé |
| CAP-CORE-021 | Matching Engine | Produire des correspondances contextualisées entre besoins, offres et signaux | aucun | `ABSENT` |
| CAP-CORE-022 | Satellite Federation | Relier le Compte GAMAD, le Portail et les comptes produit locaux | `core/registre-federation`, API v1 `/produits*` | `IMPLÉMENTÉ` — parcours pilote GamaDrive éprouvé ; aucun satellite réel raccordé |

Un état `ABSENT` est une information utile : il nomme un chantier ouvert plutôt
qu’il ne dissimule un manque derrière un module qui ne rendait aucun service.

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

## Règles de construction

Pour chaque capacité :

1. inventorier les contrats et consommateurs réels ;
2. définir la responsabilité et la frontière Core / satellite ;
3. créer une fiche individuelle fondée sur le code ;
4. porter les données nécessaires dans une source technique versionnée ;
5. écrire une garde de comportement avec sa contre-épreuve ;
6. raccorder la garde à `core-operational-tests.yml` ;
7. mettre l’état de ce catalogue à jour d’après le résultat réel.

## Priorité proposée

Le chemin critique est livré. La première tranche de CAP-CORE-022 l’est aussi :
le Core sait ouvrir une identité sur GamaDrive, borner le jeton, le révoquer et
le prouver. Les chantiers ouverts, par ordre d’utilité :

```text
intégration réelle de GamaDrive V2 sur CAP-CORE-022
→ CAP-CORE-011 registre des produits, en écriture gouvernée
→ CAP-CORE-002 organisations
→ CAP-CORE-014 publication d’événements vers les satellites
→ CAP-CORE-021 Matching
```

Le second chemin d’accès est livré : codes de secours à usage unique,
attachement d’une passkey sans ligne de commande, et refus de retirer le
dernier moyen d’accès durable. Reste à l’exercer — engendrer les codes depuis
l’écran « Mon accès » et enrôler une passkey. Tant que ce n’est pas fait, le
magasin d’accès ne contient toujours qu’un seul authentificateur, constaté le
1er août 2026 par l’exercice de restauration.

Cette proposition reste une proposition : elle se confirme produit par produit.
Tant qu’aucun satellite ne consomme la fédération, elle est `IMPLÉMENTÉE`, pas
`EXPLOITÉE`.

La continuité est passée devant le reste, et elle est faite. Le 1er août 2026,
l’inspection du serveur montrait huit lots de sauvegarde, tous sur le disque
qu’ils protégeaient, et aucune copie ailleurs. Le même jour, une copie chiffrée
est partie vers une destination distincte, en a été récupérée, et a été
rechargée sur quatre bases isolées — sept identités dérivées, un
authentificateur, vingt-deux événements relus. C’est cette exécution, et non le
code qui la permet, qui fait passer CAP-CORE-019 à `EXPLOITÉ`.