# Catalogue des capacités GAMAD Core

Une capacité est une aptitude durable du Core. Sa fiche décrit la cible ; le code et les tests établissent la réalité.

| Référence | Capacité | État initial après simplification |
|---|---|---|
| `CAP-CORE-001` | [Identity Registry](CAP-CORE-001.md) | `OPÉRATIONNEL LIMITÉ` |
| `CAP-CORE-002` | [Registre des organisations](CAP-CORE-002.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-003` | [Autorités et mandats](CAP-CORE-003.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-004` | [Autorisation commune](CAP-CORE-004.md) | `OPÉRATIONNEL LIMITÉ` |
| `CAP-CORE-005` | [Authentification et assurance](CAP-CORE-005.md) | `OPÉRATIONNEL LIMITÉ` |
| `CAP-CORE-006` | [Registre des sources](CAP-CORE-006.md) | `HÉRITÉ À MIGRER` |
| `CAP-CORE-007` | [Registre des normes](CAP-CORE-007.md) | `HÉRITÉ À MIGRER` |
| `CAP-CORE-008` | [Registre des décisions](CAP-CORE-008.md) | `HÉRITÉ À MIGRER` |
| `CAP-CORE-009` | [Registre des contrats](CAP-CORE-009.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-010` | [Lexique commun](CAP-CORE-010.md) | `HÉRITÉ À MIGRER` |
| `CAP-CORE-011` | [Registre des produits](CAP-CORE-011.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-012` | [Registre des realms](CAP-CORE-012.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-013` | [Audit commun](CAP-CORE-013.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-014` | [Journal d’événements communs](CAP-CORE-014.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-015` | [Preuves d’intégrité](CAP-CORE-015.md) | `HÉRITÉ À MIGRER` |
| `CAP-CORE-016` | [Secrets et clés](CAP-CORE-016.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-017` | [Risques et exceptions](CAP-CORE-017.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-018` | [Incidents](CAP-CORE-018.md) | `IMPLÉMENTÉ PARTIEL` |
| `CAP-CORE-019` | [Sauvegarde et restauration](CAP-CORE-019.md) | `OPÉRATIONNEL LIMITÉ` |
| `CAP-CORE-020` | [Annuaire des capacités](CAP-CORE-020.md) | `HÉRITÉ À MIGRER` |
| `CAP-CORE-021` | [Moteur de Matching GAMAD](CAP-CORE-021.md) | `À CONSTRUIRE` |
| `CAP-CORE-022` | [Fédération des satellites](CAP-CORE-022.md) | `PROJETÉ` |

## Interprétation

`HÉRITÉ À MIGRER` signifie qu’un module utile peut exister, mais qu’il dépend encore de l’ancien corpus documentaire ou de concepts à renommer. Il ne doit pas être supprimé sans analyse de ses consommateurs.

`OPÉRATIONNEL LIMITÉ` ne signifie pas que tous les usages, restaurations et satellites sont couverts. Le périmètre exact doit être indiqué par les tests et l’exploitation.

Les références 21 et 22 représentent respectivement le Matching et la Fédération. Le Matching a une conception fonctionnelle mais pas de moteur complet. La Fédération est une cible structurante issue du projet de Compte GAMAD et de Portail.
